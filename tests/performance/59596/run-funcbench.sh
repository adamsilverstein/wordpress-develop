#!/usr/bin/env bash
# Runs funcbench.php on both sides of PR #13225 (Trac #59596).
# Usage: tests/performance/59596/run-funcbench.sh [default|memcached]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT"
# shellcheck source=env.sh
source tests/performance/59596/env.sh "${1:-default}"

BEFORE_SHA=f3c74e13a9afdb95ca7ad68a5fd69fe5aa1f61bb
AFTER_SHA=eaa0f543f9ebfe37d6e500f8e4c858ebad72145a
FILES=( src/wp-includes/blocks/index.php src/wp-includes/script-loader.php )
OUT="${ROOT}/artifacts/59596/funcbench-${1:-default}.md"

install_side() {
	git checkout "$1" -- "${FILES[@]}"
	for f in "${FILES[@]}"; do
		cp "$f" "${LOCAL_DIR}/${f#src/}"
	done
}

{
	for side in before after; do
		if [ "$side" = before ]; then install_side "$BEFORE_SHA"; else install_side "$AFTER_SHA"; fi
		echo "## ${side} ($(git log -1 --format='%h %s' "$( [ "$side" = before ] && echo "$BEFORE_SHA" || echo "$AFTER_SHA" )"))"
		echo
		npm --silent run env:cli -- eval-file tests/performance/59596/funcbench.php --path="/var/www/${LOCAL_DIR}" | sed '1d'
		echo
	done
} | tee "$OUT"

git checkout HEAD -- "${FILES[@]}"
for f in "${FILES[@]}"; do
	cp "$f" "${LOCAL_DIR}/${f#src/}"
done
echo "Written to ${OUT}"
