/* global _, wp, quickDraft, pagenow, ajaxurl, postboxes, wpActiveEditor:true */
var ajaxWidgets, ajaxPopulateWidgets, QuickDraft = {};

jQuery(document).ready( function($) {
	var welcomePanel = $( '#welcome-panel' ),
		welcomePanelHide = $('#wp_welcome_panel-hide'),
		updateWelcomePanel;

	updateWelcomePanel = function( visible ) {
		$.post( ajaxurl, {
			action: 'update-welcome-panel',
			visible: visible,
			welcomepanelnonce: $( '#welcomepanelnonce' ).val()
		});
	};

	if ( welcomePanel.hasClass('hidden') && welcomePanelHide.prop('checked') ) {
		welcomePanel.removeClass('hidden');
	}

	$('.welcome-panel-close, .welcome-panel-dismiss a', welcomePanel).click( function(e) {
		e.preventDefault();
		welcomePanel.addClass('hidden');
		updateWelcomePanel( 0 );
		$('#wp_welcome_panel-hide').prop('checked', false);
	});

	welcomePanelHide.click( function() {
		welcomePanel.toggleClass('hidden', ! this.checked );
		updateWelcomePanel( this.checked ? 1 : 0 );
	});

	// These widgets are sometimes populated via ajax
	ajaxWidgets = ['dashboard_primary'];

	ajaxPopulateWidgets = function(el) {
		function show(i, id) {
			var p, e = $('#' + id + ' div.inside:visible').find('.widget-loading');
			if ( e.length ) {
				p = e.parent();
				setTimeout( function(){
					p.load( ajaxurl + '?action=dashboard-widgets&widget=' + id + '&pagenow=' + pagenow, '', function() {
						p.hide().slideDown('normal', function(){
							$(this).css('display', '');
						});
					});
				}, i * 500 );
			}
		}

		if ( el ) {
			el = el.toString();
			if ( $.inArray(el, ajaxWidgets) !== -1 ) {
				show(0, el);
			}
		} else {
			$.each( ajaxWidgets, show );
		}
	};
	ajaxPopulateWidgets();

	postboxes.add_postbox_toggles(pagenow, { pbshow: function( id ) {
		ajaxPopulateWidgets();

		if ( 'dashboard_quick_press' === id ) {
			QuickDraft.init();
		}
	} } );

	$( '.meta-box-sortables' ).sortable( 'option', 'containment', '#wpwrap' );

	function autoResizeTextarea() {
		if ( document.documentMode && document.documentMode < 9 ) {
			return;
		}

		// Add a hidden div. We'll copy over the text from the textarea to measure its height.
		$('body').append( '<div class="quick-draft-textarea-clone" style="display: none;"></div>' );

		var clone = $('.quick-draft-textarea-clone'),
			editor = $('#content'),
			editorHeight = editor.height(),
			// 100px roughly accounts for browser chrome and allows the
			// save draft button to show on-screen at the same time.
			editorMaxHeight = $(window).height() - 100;

		// Match up textarea and clone div as much as possible.
		// Padding cannot be reliably retrieved using shorthand in all browsers.
		clone.css({
			'font-family': editor.css('font-family'),
			'font-size':   editor.css('font-size'),
			'line-height': editor.css('line-height'),
			'padding-bottom': editor.css('paddingBottom'),
			'padding-left': editor.css('paddingLeft'),
			'padding-right': editor.css('paddingRight'),
			'padding-top': editor.css('paddingTop'),
			'white-space': 'pre-wrap',
			'word-wrap': 'break-word',
			'display': 'none'
		});

		// propertychange is for IE < 9
		editor.on('focus input propertychange', function() {
			var $this = $(this),
				// &nbsp; is to ensure that the height of a final trailing newline is included.
				textareaContent = $this.val() + '&nbsp;',
				// 2px is for border-top & border-bottom
				cloneHeight = clone.css('width', $this.css('width')).text(textareaContent).outerHeight() + 2;

			// Default to having scrollbars
			editor.css('overflow-y', 'auto');

			// Only change the height if it has indeed changed and both heights are below the max.
			if ( cloneHeight === editorHeight || ( cloneHeight >= editorMaxHeight && editorHeight >= editorMaxHeight ) ) {
				return;
			}

			// Don't allow editor to exceed height of window.
			// This is also bound in CSS to a max-height of 1300px to be extra safe.
			if ( cloneHeight > editorMaxHeight ) {
				editorHeight = editorMaxHeight;
			} else {
				editorHeight = cloneHeight;
			}

			// No scrollbars as we change height, not for IE < 9
			editor.css('overflow', 'hidden');

			$this.css('height', editorHeight + 'px');
		});
	}

	autoResizeTextarea();

	if ( jQuery( '#dashboard_quick_press' ).is( ':visible' ) ) {
		QuickDraft.init();
	}
});

// Set up the QuickDraft views.
QuickDraft.Views = {};

/**
 * Set up a view object for the quick draft form.
 *
 * @since 4.8.0
 *
 * @augments wp.Backbone.View
 */
QuickDraft.Views.Form = wp.Backbone.View.extend( {

	// Set up our default action handlers.
	events: {

		// Hide the prompt whena field receives focus.
		'focus :input': 'hidePrompt',

		// Possibly re-display the prompt when a field looses focus.
		'blur :input':  'showPrompt',

		// Listen for a reset event, showing all prompts.
		'reset':        'showAllPrompts',

		// Listed for and handle form submissions.
		'submit':       'handleFormSubmission'
	},

	// Initialize the QuickDraft Form view.
	initialize: function() {

		// Show all prompts in the form to begin.
		this.showAllPrompts();

		// Rerender if the error state changes.
		quickDraft.state.on( 'change:errorState', _.bind( this.render, this ) );
	},

	/**
	 * Handle toggling of the helper prompts shown inside each field.
	 *
	 * Will only show the prompt if the field is empty.
	 *
	 * @param  {object}  element Field element containing the helper prompt.
	 * @param  {boolean} visible Should the prompt be visible?
	 */
	togglePrompt: function( element, visible ) {
		var $input = jQuery( element ),
			hasContent = $input.val().length > 0;

		// Set the visibility of the elements nearest prompt to passed 'visible' value.
		jQuery( element ).siblings( '.prompt' ).toggleClass( 'screen-reader-text', ! visible || hasContent );
	},

	// Show all of the field promts.
	showAllPrompts: function() {

		// Show all of the field prompts.
		this.$el.find( ':input' ).each( _.bind( function( i, input ) {
			_.defer( _.bind( this.togglePrompt, this, input, true ) );
		}, this ) );
	},

	/**
	 * Show the prompt inside a field.
	 *
	 * @param {object} event The event triggering this show request.
	 */
	showPrompt: function( event ) {
		this.togglePrompt( event.target, true );
	},

	/**
	 * Hide the prompt inside a field.
	 *
	 * @param {object} event The event triggering this hide request.
	 */
	hidePrompt: function( event ) {
		this.togglePrompt( event.target, false );
	},

	/**
	 * Handle error conditions.
	 *
	 * {string} error The error condition, or false to reset.
	 */
	setErrorState: function( error ) {

		// Set or reset the app state error condition.
		quickDraft.state.set( 'errorState', error );

		if ( false !== error ) {

			// Alert screen readers that an error occurred.
			wp.a11y.speak( error, 'assertive' );
		}

	},

	/**
	 * Handle the form submission event.
	 *
	 * @param {object} event The form submission event.
	 */
	handleFormSubmission: function( event ) {
		var values,
			hasValuesToSave = false;

		// Prevent the browser's default form submission handling.
		event.preventDefault();

		// Prevent double submissions by checking the submitting state.
		if ( quickDraft.state.get( 'submitting' ) ) {
			return;
		}

		// Reset the error state.
		this.setErrorState( false );

		// Extract the form field values.
		values = _.reduce( this.$el.serializeArray(), function( memo, field ) {
			memo[ field.name ] = field.value;
			hasValuesToSave    = hasValuesToSave || ( '' !== field.value );
			return memo;
		}, {} );

		// If the values are all blank, show an error.
		if ( ! hasValuesToSave ) {

			// Set the error.
			this.setErrorState( quickDraft.l10n.errorEmptyFields );
			return;
		}

		// Save the new values to the model and confirm they are valid.
		this.model.set( values );
		if ( ! this.model.isValid() ) {
			return;
		}

		// Show a spinner during the callback.
		this.$el.addClass( 'is-saving' );


		// Set the state sbmitting to avoid double saves
		quickDraft.state.set( 'submitting', true );

		// Trigger the model save.
		this.model.save()

			// Always remove the spinner.
			.always(
				_.bind( function() {
					this.$el.removeClass( 'is-saving' );

					// Refocus in the title field, hiding its prompt.
					_.delay( function() { jQuery( '#quick-press input#title' ).focus(); }, 250 );

					// Submission complete
					quickDraft.state.set( 'submitting', false );

				}, this )
			)

			// Handle save success.
			.success(
				_.bind( function() {
					// Success! Clear any previous error state.
					 this.render();

					// Add the post model to the head of our collection.
					this.collection.add( this.model, { at: 0 } );

					// Create a new post model to contain the form data and reset the form.
					this.model = new wp.api.models.Post();
					this.model.on( 'change:errorState', _.bind( this.render, this ) );

					this.el.reset();
				}, this )
			)

			// Handle save failure.
			.error(
				_.bind( function( model, error ) {
					var message = '';

					// Try to parse and use the response message.
					try {
						message = JSON.parse( error.responseText ).message;
					} catch( e ) {

						// Fall back to a default error string if the parse fails.
						message = quickDraft.l10n.error;
					}

					// Set the app error condition.
					this.setErrorState( message );
				}, this )
			);
	},

	// Render the form view.
	render: function() {
		var $error    = this.$el.find( '.notice-alt' ),
			errorText = quickDraft.state.get( 'errorState' );

		// Error notice is only visible if error text is set.
		$error.toggleClass( 'hidden', ! errorText );
		if ( errorText ) {

			// Note: The inner text transform prevents XSS via html().
			$error.html( jQuery( '<p />', { text: errorText } ) );
		}
	}
} );

/**
 * Set up a view object for the Quick Draft list of drafts.
 *
 * @since 4.8.0
 *
 * @augments wp.Backbone.View
 */
QuickDraft.Views.DraftList = wp.Backbone.View.extend( {

	// Initialize the draft list view.
	initialize: function() {

		// Render the view once the drafts have loaded.
		this.listenTo( this.collection, 'sync', this.onDraftsLoaded );
	},

	// Once the drafts have loaded, complete the setup.
	onDraftsLoaded: function() {

		// Add a listener for new items added to the underlying (draft) post collection.
		this.listenTo( this.collection, 'add', this.renderNew );

		// Render the view!
		this.render();
	},

	// Handle a new item being added to the collection.
	renderNew: function() {

		// Display highlight effect to first (added) item for one second.
		var $newEl = this.render().$el.find( 'li:first' ).addClass( 'is-new' );
		setTimeout( function() {
			$newEl.removeClass( 'is-new' );
		}, 1000 );

		// Alert screen readers that a new draft has been added.
		wp.a11y.speak( quickDraft.l10n.newDraftCreated, 'assertive' );
	},

	// Render the draft post list view.
	render: function() {

		// Hide drafts list entirely if no drafts exist.
		this.$el.toggle( this.collection.length > 0 );

		// Display a 'View All' link if there are more drafts available.
		this.$el.find( '.view-all' ).toggle( this.collection.hasMore() );

		// Remove the placeholder class and render the models.
		this.$el.find( '.drafts-list' )
			.removeClass( 'is-placeholder' )
			.html(
				_.map( this.collection.models, function( draft ) {
					return new QuickDraft.Views.DraftListItem( {
						model: draft
					} ).render().el;
				} )
			);

		return this;
	}
} );

/**
 * Set up a view object an individual draft in the draft list.
 *
 * @since 4.8.0
 *
 * @augments wp.Backbone.View
 */
QuickDraft.Views.DraftListItem = wp.Backbone.View.extend( {
	tagName: 'li',

	// Render beased on the passed template.
	template: wp.template( 'item-quick-press-draft' ),

	// Render a single draft list item.
	render: function() {

		// Clone the original model attributes, so we can leave the model untouched.
		var attributes = _.clone( this.model.attributes );

		// Trim the content to 10 words.
		attributes.formattedContent = wp.formatting.trimWords( attributes.content.rendered, 10 );

		// If the title is missing entirely, add a no title placeholder.
		attributes.formattedTitle = attributes.title.rendered.length > 0 ? attributes.title.rendered : quickDraft.l10n.noTitle;

		// Format the data using Intl.DateTimeFormat with a fallback to date.toLocaleDateString.
		var date = new Date( wp.api.utils.parseISO8601( attributes.date + quickDraft.timezoneOffset ) );
		if ( 'undefined' !== typeof Intl && Intl.DateTimeFormat ) {
			attributes.formattedDate = new Intl.DateTimeFormat( undefined, {
				month: 'long',
				day: 'numeric',
				year: 'numeric'
			} ).format( date );
		} else {
			attributes.formattedDate = date.toLocaleDateString();
		}

		// Output the rendered template.
		this.$el.html( this.template( attributes ) );

		// Continue the rendering chain.
		return this;
	}
} );


/**
 * Initialize the Quick Draft feature.
 *
 * @since 4.8.0
 *
 */
QuickDraft.init = function() {

	// Set up a state model to track the application state.
	quickDraft.state = new Backbone.Model({
		'errorState': false
	});

	// Wait for the wp-api client to initialize.
	wp.api.loadPromise.done( function() {

		// Fetch up to 4 of the current user's recent drafts by extending wp.api.collections.Posts.
		var draftsCollection = new wp.api.collections.Posts();
		draftsCollection.fetch( {
			data: {
				status: 'draft',
				author: quickDraft.currentUserId,
				per_page: 4,
				order_by: 'date',
				'quick-draft-post-list': true /* flag passed for back end filters */
			}
		} );

		// Drafts list is initialized but not rendered until drafts load.
		new QuickDraft.Views.DraftList( {
			el: '#quick-press-drafts',
			collection: draftsCollection
		} );

		new QuickDraft.Views.Form( {
			el: '#quick-press',
			model: new wp.api.models.Post(),
			collection: draftsCollection
		} ).render();
	});
};
