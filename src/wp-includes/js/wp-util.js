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
		}
	};

}(jQuery));
