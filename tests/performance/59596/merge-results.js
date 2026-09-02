/**
 * Merges per-cycle performance-results JSON files into one file, so that an
 * interleaved A/B run (before, after, before, after, ...) can be compared with
 * compare-results.js. Each input has the shape written by
 * config/performance-reporter.js: an array of { file, title, results[] }
 * where results holds one metrics object per repeat. Results for the same
 * title are concatenated in input order.
 *
 * Usage: node merge-results.js <output.json> <input1.json> [<input2.json> ...]
 */
const { readFileSync, writeFileSync } = require( 'node:fs' );

const [ output, ...inputs ] = process.argv.slice( 2 );
if ( ! output || inputs.length === 0 ) {
	console.error( 'Usage: node merge-results.js <output.json> <input.json>...' );
	process.exit( 1 );
}

const merged = new Map();
for ( const input of inputs ) {
	for ( const entry of JSON.parse( readFileSync( input, 'utf8' ) ) ) {
		if ( ! merged.has( entry.title ) ) {
			merged.set( entry.title, { file: entry.file, title: entry.title, results: [] } );
		}
		merged.get( entry.title ).results.push( ...entry.results );
	}
}

writeFileSync( output, JSON.stringify( [ ...merged.values() ], null, 2 ) );
console.log( `Merged ${ inputs.length } files into ${ output }` );
