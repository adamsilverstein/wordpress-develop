#!/usr/bin/env bash
# One-time site setup for the Trac #59596 / PR #13225 benchmark.
# Mirrors .github/workflows/reusable-performance-test-v2.yml. Run from the repo root
# after `npm ci`. Re-runnable; `env:install` resets the database.
#
# Usage: tests/performance/59596/setup.sh [default|memcached]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$ROOT"
# shellcheck source=env.sh
source tests/performance/59596/env.sh "${1:-default}"

THEME_TEST_DATA_SHA=b9752e0533a5acbb876951a8cbb5bcc69a56474c

wp() { npm --silent run env:cli -- "$@" --path="/var/www/${LOCAL_DIR}"; }

npm run env:start
if [ ! -f "${LOCAL_DIR}/wp-settings.php" ]; then
	npm run build
fi
npm run env:install

wp core version
wp plugin install wordpress-importer --activate
curl -sSLo themeunittestdata.wordpress.xml "https://raw.githubusercontent.com/WordPress/theme-test-data/${THEME_TEST_DATA_SHA}/themeunittestdata.wordpress.xml"
wp import themeunittestdata.wordpress.xml --authors=create
rm themeunittestdata.wordpress.xml
wp plugin deactivate wordpress-importer
wp language core install de_DE
wp language plugin install de_DE --all
wp language theme install de_DE --all
wp config set WP_HTTP_BLOCK_EXTERNAL true --raw --type=constant
wp config set DISABLE_WP_CRON true --raw --type=constant

# Only server-timing.php is installed in CI; clear-cache.php is deliberately left
# out so measured requests hit a warm opcache, as they do there.
mkdir -p "${LOCAL_DIR}/wp-content/mu-plugins"
cp tests/performance/wp-content/mu-plugins/server-timing.php "${LOCAL_DIR}/wp-content/mu-plugins/"
cp tests/performance/wp-content/mu-plugins/inline-styles-metrics.php "${LOCAL_DIR}/wp-content/mu-plugins/"

wp config list
echo "Setup complete: ${WP_BASE_URL}"
