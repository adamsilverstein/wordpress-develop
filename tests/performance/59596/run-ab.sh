#!/usr/bin/env bash
# Full-request A/B benchmark for Trac #59596 / PR #13225.
#
# Measures the trunk parent of the PR's merge commit ("before") against the PR
# head ("after") with the existing tests/performance Playwright suite, extended
# by inline-styles-metrics.php. Only the two src/ files the PR touches differ
# between sides; they are copied straight into build/ (grunt copies PHP
# verbatim), and php-fpm is restarted so opcache picks them up.
#
# Prerequisites: tests/performance/59596/setup.sh has been run once.
#
# The two sides are interleaved in short cycles (before, after, before, ...)
# because a single long run per side is dominated by environment drift: a
# first attempt with 20 runs per side in one block showed every metric,
# including ones the PR cannot affect, 40-60% slower on whichever side ran
# second. Per-cycle results are merged with merge-results.js.
#
# Usage: tests/performance/59596/run-ab.sh [default|memcached] [TEST_RUNS] [CYCLES]
#   TEST_RUNS  total iterations per test case per side (default 20)
#   CYCLES     number of before/after cycles (default 4); TEST_RUNS is split evenly
set -euo pipefail

CONFIG="${1:-default}"
RUNS="${2:-20}"
CYCLES="${3:-4}"
RUNS_PER_CYCLE=$(( RUNS / CYCLES ))
if [ "$RUNS_PER_CYCLE" -lt 1 ]; then
	echo "TEST_RUNS must be at least CYCLES" >&2
	exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT"
# shellcheck source=env.sh
source tests/performance/59596/env.sh "$CONFIG"

BEFORE_SHA=f3c74e13a9afdb95ca7ad68a5fd69fe5aa1f61bb # trunk parent of the PR merge commit.
AFTER_SHA=eaa0f543f9ebfe37d6e500f8e4c858ebad72145a  # PR #13225 head.
FILES=( src/wp-includes/blocks/index.php src/wp-includes/script-loader.php )

export WP_ARTIFACTS_PATH="${ROOT}/artifacts/59596/${CONFIG}"
export TEST_RUNS="$RUNS_PER_CYCLE"
CYCLE_DIR="${WP_ARTIFACTS_PATH}/cycles"
mkdir -p "$CYCLE_DIR"

wp() { npm --silent run env:cli -- "$@" --path="/var/www/${LOCAL_DIR}"; }
compose() { node ./tools/local-env/scripts/docker.js "$@"; }

install_side() {
	local sha="$1"
	git checkout "$sha" -- "${FILES[@]}"
	for f in "${FILES[@]}"; do
		cp "$f" "${LOCAL_DIR}/${f#src/}"
	done
	compose restart php >/dev/null
	wp transient delete wp_core_block_css_files >/dev/null || true
	wp cache flush >/dev/null || true
	# Warm opcache, the transient and the object cache before measuring.
	for _ in 1 2 3 4 5; do
		curl -s -o /dev/null "${WP_BASE_URL}/"
		curl -s -o /dev/null "${WP_BASE_URL}/2018/11/03/block-image/"
	done
	echo "== $(git log -1 --format='%h %s' "$sha") installed. Sample Server-Timing:"
	curl -sI "${WP_BASE_URL}/" | grep -i '^server-timing' || true
}

npm run env:start
if [ "$LOCAL_PHP_MEMCACHED" = "true" ]; then
	cp tests/phpunit/includes/object-cache.php "${LOCAL_DIR}/wp-content/object-cache.php"
else
	rm -f "${LOCAL_DIR}/wp-content/object-cache.php"
fi

{
	echo "config: ${CONFIG}"
	echo "runs: ${RUNS} x repeatEach 2, interleaved in ${CYCLES} cycles of ${RUNS_PER_CYCLE}"
	echo "before: ${BEFORE_SHA}"
	echo "after: ${AFTER_SHA}"
	echo "php: $(compose exec -T php php -r 'echo PHP_VERSION;')"
	echo "php image: wordpressdevelop/php:${LOCAL_PHP}"
	echo "host: $(uname -srm), $(sysctl -n machdep.cpu.brand_string 2>/dev/null || true)"
	echo "docker: $(docker version --format '{{.Server.Version}}')"
	echo "date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} > "${WP_ARTIFACTS_PATH}/environment.txt"

before_files=()
after_files=()
for (( cycle = 1; cycle <= CYCLES; cycle++ )); do
	echo "=== Cycle ${cycle}/${CYCLES}: before"
	install_side "$BEFORE_SHA"
	TEST_RESULTS_PREFIX=before npm run test:performance
	mv "${WP_ARTIFACTS_PATH}/before-performance-results.json" "${CYCLE_DIR}/before-${cycle}.json"
	before_files+=( "${CYCLE_DIR}/before-${cycle}.json" )

	echo "=== Cycle ${cycle}/${CYCLES}: after"
	install_side "$AFTER_SHA"
	TEST_RESULTS_PREFIX= npm run test:performance
	mv "${WP_ARTIFACTS_PATH}/performance-results.json" "${CYCLE_DIR}/after-${cycle}.json"
	after_files+=( "${CYCLE_DIR}/after-${cycle}.json" )
done

node tests/performance/59596/merge-results.js "${WP_ARTIFACTS_PATH}/before-performance-results.json" "${before_files[@]}"
node tests/performance/59596/merge-results.js "${WP_ARTIFACTS_PATH}/performance-results.json" "${after_files[@]}"
node tests/performance/compare-results.js "${WP_ARTIFACTS_PATH}/summary.md"

# Leave the working tree on the branch head.
git checkout HEAD -- "${FILES[@]}"
for f in "${FILES[@]}"; do
	cp "$f" "${LOCAL_DIR}/${f#src/}"
done

echo "Results: ${WP_ARTIFACTS_PATH}"
