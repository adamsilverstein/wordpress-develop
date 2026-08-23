<?php

/**
 * @group comment
 *
 * @covers ::wp_list_comments
 */
class Tests_Comment_Walker extends WP_UnitTestCase {

	/**
	 * Comment post ID.
	 *
	 * @var int
	 */
	private $post_id;

	public function set_up() {
		parent::set_up();

		$this->post_id = self::factory()->post->create();
	}

	/**
	 * @ticket 14041
	 */
	public function test_has_children() {
		$comment_parent = self::factory()->comment->create( array( 'comment_post_ID' => $this->post_id ) );
		$comment_child  = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_parent'  => $comment_parent,
			)
		);
		$comment_parent = get_comment( $comment_parent );
		$comment_child  = get_comment( $comment_child );

		$comment_walker   = new Walker_Comment();
		$comment_callback = new Comment_Callback_Test_Helper( $this, $comment_walker );

		wp_list_comments(
			array(
				'callback' => array( $comment_callback, 'comment' ),
				'walker'   => $comment_walker,
				'echo'     => false,
			),
			array( $comment_parent, $comment_child )
		);
		wp_list_comments(
			array(
				'callback' => array( $comment_callback, 'comment' ),
				'walker'   => $comment_walker,
				'echo'     => false,
			),
			array( $comment_child, $comment_parent )
		);
	}

	/**
	 * Renders the comments on the test post and returns the markup.
	 *
	 * @param array $comments Comments to render.
	 * @param array $args     Optional. Arguments merged into the wp_list_comments() defaults.
	 *                        Default empty array.
	 * @return string Rendered markup.
	 */
	private function render_comments( $comments, $args = array() ) {
		return wp_list_comments(
			array_merge(
				array(
					'echo'   => false,
					'walker' => new Walker_Comment(),
				),
				$args
			),
			$comments
		);
	}

	/**
	 * A registered comment type's render_callback is used to render its comments.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_renders_registered_comment_type() {
		register_comment_type(
			'review',
			array(
				// Like a wp_list_comments() callback, only the element opening is output.
				'render_callback' => static function ( $comment ) {
					echo '<li class="review">' . esc_html( $comment->comment_content );
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Rendered by callback',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		// Walker_Comment::end_el() closes the element the callback opened.
		$this->assertStringContainsString( '<li class="review">Rendered by callback</li><!-- #comment-## -->', $output );
	}

	/**
	 * end_el() closes a `<div>` under the 'div' style, so a callback that always opened an
	 * `<li>` would produce mismatched markup. The callback owns that choice.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_pairs_with_the_div_style() {
		register_comment_type(
			'review',
			array(
				'render_callback' => static function ( $comment, $args ) {
					$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

					echo '<' . $tag . ' class="review">' . esc_html( $comment->comment_content );
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Rendered by callback',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ), array( 'style' => 'div' ) );

		$this->assertStringContainsString(
			'<div class="review">Rendered by callback</div><!-- #comment-## -->',
			$output
		);
	}

	/**
	 * Built-in types register without a callback and cannot be re-registered, but the
	 * registration args filter can still set one. That is the sanctioned override path,
	 * so pin it rather than leave it as an accident of the filter's placement.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_can_be_added_to_a_built_in_type_by_filter() {
		add_filter(
			'register_comment_type_args',
			static function ( $args, $comment_type ) {
				if ( 'comment' === $comment_type ) {
					$args['render_callback'] = static function () {
						echo '<li class="from-filter">';
					};
				}

				return $args;
			},
			10,
			2
		);

		// Rebuild the built-ins so the filter applies to them.
		create_initial_comment_types();

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'comment',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertStringContainsString( '<li class="from-filter">', $output );
	}

	/**
	 * An explicit wp_list_comments() callback takes precedence over a type's render_callback.
	 *
	 * @ticket 35214
	 */
	public function test_explicit_callback_takes_precedence_over_render_callback() {
		register_comment_type(
			'review',
			array(
				'render_callback' => static function () {
					echo 'FROM_TYPE_CALLBACK';
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array(
				'callback' => static function () {
					echo 'FROM_LIST_CALLBACK';
				},
			)
		);

		$this->assertStringContainsString( 'FROM_LIST_CALLBACK', $output );
		$this->assertStringNotContainsString( 'FROM_TYPE_CALLBACK', $output );
	}

	/**
	 * A registered ping type renders with the compact ping markup when short_ping is on:
	 * a label, the author link, and no comment body.
	 *
	 * @ticket 35214
	 */
	public function test_registered_ping_type_renders_as_ping() {
		register_comment_type(
			'webmention',
			array(
				'is_ping' => true,
				'labels'  => array( 'singular_name' => 'Webmention' ),
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'webmention',
				'comment_content'  => 'A webmention body',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => true )
		);

		$this->assertStringContainsString( '<div class="comment-body">', $output, 'The compact ping markup should be used.' );
		$this->assertStringContainsString( 'class="url"', $output, 'The author link should be rendered.' );
		$this->assertStringNotContainsString( 'A webmention body', $output, 'The comment body should be omitted.' );
	}

	/**
	 * The user-visible end of the grouping change: a theme listing 'pings' gets the
	 * registered ping type alongside the built-ins, and nothing else.
	 *
	 * @ticket 35214
	 */
	public function test_wp_list_comments_type_pings_lists_registered_ping_types() {
		register_comment_type( 'webmention', array( 'is_ping' => true ) );

		$comments = array();

		foreach ( array( 'comment', 'pingback', 'webmention' ) as $comment_type ) {
			$comments[] = get_comment(
				self::factory()->comment->create(
					array(
						'comment_post_ID'  => $this->post_id,
						'comment_type'     => $comment_type,
						'comment_author'   => "Author of a $comment_type",
						'comment_approved' => '1',
					)
				)
			);
		}

		$output = $this->render_comments( $comments, array( 'type' => 'pings' ) );

		$this->assertStringContainsString( 'Author of a webmention', $output );
		$this->assertStringContainsString( 'Author of a pingback', $output );
		$this->assertStringNotContainsString( 'Author of a comment', $output );
	}

	/**
	 * The compact ping markup labels a registered type with its own singular name. The
	 * built-in "Pingback:" would be plainly wrong for anything else.
	 *
	 * @ticket 35214
	 */
	public function test_registered_ping_type_renders_with_its_own_label() {
		register_comment_type(
			'webmention',
			array(
				'is_ping' => true,
				'labels'  => array( 'singular_name' => 'Webmention' ),
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'webmention',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => true )
		);

		$this->assertStringContainsString( 'Webmention:', $output );
		$this->assertStringNotContainsString( 'Pingback:', $output );
	}

	/**
	 * The built-in pingback type still renders with the compact ping markup.
	 *
	 * Guards the refactor from hard-coded `pingback`/`trackback` string checks to
	 * the `is_ping` flag: built-in ping rendering must remain unchanged.
	 *
	 * @ticket 35214
	 */
	public function test_built_in_pingback_still_renders_as_ping() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'pingback',
				'comment_content'  => 'A pingback body',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => true )
		);

		$this->assertStringContainsString( 'Pingback:', $output );
		$this->assertStringNotContainsString( 'A pingback body', $output );
	}

	/**
	 * A ping type renders its full markup (not the compact ping) when short_ping is off.
	 *
	 * @ticket 35214
	 */
	public function test_ping_type_renders_full_markup_when_short_ping_disabled() {
		register_comment_type( 'webmention', array( 'is_ping' => true ) );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'webmention',
				'comment_content'  => 'A webmention body',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => false )
		);

		// With short_ping off the full markup is rendered, including the comment body.
		$this->assertStringContainsString( 'A webmention body', $output );
	}

	/**
	 * A non-ping type is never rendered as a ping, even with short_ping enabled.
	 *
	 * @ticket 35214
	 */
	public function test_non_ping_type_is_not_rendered_as_ping_with_short_ping() {
		register_comment_type( 'review' );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'A review body',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => true )
		);

		$this->assertStringContainsString( 'A review body', $output );
		$this->assertStringNotContainsString( 'Pingback:', $output );
	}

	/**
	 * A render_callback wins over the compact ping markup for ping types.
	 *
	 * This pins the precedence chain: explicit wp_list_comments() 'callback',
	 * then 'render_callback', then is_ping short-ping markup, then default markup.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_takes_precedence_over_short_ping_markup() {
		register_comment_type(
			'webmention',
			array(
				'is_ping'         => true,
				'render_callback' => static function ( $comment ) {
					echo '<li class="webmention">' . esc_html( $comment->comment_content );
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'webmention',
				'comment_content'  => 'A webmention body',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments(
			array( get_comment( $comment_id ) ),
			array( 'short_ping' => true )
		);

		$this->assertStringContainsString( '<li class="webmention">A webmention body', $output );
		$this->assertStringNotContainsString( 'Pingback:', $output );
	}

	/**
	 * Built-in comment types without a render_callback render normally.
	 *
	 * @ticket 35214
	 */
	public function test_comment_type_without_render_callback_renders_normally() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'comment',
				'comment_content'  => 'A normal comment',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertStringContainsString( 'A normal comment', $output );
	}

	/**
	 * The render_callback receives the comment, the arguments array, and the depth,
	 * matching the wp_list_comments() `callback` contract.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_receives_comment_args_and_depth() {
		$received = array();

		register_comment_type(
			'review',
			array(
				'render_callback' => static function ( $comment, $args, $depth ) use ( &$received ) {
					$received = array(
						'comment' => $comment,
						'args'    => $args,
						'depth'   => $depth,
					);
					echo '<li>';
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_approved' => '1',
			)
		);

		$this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertInstanceOf( 'WP_Comment', $received['comment'], 'The callback should receive the comment object.' );
		$this->assertSame( (string) $comment_id, (string) $received['comment']->comment_ID, 'The callback should receive the rendered comment.' );
		$this->assertIsArray( $received['args'], 'The callback should receive the arguments array.' );
		$this->assertSame( 1, $received['depth'], 'A top-level comment should be rendered at depth 1.' );
	}

	/**
	 * A child comment renders inside the element opened by its parent's render_callback.
	 *
	 * This pins the open-tag contract end to end: the callback outputs only the
	 * element opening, children render within it, and end_el() closes each element.
	 *
	 * @ticket 35214
	 */
	public function test_child_comment_renders_within_render_callback_parent_element() {
		register_comment_type(
			'review',
			array(
				'render_callback' => static function ( $comment ) {
					echo '<li class="review">' . esc_html( $comment->comment_content );
				},
			)
		);

		$parent_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Parent review',
				'comment_approved' => '1',
			)
		);
		$child_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Child review',
				'comment_approved' => '1',
				'comment_parent'   => $parent_id,
			)
		);

		$output = $this->render_comments( array( get_comment( $parent_id ), get_comment( $child_id ) ) );

		$parent_pos   = strpos( $output, 'Parent review' );
		$children_pos = strpos( $output, '<ul class="children">' );
		$child_pos    = strpos( $output, 'Child review' );

		$this->assertNotFalse( $children_pos, 'A children list should be rendered.' );
		$this->assertGreaterThan( $parent_pos, $children_pos, 'The children list should open after the parent content.' );
		$this->assertGreaterThan( $children_pos, $child_pos, 'The child should render inside the children list.' );
		$this->assertSame( 2, substr_count( $output, '</li><!-- #comment-## -->' ), 'Each element should be closed exactly once by end_el().' );
	}

	/**
	 * A comment stored with the legacy empty string type renders the default markup.
	 *
	 * @ticket 35214
	 */
	public function test_legacy_empty_type_renders_default_markup() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => '',
				'comment_content'  => 'Legacy comment',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertStringContainsString( 'Legacy comment', $output );
		$this->assertStringContainsString( '</li><!-- #comment-## -->', $output );
	}

	/**
	 * A render_callback that is not callable is ignored and the comment renders normally.
	 *
	 * @ticket 35214
	 */
	public function test_non_callable_render_callback_is_ignored() {
		register_comment_type(
			'review',
			array(
				'render_callback' => 'this_is_not_a_callable_function',
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Falls back to normal rendering',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertStringContainsString( 'Falls back to normal rendering', $output );
	}

	/**
	 * A render_callback must echo its output; a returned string is discarded, per the
	 * documented contract. Pinned so the callback path never quietly starts honoring
	 * return values.
	 *
	 * @ticket 35214
	 */
	public function test_render_callback_return_value_is_discarded() {
		register_comment_type(
			'review',
			array(
				'render_callback' => static function () {
					return '<li>RETURNED_MARKER';
				},
			)
		);

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->post_id,
				'comment_type'     => 'review',
				'comment_content'  => 'Return value test',
				'comment_approved' => '1',
			)
		);

		$output = $this->render_comments( array( get_comment( $comment_id ) ) );

		$this->assertStringNotContainsString( 'RETURNED_MARKER', $output );
	}
}

class Comment_Callback_Test_Helper {
	private $test_walker;
	private $walker;

	public function __construct( Tests_Comment_Walker $test_walker, Walker_Comment $walker ) {
		$this->test_walker = $test_walker;
		$this->walker      = $walker;
	}

	public function comment( $comment, $args, $depth ) {
		if ( 1 === $depth ) {
			$this->test_walker->assertTrue( $this->walker->has_children );
			$this->test_walker->assertTrue( $args['has_children'] );  // Back compat.
		} elseif ( 2 === $depth ) {
			$this->test_walker->assertFalse( $this->walker->has_children );
			$this->test_walker->assertFalse( $args['has_children'] ); // Back compat.
		}
	}
}
