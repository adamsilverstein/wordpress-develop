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
				'render_callback' => static function ( $comment ) {
					echo '<li class="review">' . esc_html( $comment->comment_content ) . '</li>';
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

		$this->assertStringContainsString( 'class="review"', $output );
		$this->assertStringContainsString( 'Rendered by callback', $output );

		unregister_comment_type( 'review' );
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

		unregister_comment_type( 'review' );
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
