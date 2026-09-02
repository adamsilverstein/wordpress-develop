# Benchmark harness for Trac #59596 / PR #13225

Measures whether caching core block stylesheet file sizes in the
`wp_core_block_css_files` transient ([PR #13225](https://github.com/WordPress/wordpress-develop/pull/13225),
[Trac #59596](https://core.trac.wordpress.org/ticket/59596)) is a measurable
win, and attributes any difference to the individual changes the PR makes.

## What the PR changes, from a performance standpoint

1. `wp_maybe_inline_styles()` reads a cached `file_size` instead of calling
   `wp_filesize()` (one `stat()` plus two filter passes) for each queued core
   block style. Runs twice per front-end request (`wp_head` and `wp_footer`).
2. `register_core_block_style_handles()` looks handles up with `isset()` on a
   keyed map instead of `in_array()` over the 624-entry file list, for each of
   its ~345 registrations per request.
3. The transient gains a `sizes` map and a keyed `files` map, so more bytes are
   unserialized from the autoloaded options blob (or object cache) per request.
4. In core development mode the transient is bypassed and every request now
   also stats all 624 CSS files.

## Instruments

- `../wp-content/mu-plugins/inline-styles-metrics.php` adds Server-Timing
  entries the existing Playwright specs pick up automatically:
  `wp-filesize-calls`, `wp-inline-styles-us`, `wp-inline-candidates`,
  `wp-register-block-styles-us`, `wp-block-css-transient-bytes`.
  `server-timing.php` merges them into its header.
- `microbench.php` isolates each of the four effects and checks whether
  `filesize()` reads the file into memory.
- `run-ab.sh` runs the full `tests/performance` suite against the PR's trunk
  parent and the PR head, swapping only the two changed PHP files.

## Reproducing

```sh
nvm use && npm ci
tests/performance/59596/setup.sh            # once; mirrors the CI perf workflow
tests/performance/59596/run-ab.sh default 20
tests/performance/59596/run-ab.sh memcached 20
```

Results land in `artifacts/59596/<config>/`: `before-performance-results.json`,
`performance-results.json`, `summary.md` (median, standard deviation and MAD per
metric), and `environment.txt`.

Micro-benchmark inside the container (WordPress loaded):

```sh
source tests/performance/59596/env.sh
npm run env:cli -- eval-file tests/performance/59596/microbench.php --path=/var/www/build
```

Standalone on the host (no `wp_filesize()` cases):

```sh
php tests/performance/59596/microbench.php src/wp-includes/blocks/
```

## Caveats

- Local Docker on macOS serves files through a virtualized bind mount, so
  absolute `stat()` costs differ from a Linux host with a local disk. The
  relative comparison between sides is unaffected.
- PHP-FPM clears its stat cache per request, so every `wp_filesize()` call in
  a request is a real `stat()`. The micro-benchmark's "cold" cases model that
  by cycling through distinct files.
- `clear-cache.php` is intentionally not installed, matching CI, so measured
  requests hit a warm opcache and a populated transient.
