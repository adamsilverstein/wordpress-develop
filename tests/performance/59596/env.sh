#!/usr/bin/env bash
# Environment overrides for the Trac #59596 / PR #13225 benchmark.
#
# These mirror .github/workflows/reusable-performance-test-v2.yml so the local
# run measures the same configuration CI does: production-like debug settings,
# transient cache active (no core development mode), built files in build/.
#
# Real environment variables take precedence over .env because the local-env
# scripts use dotenv.config(), which never overrides an existing variable.
#
# Usage: source tests/performance/59596/env.sh [memcached]

export LOCAL_PORT="${LOCAL_PORT:-8899}"
export LOCAL_DIR=build
export LOCAL_PHP="${LOCAL_PHP:-latest}"
export LOCAL_PHP_XDEBUG=false
export LOCAL_DB_TYPE=mysql
export LOCAL_DB_VERSION="${LOCAL_DB_VERSION:-8.4}"
export LOCAL_MULTISITE=false
export LOCAL_WP_DEBUG=false
export LOCAL_WP_DEBUG_LOG=false
export LOCAL_WP_DEBUG_DISPLAY=false
export LOCAL_SCRIPT_DEBUG=false
export LOCAL_SAVEQUERIES=false
export LOCAL_WP_ENVIRONMENT_TYPE=production
# Empty string: wp_is_development_mode( 'core' ) is false, so the
# wp_core_block_css_files transient is used, as in CI.
export LOCAL_WP_DEVELOPMENT_MODE="''"
export LOCAL_WP_TESTS_DOMAIN=example.org
export WP_BASE_URL="http://localhost:${LOCAL_PORT}"
export DOTENV_CONFIG_QUIET=true

if [ "${1:-}" = "memcached" ]; then
	export LOCAL_PHP_MEMCACHED=true
else
	export LOCAL_PHP_MEMCACHED=false
fi
