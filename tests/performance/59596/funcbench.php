<?php
/**
 * In-process benchmark of the two functions PR #13225 changes, for Trac #59596.
 *
 * Whole-request timings in Docker drift by more than the effect under test,
 * so this times each function directly, many iterations in one process, with
 * the same WordPress state on both sides. Run once per side via
 * run-funcbench.sh, which swaps the two changed files.
 *
 * Case A: wp_maybe_inline_styles() with a queue of core block styles and the
 *         inline size limit set to zero, so nothing is read or inlined and the
 *         per-handle size lookup is the only work the function does.
 * Case B: wp_maybe_inline_styles() with the default limit (reads and inlines
 *         CSS as on a real page), for scale.
 * Case C: register_core_block_style_handles() on a warm object cache.
 *
 *   npm run env:cli -- eval-file tests/performance/59596/funcbench.php --path=/var/www/build
 */

remove_all_filters( 'pre_wp_filesize' );

// Make the front-end code paths active in WP-CLI.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

$rounds   = 5;
$bench    = static function ( callable $fn, int $iterations ) use ( $rounds ): float {
	$samples = array();
	for ( $r = 0; $r < $rounds; $r++ ) {
		$start = hrtime( true );
		for ( $i = 0; $i < $iterations; $i++ ) {
			$fn();
		}
		$samples[] = ( hrtime( true ) - $start ) / $iterations;
	}
	sort( $samples );
	return $samples[ intdiv( $rounds, 2 ) ];
};
$fmt_us   = static fn( float $ns ): string => number_format( $ns / 1000, 1 ) . ' µs';
$filesize = 0;
add_filter(
	'pre_wp_filesize',
	static function ( $size ) use ( &$filesize ) {
		++$filesize;
		return $size;
	}
);

// Register core block styles once, as init:9 would.
register_core_block_style_handles();
$wp_styles = wp_styles();

// A realistic queue: the block styles a Twenty Twenty-Four/Five page enqueues (~22 handles).
$queue = array();
foreach ( array( 'paragraph', 'heading', 'image', 'group', 'columns', 'column', 'list', 'button', 'buttons', 'navigation', 'site-title', 'site-logo', 'post-title', 'post-content', 'post-date', 'post-excerpt', 'post-featured-image', 'post-template', 'query', 'separator', 'spacer', 'cover', 'social-links', 'template-part' ) as $block ) {
	$handle = "wp-block-{$block}";
	if ( isset( $wp_styles->registered[ $handle ] ) && $wp_styles->registered[ $handle ]->src ) {
		$queue[] = $handle;
	}
}
$queue_count = count( $queue );

/**
 * Restores the queue and every handle's src/extra so each iteration starts from the same state.
 */
$snapshot = array();
foreach ( $queue as $handle ) {
	$snapshot[ $handle ] = array( $wp_styles->registered[ $handle ]->src, $wp_styles->registered[ $handle ]->extra );
}
$reset = static function () use ( $wp_styles, $queue, $snapshot ) {
	$wp_styles->queue = $queue;
	foreach ( $snapshot as $handle => list( $src, $extra ) ) {
		$wp_styles->registered[ $handle ]->src   = $src;
		$wp_styles->registered[ $handle ]->extra = $extra;
	}
};

echo "# Function benchmark: Trac #59596 / PR #13225\n\n";
echo '- WordPress ' . wp_get_wp_version() . ', PHP ' . PHP_VERSION . "\n";
echo "- Queue: {$queue_count} core block style handles\n";
echo "- Rounds per case: {$rounds} (median reported)\n\n";

// Case A: size lookups only.
add_filter( 'styles_inline_size_limit', '__return_zero' );
$reset();
$filesize = 0;
wp_maybe_inline_styles();
$calls_a = $filesize;
$reset_ns = $bench( $reset, 2000 );
$case_a   = $bench(
	static function () use ( $reset ) {
		$reset();
		wp_maybe_inline_styles();
	},
	2000
) - $reset_ns;
remove_filter( 'styles_inline_size_limit', '__return_zero' );

// Case B: full behaviour (reads and inlines CSS).
$reset();
$filesize = 0;
wp_maybe_inline_styles();
$calls_b = $filesize;
$case_b  = $bench(
	static function () use ( $reset ) {
		$reset();
		wp_maybe_inline_styles();
	},
	100
) - $reset_ns;

// Case C: register_core_block_style_handles() with the transient in the object cache.
$case_c = $bench( 'register_core_block_style_handles', 200 );

echo "| case | wp_filesize() calls | per call |\n|---|---|---|\n";
printf( "| A. `wp_maybe_inline_styles()`, size limit 0 (lookups only) | %d | %s |\n", $calls_a, $fmt_us( $case_a ) );
printf( "| B. `wp_maybe_inline_styles()`, default limit (reads + inlines) | %d | %s |\n", $calls_b, $fmt_us( $case_b ) );
printf( "| C. `register_core_block_style_handles()`, warm cache | n/a | %s |\n", $fmt_us( $case_c ) );
