<?php
/**
 * Micro-benchmark for Trac #59596 / PR #13225 (cache core block stylesheet file sizes).
 *
 * Isolates the individual costs the PR trades off, so the whole-request A/B
 * numbers can be attributed:
 *
 *  a. filesize() / wp_filesize() per call, cold (distinct files, as in a fresh
 *     PHP-FPM request) and warm (PHP stat cache hit).
 *  b. in_array() over the 624-entry file list vs isset() on a keyed map, for
 *     the ~345 handle lookups register_core_block_style_handles() performs.
 *  c. Serialized size and unserialize() cost of the trunk-shaped vs PR-shaped
 *     wp_core_block_css_files transient (paid on every request).
 *  d. Peak memory before/after filesize() on a large file (the "loads the whole
 *     file into memory" claim).
 *  e. Cost of stat'ing all 624 files (what the PR adds per request in core
 *     development mode, where the transient is bypassed).
 *
 * Inside the local environment (WordPress loaded, wp_filesize() available):
 *   npm run env:cli -- eval-file tests/performance/59596/microbench.php --path=/var/www/build
 *
 * Standalone on the host (no WordPress; wp_filesize() cases are skipped):
 *   php tests/performance/59596/microbench.php [path/to/wp-includes/blocks/]
 *
 * Output is Markdown on stdout.
 */

$in_wp = function_exists( 'wp_filesize' );

if ( $in_wp ) {
	$blocks_path = BLOCKS_PATH;
	// Measure the core function itself, not the pre_wp_filesize counter from inline-styles-metrics.php.
	remove_all_filters( 'pre_wp_filesize' );
	remove_all_filters( 'wp_filesize' );
} else {
	$blocks_path = $argv[1] ?? __DIR__ . '/../../../src/wp-includes/blocks/';
	$blocks_path = rtrim( $blocks_path, '/' ) . '/';
}

if ( ! is_dir( $blocks_path ) ) {
	fwrite( STDERR, "Blocks directory not found: {$blocks_path}\n" );
	exit( 1 );
}

$rounds = 5;

/**
 * Runs $fn( $iterations ) $rounds times and returns the median nanoseconds per iteration.
 */
$bench = static function ( callable $fn, int $iterations ) use ( $rounds ): float {
	$samples = array();
	for ( $r = 0; $r < $rounds; $r++ ) {
		$start = hrtime( true );
		$fn( $iterations );
		$samples[] = ( hrtime( true ) - $start ) / $iterations;
	}
	sort( $samples );
	return $samples[ intdiv( $rounds, 2 ) ];
};

$fmt_ns = static fn( float $ns ): string => number_format( $ns, 0 ) . ' ns';
$fmt_us = static fn( float $ns ): string => number_format( $ns / 1000, 1 ) . ' µs';

// ---------------------------------------------------------------------------
// Inputs: the same glob register_core_block_style_handles() uses.
// ---------------------------------------------------------------------------
$found = glob( $blocks_path . '**/**.css' );
sort( $found );
$files_list = array_map( static fn( $f ) => str_replace( $blocks_path, '', $f ), $found ); // trunk shape.
$files_map  = array();
$sizes_map  = array();
foreach ( $found as $f ) {
	$rel               = str_replace( $blocks_path, '', $f );
	$files_map[ $rel ] = $rel;
	$sizes_map[ $rel ] = filesize( $f );
}
$file_count = count( $found );

// The lookups register_core_block_style_handles() performs: 3 handles per block dir, .min suffix.
$block_names = array_values( array_filter( array_map( 'basename', glob( $blocks_path . '*', GLOB_ONLYDIR ) ) ) );
$lookups     = array();
foreach ( $block_names as $name ) {
	foreach ( array( 'style', 'editor', 'theme' ) as $filename ) {
		$lookups[] = "{$name}/{$filename}.min.css";
	}
}
$lookup_count = count( $lookups );
$hits         = count( array_filter( $lookups, static fn( $k ) => isset( $files_map[ $k ] ) ) );

echo "# Micro-benchmark: Trac #59596 / PR #13225\n\n";
echo '- Mode: ' . ( $in_wp ? 'WordPress loaded (' . ( function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : $GLOBALS['wp_version'] ) . ')' : 'standalone' ) . "\n";
echo '- PHP: ' . PHP_VERSION . ' (' . PHP_OS . ")\n";
echo "- Blocks path: {$blocks_path}\n";
echo "- CSS files: {$file_count}; block directories: " . count( $block_names ) . "; handle lookups per request: {$lookup_count} ({$hits} hits)\n";
echo "- Rounds per case: {$rounds} (median reported)\n\n";

// ---------------------------------------------------------------------------
// a. filesize() / wp_filesize() cost.
// ---------------------------------------------------------------------------
$one_file = $found[0];

// Cold: cycle through distinct files. PHP's stat cache holds only the last
// stat'ed path, so each call is a real stat() syscall, as in a fresh request.
$filesize_cold = $bench(
	static function ( $n ) use ( $found, $file_count ) {
		for ( $i = 0; $i < $n; $i++ ) {
			filesize( $found[ $i % $file_count ] );
		}
	},
	20000
);
$filesize_warm = $bench(
	static function ( $n ) use ( $one_file ) {
		for ( $i = 0; $i < $n; $i++ ) {
			filesize( $one_file );
		}
	},
	200000
);
$file_exists_cold = $bench(
	static function ( $n ) use ( $found, $file_count ) {
		for ( $i = 0; $i < $n; $i++ ) {
			file_exists( $found[ $i % $file_count ] );
		}
	},
	20000
);

$rows = array(
	array( 'case' => '`filesize()`, cold (distinct files)', 'per call' => $fmt_ns( $filesize_cold ) ),
	array( 'case' => '`filesize()`, warm (same file, stat cache hit)', 'per call' => $fmt_ns( $filesize_warm ) ),
	array( 'case' => '`file_exists()`, cold (distinct files)', 'per call' => $fmt_ns( $file_exists_cold ) ),
);

$wp_filesize_cold = null;
if ( $in_wp ) {
	$wp_filesize_cold = $bench(
		static function ( $n ) use ( $found, $file_count ) {
			for ( $i = 0; $i < $n; $i++ ) {
				wp_filesize( $found[ $i % $file_count ] );
			}
		},
		20000
	);
	$wp_filesize_warm = $bench(
		static function ( $n ) use ( $one_file ) {
			for ( $i = 0; $i < $n; $i++ ) {
				wp_filesize( $one_file );
			}
		},
		200000
	);
	$rows[] = array( 'case' => '`wp_filesize()`, cold (file_exists + filesize + 2 filters)', 'per call' => $fmt_ns( $wp_filesize_cold ) );
	$rows[] = array( 'case' => '`wp_filesize()`, warm', 'per call' => $fmt_ns( $wp_filesize_warm ) );
}

echo "## a. Cost of one file size lookup\n\n";
echo md_table( $rows );

$per_call = $wp_filesize_cold ?? $filesize_cold;
$label    = $in_wp ? 'wp_filesize()' : 'filesize()';
echo "\nPer-request equivalent of the calls `wp_maybe_inline_styles()` makes on trunk (cold {$label}):\n\n";
echo md_table(
	array_map(
		static fn( $n ) => array( 'calls per request' => $n, 'time' => $fmt_us( $n * $per_call ) ),
		array( 15, 35, 100 )
	)
);

// ---------------------------------------------------------------------------
// b. in_array() vs isset() for the handle lookups.
// ---------------------------------------------------------------------------
$in_array_ns = $bench(
	static function ( $n ) use ( $lookups, $files_list, $lookup_count ) {
		for ( $i = 0; $i < $n; $i++ ) {
			in_array( $lookups[ $i % $lookup_count ], $files_list, true );
		}
	},
	50000
);
$isset_ns    = $bench(
	static function ( $n ) use ( $lookups, $files_map, $lookup_count ) {
		for ( $i = 0; $i < $n; $i++ ) {
			isset( $files_map[ $lookups[ $i % $lookup_count ] ] );
		}
	},
	500000
);

echo "\n## b. Handle lookup in `register_core_block_style_handles()`\n\n";
echo md_table(
	array(
		array(
			'case'        => "`in_array()` over {$file_count}-entry list (trunk)",
			'per lookup'  => $fmt_ns( $in_array_ns ),
			'per request' => $fmt_us( $in_array_ns * $lookup_count ),
		),
		array(
			'case'        => '`isset()` on keyed map (PR)',
			'per lookup'  => $fmt_ns( $isset_ns ),
			'per request' => $fmt_us( $isset_ns * $lookup_count ),
		),
	)
);
echo "\nPer request = {$lookup_count} lookups (one per registered handle).\n";

// ---------------------------------------------------------------------------
// c. Transient payload.
// ---------------------------------------------------------------------------
$version   = $in_wp ? ( function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : $GLOBALS['wp_version'] ) : '7.2-alpha';
$trunk_arr = array(
	'version' => $version,
	'files'   => $files_list,
);
$pr_arr    = array(
	'version' => $version,
	'files'   => $files_map,
	'sizes'   => $sizes_map,
);
$trunk_ser = serialize( $trunk_arr );
$pr_ser    = serialize( $pr_arr );

$unser_trunk = $bench(
	static function ( $n ) use ( $trunk_ser ) {
		for ( $i = 0; $i < $n; $i++ ) {
			unserialize( $trunk_ser );
		}
	},
	5000
);
$unser_pr    = $bench(
	static function ( $n ) use ( $pr_ser ) {
		for ( $i = 0; $i < $n; $i++ ) {
			unserialize( $pr_ser );
		}
	},
	5000
);

echo "\n## c. `wp_core_block_css_files` transient payload (paid on every request)\n\n";
echo md_table(
	array(
		array(
			'shape'         => 'trunk (`files` list)',
			'serialized'    => number_format( strlen( $trunk_ser ) ) . ' bytes',
			'unserialize()' => $fmt_us( $unser_trunk ),
		),
		array(
			'shape'         => 'PR (`files` map + `sizes` map)',
			'serialized'    => number_format( strlen( $pr_ser ) ) . ' bytes',
			'unserialize()' => $fmt_us( $unser_pr ),
		),
		array(
			'shape'         => 'delta (PR - trunk)',
			'serialized'    => number_format( strlen( $pr_ser ) - strlen( $trunk_ser ) ) . ' bytes',
			'unserialize()' => $fmt_us( $unser_pr - $unser_trunk ),
		),
	)
);

// ---------------------------------------------------------------------------
// d. Memory: does filesize() read the file?
// ---------------------------------------------------------------------------
$tmp_dir  = sys_get_temp_dir();
$big_file = $tmp_dir . '/wp-59596-bigfile.bin';
$chunk    = str_repeat( 'x', 1024 * 1024 );
$fh       = fopen( $big_file, 'wb' );
for ( $i = 0; $i < 64; $i++ ) {
	fwrite( $fh, $chunk );
}
fclose( $fh );
unset( $chunk );
gc_collect_cycles();

$mem_before = memory_get_peak_usage();
clearstatcache( true, $big_file );
$big_size  = filesize( $big_file );
$mem_after = memory_get_peak_usage();
$contents  = file_get_contents( $big_file );
$mem_read  = memory_get_peak_usage();
unset( $contents );
unlink( $big_file );

echo "\n## d. Memory: does `filesize()` load the file?\n\n";
echo md_table(
	array(
		array( 'step' => 'file size on disk', 'value' => number_format( $big_size / 1048576, 0 ) . ' MB' ),
		array( 'step' => 'peak memory before `filesize()`', 'value' => number_format( $mem_before / 1048576, 2 ) . ' MB' ),
		array( 'step' => 'peak memory after `filesize()`', 'value' => number_format( $mem_after / 1048576, 2 ) . ' MB (+' . number_format( ( $mem_after - $mem_before ) / 1024, 1 ) . ' KB)' ),
		array( 'step' => 'peak memory after `file_get_contents()` (for contrast)', 'value' => number_format( $mem_read / 1048576, 2 ) . ' MB' ),
	)
);

// ---------------------------------------------------------------------------
// e. Development mode: stat every CSS file on every request.
// ---------------------------------------------------------------------------
$all_stat = $bench(
	static function ( $n ) use ( $found, $file_count, $in_wp ) {
		for ( $i = 0; $i < $n; $i++ ) {
			foreach ( $found as $f ) {
				$in_wp ? wp_filesize( $f ) : filesize( $f );
			}
		}
	},
	20
);
$glob_ns  = $bench(
	static function ( $n ) use ( $blocks_path ) {
		for ( $i = 0; $i < $n; $i++ ) {
			glob( $blocks_path . '**/**.css' );
		}
	},
	20
);

echo "\n## e. Core development mode (transient bypassed on every request)\n\n";
echo md_table(
	array(
		array( 'case' => "`glob()` of all block CSS (trunk and PR)", 'per request' => $fmt_us( $glob_ns ) ),
		array( 'case' => "{$label} on all {$file_count} files (added by PR)", 'per request' => $fmt_us( $all_stat ) ),
	)
);

/**
 * Formats rows as a Markdown table.
 */
function md_table( array $rows ): string {
	$headers = array_keys( $rows[0] );
	$out     = '| ' . implode( ' | ', $headers ) . " |\n";
	$out    .= '|' . str_repeat( ' --- |', count( $headers ) ) . "\n";
	foreach ( $rows as $row ) {
		$out .= '| ' . implode( ' | ', array_map( 'strval', array_values( $row ) ) ) . " |\n";
	}
	return $out;
}
