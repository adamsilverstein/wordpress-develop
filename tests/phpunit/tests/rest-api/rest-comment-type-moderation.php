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
 * @covers WP_REST_Comments_Controller::check_read_permission
 * @covers WP_REST_Comments_Controller::get_item_permissions_check
 */
class Tests_REST_Comment_Type_Moderation extends WP_Test_REST_TestCase {

	/**
	 * Post the comments are attached to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Administrator user ID (moderate_comments plus edit_posts).
	 *
	 * @var int
	 */
	protected static $admin_id;

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
		self::$post_id  = $factory->post->create();
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

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

	/**
	 * Creates a comment of the given type on the shared post.
	 *
	 * @param string $comment_type Comment type slug.
	 * @param int    $post_id      Optional. Post to attach the comment to; 0 for an
	 *                             orphaned comment. Default the shared post.
	 * @return int The new comment ID.
	 */
	private function make_comment( $comment_type, $post_id = null ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => null === $post_id ? self::$post_id : $post_id,
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

	/**
	 * Dispatches a REST request to read a single comment.
	 *
	 * @param int    $comment_id Comment to read.
	 * @param string $context    Optional. Request context. Default 'view'.
	 * @return WP_REST_Response
	 */
	private function dispatch_get( $comment_id, $context = 'view' ) {
		$request = new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id );
		$request->set_param( 'context', $context );

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

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_cannot_delete_review_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_delete( $this->make_comment( 'review' ) );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * A review moderator has no power over a default-model comment.
	 *
	 * @ticket 35214
	 */
	public function test_review_moderator_cannot_update_default_comment() {
		wp_set_current_user( self::$review_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( 'comment' ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
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

	/**
	 * An UNREGISTERED comment type stays fully moderatable by a global moderator.
	 *
	 * This pins the back-compat fallback everything relies on: comments of types
	 * that were never registered (e.g. stored by plugins before registration
	 * existed, or after their plugin is deactivated) resolve to a null type
	 * object and behave exactly like default comments.
	 *
	 * @ticket 35214
	 */
	public function test_global_moderator_retains_control_over_unregistered_type() {
		wp_set_current_user( self::$global_moderator_id );

		$webmention_id = $this->make_comment( 'webmention' );

		$response = $this->dispatch_update( $webmention_id );
		$this->assertSame( 200, $response->get_status(), 'A global moderator should be able to update an unregistered-type comment.' );

		$response = $this->dispatch_delete( $webmention_id );
		$this->assertSame( 200, $response->get_status(), 'A global moderator should be able to delete an unregistered-type comment.' );
	}

	/**
	 * A comment stored with the legacy empty string type behaves like a default comment.
	 *
	 * @ticket 35214
	 */
	public function test_global_moderator_can_update_legacy_empty_type_comment() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_update( $this->make_comment( '' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/*
	 * Single-item edit-context reads (get_item_permissions_check()): the read gate
	 * matches the update/delete gates, whose responses already return edit data.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_review_moderator_can_read_review_comment_in_edit_context() {
		wp_set_current_user( self::$review_moderator_id );

		$response = $this->dispatch_get( $this->make_comment( 'review' ), 'edit' );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_cannot_read_review_comment_in_edit_context() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_get( $this->make_comment( 'review' ), 'edit' );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * @ticket 35214
	 */
	public function test_global_moderator_can_read_default_comment_in_edit_context() {
		wp_set_current_user( self::$global_moderator_id );

		$response = $this->dispatch_get( $this->make_comment( 'comment' ), 'edit' );

		$this->assertSame( 200, $response->get_status() );
	}

	/*
	 * Orphaned comments (comment_post_ID = 0) through check_read_permission().
	 */

	/**
	 * Reading an orphaned default comment requires passing both the orphan gate
	 * (moderate_comment) and the final edit_comment check, which for orphans
	 * falls back to edit_posts - so administrators pass and bare moderators do
	 * not, exactly as before this change.
	 *
	 * @ticket 35214
	 */
	public function test_admin_can_read_orphaned_default_comment() {
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch_get( $this->make_comment( 'comment', 0 ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_subscriber_cannot_read_orphaned_default_comment() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->dispatch_get( $this->make_comment( 'comment', 0 ) );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * Reading an orphaned independent-type comment requires the type's own
	 * capabilities on both checks: its moderation primitive for the orphan gate
	 * and its edit primitives for the final edit_comment check.
	 *
	 * @ticket 35214
	 */
	public function test_review_moderator_with_edit_cap_can_read_orphaned_review_comment() {
		$full_review_moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$full_review_moderator->add_cap( 'moderate_reviews' );
		$full_review_moderator->add_cap( 'edit_others_reviews' );

		wp_set_current_user( $full_review_moderator->ID );

		$response = $this->dispatch_get( $this->make_comment( 'review', 0 ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Even an administrator (global moderate_comments and edit_posts) cannot
	 * read an orphaned comment of an independent type.
	 *
	 * @ticket 35214
	 */
	public function test_admin_cannot_read_orphaned_review_comment() {
		wp_set_current_user( self::$admin_id );

		$response = $this->dispatch_get( $this->make_comment( 'review', 0 ) );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	/**
	 * The `?post=0` collection gate stays on the global primitive: a global
	 * moderator gets the list with independent-type orphans filtered out, while
	 * a type moderator is rejected at the gate. This pins the known, documented
	 * divergence until a per-type collection treatment exists.
	 *
	 * @ticket 35214
	 */
	public function test_orphan_collection_gate_uses_global_primitive() {
		$default_orphan = $this->make_comment( 'comment', 0 );
		$review_orphan  = $this->make_comment( 'review', 0 );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$ids = wp_list_pluck( $response->get_data(), 'id' );
		$this->assertContains( $default_orphan, $ids, 'The default-type orphan should be listed for an administrator.' );
		$this->assertNotContains( $review_orphan, $ids, 'The independent-type orphan should be filtered out for an administrator.' );

		wp_set_current_user( self::$review_moderator_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
		$request->set_param( 'post', 0 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}
}
