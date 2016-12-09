/* global _wpUtilSettings */
window.wp = window.wp || {};

(function ($) {
	// Check for the utility settings.
	var settings = typeof _wpUtilSettings === 'undefined' ? {} : _wpUtilSettings;

	/**
	 * wp.template( id )
	 *
	 * Fetch a JavaScript template for an id, and return a templating function for it.
	 *
	 * @param  {string} id   A string that corresponds to a DOM element with an id prefixed with "tmpl-".
	 *                       For example, "attachment" maps to "tmpl-attachment".
	 * @return {function}    A function that lazily-compiles the template requested.
	 */
	wp.template = _.memoize(function ( id ) {
		var compiled,
			/*
			 * Underscore's default ERB-style templates are incompatible with PHP
			 * when asp_tags is enabled, so WordPress uses Mustache-inspired templating syntax.
			 *
			 * @see trac ticket #22344.
			 */
			options = {
				evaluate:    /<#([\s\S]+?)#>/g,
				interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
				escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
				variable:    'data'
			};

		return function ( data ) {
			compiled = compiled || _.template( $( '#tmpl-' + id ).html(),  options );
			return compiled( data );
		};
	});

	// wp.ajax
	// ------
	//
	// Tools for sending ajax requests with JSON responses and built in error handling.
	// Mirrors and wraps jQuery's ajax APIs.
	wp.ajax = {
		settings: settings.ajax || {},

		/**
		 * wp.ajax.post( [action], [data] )
		 *
		 * Sends a POST request to WordPress.
		 *
		 * @param  {(string|object)} action  The slug of the action to fire in WordPress or options passed
		 *                                   to jQuery.ajax.
		 * @param  {object=}         data    Optional. The data to populate $_POST with.
		 * @return {$.promise}     A jQuery promise that represents the request,
		 *                         decorated with an abort() method.
		 */
		post: function( action, data ) {
			return wp.ajax.send({
				data: _.isObject( action ) ? action : _.extend( data || {}, { action: action })
			});
		},

		/**
		 * wp.ajax.send( [action], [options] )
		 *
		 * Sends a POST request to WordPress.
		 *
		 * @param  {(string|object)} action  The slug of the action to fire in WordPress or options passed
		 *                                   to jQuery.ajax.
		 * @param  {object=}         options Optional. The options passed to jQuery.ajax.
		 * @return {$.promise}      A jQuery promise that represents the request,
		 *                          decorated with an abort() method.
		 */
		send: function( action, options ) {
			var promise, deferred;
			if ( _.isObject( action ) ) {
				options = action;
			} else {
				options = options || {};
				options.data = _.extend( options.data || {}, { action: action });
			}

			options = _.defaults( options || {}, {
				type:    'POST',
				url:     wp.ajax.settings.url,
				context: this
			});

			deferred = $.Deferred( function( deferred ) {
				// Transfer success/error callbacks.
				if ( options.success )
					deferred.done( options.success );
				if ( options.error )
					deferred.fail( options.error );

				delete options.success;
				delete options.error;

				// Use with PHP's wp_send_json_success() and wp_send_json_error()
				deferred.jqXHR = $.ajax( options ).done( function( response ) {
					// Treat a response of 1 as successful for backward compatibility with existing handlers.
					if ( response === '1' || response === 1 )
						response = { success: true };

					if ( _.isObject( response ) && ! _.isUndefined( response.success ) )
						deferred[ response.success ? 'resolveWith' : 'rejectWith' ]( this, [response.data] );
					else
						deferred.rejectWith( this, [response] );
				}).fail( function() {
					deferred.rejectWith( this, arguments );
				});
			});

			promise = deferred.promise();
			promise.abort = function() {
				deferred.jqXHR.abort();
				return this;
			};

			return promise;
		}
	};

	// wp.formatting
	// ------
	//
	// Tools for formatting strings
	wp.formatting = {
		settings: settings.formatting || {},

		/**
		 * Trims text to a certain number of words.
		 *
		 * @see wp_trim_words
		 *
		 * @param  {string} text     Text to trim.
		 * @param  {number} numWords Number of words. Optional, default is 55.
		 * @param  {string} more     What to append if text needs to be trimmed. Optional, default is '…'.
		 * @return {string}          Trimmed text.
		 */
		trimWords: function( text, numWords, more ) {
			var words, separator;

			if ( 'undefined' === typeof numWords ) {
				numWords = 55;
			}

			if ( 'undefined' === typeof more ) {
				more = wp.formatting.settings.trimWordsMore;
			}

			text = text.replace( /[\n\r\t ]+/g, ' ' ).replace( /^ | $/g, '' );

			if ( wp.formatting.settings.trimWordsByCharacter ) {
				separator = '';
			} else {
				separator = ' ';
			}

			words = text.split( separator );

			if ( words.length <= numWords ) {
				return words.join( separator );
			}

			return words.slice( 0, numWords ).join( separator ) + more;
		},

		/**
		 * Given optional date and format string, returns a localized date
		 * string approximating specified format. Uses browser i18n features
		 * when available, with fallback. Undefined arguments default to now
		 * and site configured date format respectively.
		 *
		 * @see http://php.net/manual/en/function.date.php
		 *
		 * @param  {?*}      date   Optional date object or timestamp, defaults
		 *                          to now
		 * @param  {?String} format Optional PHP date format string, defaults
		 *                          to site date format option
		 * @return {String}         Localized formatted date string
		 */
		date: function( date, format ) {
			var options, matches, m, ml, character, locales;

			// Cast date parameter to date object. This accommodates timestamp,
			// Date object, ISO8601 string, and undefined (defaulting to now).
			date = new Date( date );

			// Adjust JavaScript date from local browser timezone to GMT+0
			date.setTime( date.getTime() + ( date.getTimezoneOffset() * 60000 ) );

			// If no browser support for Intl, return fallback
			if ( 'undefined' === typeof Intl || ! Intl.DateTimeFormat ) {
				return date.toLocaleDateString();
			}

			// Default format to site date option
			if ( 'undefined' === typeof format ) {
				format = wp.formatting.settings.dateFormat;
			}

			matches = format.match( /\\?[a-zA-Z]/g );
			if ( ! matches ) {
				return format;
			}

			options = {};
			for ( m = 0, ml = matches.length; m < ml; m++ ) {
				character = matches[ m ];

				// Ignore characters prefixed with backslash
				if ( 0 === character.indexOf( '\\' ) ) {
					continue;
				}

				switch ( character ) {
					// Supported:
					case 'd': options.day = '2-digit'; break;
					case 'D': options.weekday = 'short'; break;
					case 'j': options.day = 'numeric'; break;
					case 'l': options.weekday = 'long'; break;
					case 'F': options.month = 'long'; break;
					case 'm': options.month = '2-digit'; break;
					case 'M': options.month = 'short'; break;
					case 'n': options.month = 'numeric'; break;
					case 'Y': options.year = 'numeric'; break;
					case 'y': options.year = '2-digit'; break;
					case 'g': options.hour = 'numeric'; options.hour12 = true; break;
					case 'G': options.hour = 'numeric'; options.hour12 = false; break;
					case 'h': options.hour = '2-digit'; options.hour12 = true; break;
					case 'H': options.hour = '2-digit'; options.hour12 = false; break;
					case 'i': options.minute = '2-digit'; break;
					case 's': options.second = '2-digit'; break;
					case 'e': options.timeZoneName = 'long'; break;
					case 'T': options.timeZoneName = 'short'; break;
					case 'c': return date.toISOString();
					case 'U': return Number( date );

					// Unsupported with fallback:
					case 'N': options.weekday = 'narrow'; break;
					case 'w': options.weekday = 'narrow'; break;
					case 'o': options.year = 'numeric'; break;
					case 'O': options.timeZoneName = 'short'; break;
					case 'P': options.timeZoneName = 'short'; break;
					case 'Z': options.timeZoneName = 'short'; break;
					case 'r': return String( time );

					// Unsupported: 'S', 'z', 'W', 't', 'L', 'a', 'A', 'B', 'u', 'v', 'I'
				}
			}

			locales = wp.formatting.settings.userLocale.replace( '_', '-' );
			return new Intl.DateTimeFormat( locales, options ).format( date );
		}
	};

}(jQuery));
