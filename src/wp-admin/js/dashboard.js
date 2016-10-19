/* global wp, quickPress, pagenow, ajaxurl, postboxes, wpActiveEditor:true */
var ajaxWidgets, ajaxPopulateWidgets, quickPressLoad;

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

	postboxes.add_postbox_toggles(pagenow, { pbshow: ajaxPopulateWidgets } );

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

} );

wp.api.loadPromise.done( function() {
	var $ = jQuery,
		QuickPress = {},
		draftsCollection;

	QuickPress.Models = {};

	QuickPress.Models.Draft = wp.api.models.Post.extend({
		initialize: function( attributes ) {
			if ( attributes ) {
				this.set( this.normalizeAttributes( attributes ) );
			}
		},

		parse: function( response ) {
			return this.normalizeAttributes( response );
		},

		sync: function() {
			this.set( 'date', ( new Date() ).toISOString() );

			return Backbone.sync.apply( this, arguments );
		},

		normalizeAttributes: function( attributes ) {
			if ( ! attributes ) {
				return attributes;
			}

			if ( 'object' === typeof attributes.content ) {
				attributes.content = attributes.content.rendered;
			}

			if ( 'object' === typeof attributes.title ) {
				attributes.title = attributes.title.rendered;
			}

			return attributes;
		},

		validate: function( attributes ) {
			if ( ! attributes.title && ! attributes.content ) {
				return 'no-content';
			}
		}
	});

	QuickPress.Collections = {};

	QuickPress.Collections.Drafts = wp.api.collections.Posts.extend({
		model: QuickPress.Models.Draft,

		comparator: function( a, b ) {
			return a.get( 'date' ) < b.get( 'date' );
		}
	});

	QuickPress.Views = {};

	QuickPress.Views.Form = wp.Backbone.View.extend({
		events: {
			'click #title-wrap,#description-wrap': 'hidePromptAndFocus',
			'focus #title-wrap,#description-wrap': 'hidePrompt',
			'blur #title-wrap,#description-wrap': 'showPrompt',
			click: 'setActiveEditor',
			focusin: 'setActiveEditor',
			submit: 'submit'
		},

		initialize: function() {
			this.listenTo( this.model, 'invalid', this.render );
		},

		togglePrompt: function( element, visible ) {
			var $input = $( ':input', element ),
				hasContent = $input.val().length > 0;

			$( '.prompt', element ).toggleClass( 'screen-reader-text', ! visible || hasContent );
		},

		showPrompt: function( event ) {
			this.togglePrompt( event.currentTarget, true );
		},

		hidePrompt: function( event ) {
			this.togglePrompt( event.currentTarget, false );
		},

		hidePromptAndFocus: function( event ) {
			this.togglePrompt( event.currentTarget, false );
			$( ':input', event.target ).focus();
		},

		setActiveEditor: function() {
			wpActiveEditor = 'content';
		},

		submit: function( event ) {
			var values;

			event.preventDefault();

			values = this.$el.serializeArray().reduce( function( memo, field ) {
				memo[ field.name ] = field.value;
				return memo;
			}, {} );

			this.model.set( values );
			if ( ! this.model.isValid() ) {
				return;
			}

			this.model.save()
				// TODO: `always` should be `done` to handle success only
				.always( function() {
					this.collection.add( this.model );
				}.bind( this ) );
				// .fail( function() {
				// 	// TODO: Handle failure
				// } );

			// TODO: Clear form model
		},

		render: function() {
			var $error = this.$el.find( '.error' );

			$error.toggle( !! this.model.validationError );
			if ( this.model.validationError ) {
				$error.html( $( '<p />', {
					text: quickPress.l10n[ this.model.validationError ]
				} ) );
			}
		}
	});

	QuickPress.Views.DraftList = wp.Backbone.View.extend({
		initialize: function() {
			this.listenTo( this.collection, 'add', this.render );
		},

		render: function() {
			var slicedCollection = this.collection.slice( 0, 4 );

			this.$el.toggle( this.collection.length > 0 );
			this.$el.find( '.view-all' ).toggle( slicedCollection.length > 3 );
			this.$el.find( '.drafts-list' ).html( slicedCollection.map( function( draft ) {
				return new QuickPress.Views.DraftListItem({
					model: draft
				}).render().el;
			}) );

			return this;
		}
	});

	QuickPress.Views.DraftListItem = wp.Backbone.View.extend({
		tagName: 'li',

		template: wp.template( 'item-quick-press-draft' ),

		render: function() {
			// TODO: Render highlight effect to new post

			this.$el.html( this.template( this.model.attributes ) );

			return this;
		}
	});

	draftsCollection = new QuickPress.Collections.Drafts( quickPress.data.data );

	new QuickPress.Views.DraftList({
		el: '#quick-press-drafts',
		collection: draftsCollection
	}).render();

	new QuickPress.Views.Form({
		el: '#quick-press',
		model: new QuickPress.Models.Draft(),
		collection: draftsCollection
	}).render();
} );
