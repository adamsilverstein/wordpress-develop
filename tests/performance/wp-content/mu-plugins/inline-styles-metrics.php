<?php
/**
 * Per-function Server-Timing metrics for Trac #59596 / PR #13225.
 *
 * Measures exactly the two functions the PR changes, plus the filesystem
 * calls it aims to avoid, so the effect is visible above the noise floor of
 * the whole-request wp-total metric:
 *
 * - filesize-calls:            number of wp_filesize() invocations in the request.
 * - inline-styles-us:          integer microseconds spent in wp_maybe_inline_styles() (head + footer passes).
 * - inline-candidates:         queued style handles carrying `path` data when the head pass runs.
 * - register-block-styles-us:  integer microseconds spent in register_core_block_style_handles().
 * - block-css-transient-bytes: serialized size of the wp_core_block_css_files transient.
 *
 * Values are published via $GLOBALS['wp_perf_extra_server_timing'] and merged
 * into the Server-Timing header by server-timing.php.
 */

$GLOBALS['wp_perf_extra_server_timing'] = array(
	'filesize-calls'            => 0,
	'inline-styles-us'          => 0,
	'inline-candidates'         => 0,
	'register-block-styles-us'  => 0,
	'block-css-transient-bytes' => 0,
);

// Count every wp_filesize() call. Returning the incoming value leaves behavior unchanged.
add_filter(
	'pre_wp_filesize',
	static function ( $size ) {
		++$GLOBALS['wp_perf_extra_server_timing']['filesize-calls'];
		return $size;
	},
	0
);

/*
 * Time register_core_block_style_handles() exactly. blocks/index.php is loaded
 * before mu-plugins, so the original callback is already registered at init:9
 * and can be swapped for a timed wrapper at the same priority.
 */
if ( function_exists( 'register_core_block_style_handles' ) ) {
	remove_action( 'init', 'register_core_block_style_handles', 9 );
	add_action(
		'init',
		static function () {
			$start = hrtime( true );
			register_core_block_style_handles();
			$GLOBALS['wp_perf_extra_server_timing']['register-block-styles-us'] += intdiv( hrtime( true ) - $start, 1000 );
		},
		9
	);
}

/*
 * Time wp_maybe_inline_styles() on both of its hooks. default-filters.php is
 * loaded before mu-plugins, so the original callbacks are already registered.
 */
$wp_perf_timed_inline_styles = static function () {
	global $wp_styles;

	if ( doing_action( 'wp_head' ) && $wp_styles instanceof WP_Styles ) {
		$candidates = 0;
		foreach ( $wp_styles->queue as $handle ) {
			if ( isset( $wp_styles->registered[ $handle ] ) && $wp_styles->get_data( $handle, 'path' ) ) {
				++$candidates;
			}
		}
		$GLOBALS['wp_perf_extra_server_timing']['inline-candidates'] = $candidates;
	}

	$start = hrtime( true );
	wp_maybe_inline_styles();
	$GLOBALS['wp_perf_extra_server_timing']['inline-styles-us'] += intdiv( hrtime( true ) - $start, 1000 );
};

foreach ( array( 'wp_head', 'wp_footer' ) as $wp_perf_hook ) {
	if ( has_action( $wp_perf_hook, 'wp_maybe_inline_styles' ) ) {
		remove_action( $wp_perf_hook, 'wp_maybe_inline_styles', 1 );
		add_action( $wp_perf_hook, $wp_perf_timed_inline_styles, 1 );
	}
}
unset( $wp_perf_hook );

// After init the transient is in the object cache, so reading it here is free.
add_action(
	'init',
	static function () {
		$transient = get_transient( 'wp_core_block_css_files' );
		$GLOBALS['wp_perf_extra_server_timing']['block-css-transient-bytes'] = false === $transient ? 0 : strlen( serialize( $transient ) );
	},
	PHP_INT_MAX
);
