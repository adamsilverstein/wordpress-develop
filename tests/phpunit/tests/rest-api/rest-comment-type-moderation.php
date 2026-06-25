<?php
/**
 * Integration tests for per-comment-type moderation in the REST comments controller.
 *
 * Exercises the `check_edit_permission()` gate end to end: a comment type with an
 * independent capability model is moderated through its own primitives, while the
 * default capability model behaves exactly as before.
 *
 * @group restapi
 * @group comment
 * @group capabilities
 *
 * @covers WP_REST_Comments_Controller::check_edit_permission
 */
class Tests_REST_Comment_Type_Moderation extends WP_UnitTestCase {

	/**
	 * Post the comments are attached to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * User with the global `moderate_comments` primitive only.
	 *
	 * @var int
	 */
	protected static $global_moderator_id;

	/**
	 * User with the `review` type's `moderate_reviews` primitive only.
	 *
	 * @var int
	 */
	protected static $review_moderator_id;

	/**
	 * User with the `review` type's `edit_others_reviews` primitive only.
	 *
	 * @var int
	 */
	protected static $review_editor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		self::$global_moderator_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$global_moderator_id )->add_cap( 'moderate_comments' );

		self::$review_moderator_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$review_moderator_id )->add_cap( 'moderate_reviews' );

		self::$review_editor_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$review_editor_id )->add_cap( 'edit_others_reviews' );
	}

	public function set_up() {
		parent::set_up();

		// A comment type with an independent capability model.
		register_comment_type( 'review', array( 'capability_type' => 'review' ) );
	}

	public function tear_down() {
		unregister_comment_type( 'review' );

		parent::tear_down();
	}

	/**
	 * Creates a comment of the given type on the shared post.
	 *
	 * @param string $comment_type Comment type slug.
	 * @return int The new comment ID.
	 */
	private function make_comment( $comment_type ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_type'     => $comment_type,
				'comment_approved' => '1',
			)
		);
	}

	/**
	 * Dispatches a REST request to update a comment's content.
	 *
	 * @param int $comment_id Comment to update.
	 * @return WP_REST_Response
	 */
	private function dispatch_update( $comment_id ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/comments/' . $comment_id );
		$request->set_param( 'content', 'Updated content' );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Dispatches a REST request to force-delete a comment.
	 *
	 * @param int $comment_id Comment to delete.
	 * @return WP_REST_Response
	 */
	private function dispatch_delete( $comment_id ) {
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/comments/' . $comment_id );
		$request->set_param( 'force', true );

		return rest_get_server()->dispatch( $request );
	}

	/*
	 * Independent capability model.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_review_moderator_can_update_review_comment() {
		wp_set_current_user( self::$review_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'review' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_review_moderator_can_delete_review_comment() {
		wp_set_current_user( self::$review_moderator_id );

		$response = $this->dispatch_delete( $this->make_comment( 'review' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * The type's `edit_others_reviews` primitive also grants moderation through the
	 * `edit_comment` fallback in `check_edit_permission()`.
	 *
	 * @ticket 35214
	 */
	public function test_review_editor_can_update_review_comment() {
		wp_set_current_user( self::$review_editor_id );

		$response = $this->dispatch_update( $this->make_comment( 'review' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * A global `moderate_comments` moderator has no power over an independent type.
	 *
	 * @ticket 35214
	 */
	public function test_global_moderator_cannot_update_review_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'review' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_cannot_delete_review_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_delete( $this->make_comment( 'review' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A review moderator has no power over a default-model comment.
	 *
	 * @ticket 35214
	 */
	public function test_review_moderator_cannot_update_default_comment() {
		wp_set_current_user( self::$review_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'comment' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/*
	 * Default capability model: unchanged from historical behavior.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_can_update_default_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'comment' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_can_delete_default_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_delete( $this->make_comment( 'comment' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * The built-in pingback type uses the default model and is unaffected.
	 *
	 * @ticket 35214
	 */
	public function test_global_moderator_can_update_builtin_ping_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'pingback' ) );

		$this->assertSame( 200, $response->get_status() );
	}
}
