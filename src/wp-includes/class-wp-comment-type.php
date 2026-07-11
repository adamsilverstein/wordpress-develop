<?php
/**
 * Comment API: WP_Comment_Type class
 *
 * @package WordPress
 * @subpackage Comments
 * @since 7.1.0
 */

/**
 * Core class used for interacting with comment types.
 *
 * @since 7.1.0
 *
 * @see register_comment_type()
 */
#[AllowDynamicProperties]
final class WP_Comment_Type {
	/**
	 * Comment type key.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $name;

	/**
	 * Name of the comment type. Usually plural.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $label;

	/**
	 * Labels object for this comment type.
	 *
	 * If not set, the default comment labels are used.
	 *
	 * @see get_comment_type_labels()
	 *
	 * @since 7.1.0
	 * @var stdClass
	 */
	public $labels;

	/**
	 * Default labels.
	 *
	 * @since 7.1.0
	 * @var (string|null)[][] $default_labels
	 */
	protected static $default_labels = array();

	/**
	 * A short descriptive summary of what the comment type is for.
	 *
	 * @since 7.1.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether a comment type is intended for use publicly either via the admin interface or by front-end users.
	 *
	 * Core does not currently act on this property, but it is the intended default
	 * for future visibility-related arguments. It defaults to true so that
	 * registering a type in order to provide labels never hides comments that are
	 * already publicly visible.
	 *
	 * Default true.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $public = true;

	/**
	 * Whether the comment type is for internal use only.
	 *
	 * Analogous to the `internal` argument of register_post_status(). Core does not
	 * currently consult this property: the exclusion of the built-in `note` type
	 * from default comment queries is hard-coded. The property is intended to drive
	 * that exclusion for registered types in the future.
	 *
	 * Default false.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $internal = false;

	/**
	 * Whether this comment type is a native or "built-in" comment type.
	 *
	 * Default false.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $_builtin = false;

	/**
	 * Callback used to render a comment of this type in comment lists.
	 *
	 * When set to a callable, {@see Walker_Comment} invokes it to render a
	 * comment of this type, receiving the same arguments as the `callback`
	 * argument of wp_list_comments(): the comment object, the arguments array,
	 * and the depth. The precedence chain is: an explicit `callback` passed to
	 * wp_list_comments(), then this callback, then the default markup.
	 *
	 * Like the `callback` argument of wp_list_comments(), the callback must only
	 * output the opening of the list element (an unclosed `<li>` by default);
	 * {@see Walker_Comment::end_el()} (or the `end-callback` argument) closes
	 * the element after any child comments have been rendered.
	 *
	 * Output from the callback is printed unescaped; the callback is
	 * responsible for escaping all output.
	 *
	 * Only applies when comments are rendered via wp_list_comments() (classic
	 * themes). Block themes render comments through the `core/comment-template`
	 * block and do not invoke this callback.
	 *
	 * Default null.
	 *
	 * @since 7.1.0
	 * @var callable|null
	 */
	public $render_callback = null;

	/**
	 * Whether the comment type represents a ping (a notification from another site)
	 * rather than a human-authored comment.
	 *
	 * Ping types (such as `pingback` and `trackback`) are grouped together by
	 * {@see separate_comments()} and rendered with the compact ping markup by
	 * {@see Walker_Comment}. Default false.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $is_ping = false;

	/**
	 * Whether the comment type is hierarchical.
	 *
	 * Comment types are never hierarchical. This property exists so the shared
	 * label helper {@see _get_custom_object_labels()} can resolve default labels.
	 *
	 * @since 7.1.0
	 * @var bool
	 */
	public $hierarchical = false;

	/**
	 * Constructor.
	 *
	 * See the register_comment_type() function for accepted arguments for `$args`.
	 *
	 * Will populate object properties from the provided arguments and assign other
	 * default properties based on that information.
	 *
	 * @since 7.1.0
	 *
	 * @see register_comment_type()
	 *
	 * @param string       $comment_type Comment type key.
	 * @param array|string $args         Optional. Array or string of arguments for registering a comment type.
	 *                                   Default empty array.
	 */
	public function __construct( $comment_type, $args = array() ) {
		$this->name = $comment_type;

		$this->set_props( $args );
	}

	/**
	 * Sets comment type properties.
	 *
	 * See the register_comment_type() function for accepted arguments for `$args`.
	 *
	 * @since 7.1.0
	 *
	 * @param array|string $args Array or string of arguments for registering a comment type.
	 */
	public function set_props( $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a comment type.
		 *
		 * @since 7.1.0
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 *                             See the register_comment_type() function for accepted arguments.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( 'register_comment_type_args', $args, $this->name );

		$comment_type = $this->name;

		/**
		 * Filters the arguments for registering a specific comment type.
		 *
		 * The dynamic portion of the filter name, `$comment_type`, refers to the comment type key.
		 *
		 * Possible hook names include:
		 *
		 *  - `register_comment_comment_type_args`
		 *  - `register_pingback_comment_type_args`
		 *
		 * @since 7.1.0
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 *                             See the register_comment_type() function for accepted arguments.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( "register_{$comment_type}_comment_type_args", $args, $this->name );

		/*
		 * Note: 'label' is intentionally omitted from the defaults. Leaving the property
		 * unset (null) lets get_comment_type_labels() fall back to the default labels, the
		 * same way WP_Post_Type and WP_Taxonomy behave. A 'label' default of false would be
		 * treated as a provided value and overwrite the default name with false.
		 */
		$defaults = array(
			'labels'          => array(),
			'description'     => '',
			'public'          => true,
			'internal'        => false,
			'render_callback' => null,
			'is_ping'         => false,
			'_builtin'        => false,
		);

		$args = array_merge( $defaults, $args );

		$args['name'] = $this->name;

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}

		$this->labels = get_comment_type_labels( $this );
		$this->label  = $this->labels->name;
	}

	/**
	 * Returns the default labels for comment types.
	 *
	 * @since 7.1.0
	 *
	 * @return (string|null)[][] The default labels for comment types.
	 */
	public static function get_default_labels() {
		if ( ! empty( self::$default_labels ) ) {
			return self::$default_labels;
		}

		self::$default_labels = array(
			'name'          => array( _x( 'Comments', 'comment type general name' ), null ),
			'singular_name' => array( _x( 'Comment', 'comment type singular name' ), null ),
		);

		return self::$default_labels;
	}

	/**
	 * Resets the cache for the default labels.
	 *
	 * @since 7.1.0
	 */
	public static function reset_default_labels() {
		self::$default_labels = array();
	}
}
