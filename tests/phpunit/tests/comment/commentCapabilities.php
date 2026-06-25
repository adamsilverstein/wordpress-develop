<?php

/**
 * Tests for capability enforcement of registered comment types via map_meta_cap().
 *
 * @group comment
 * @group capabilities
 *
 * @covers ::map_meta_cap
 */
class Tests_Comment_CommentCapabilities extends WP_UnitTestCase {

	/**
	 * Post the comments are attached to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Administrator user ID (has moderate_comments and can edit the post).
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID (no comment capabilities).
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$post_id       = $factory->post->create( array( 'post_author' => self::$admin_id ) );
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
	 * Creates a comment of the given type, optionally attributed to a user.
	 *
	 * @param string $comment_type Comment type slug.
	 * @param int    $user_id      Authoring user ID. Default 0 (anonymous).
	 * @return int The new comment ID.
	 */
	private function make_comment( $comment_type = 'comment', $user_id = 0 ) {
		return self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => $comment_type,
				'user_id'         => $user_id,
			)
		);
	}

	/*
	 * Default capability model: behavior must match historical core exactly.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_default_edit_comment_follows_parent_post() {
		$comment_id = $this->make_comment();

		$this->assertTrue( user_can( self::$admin_id, 'edit_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'edit_comment', $comment_id ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_orphaned_comment_edit_falls_back_to_edit_posts() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => 0,
				'comment_type'    => 'comment',
			)
		);

		$this->assertTrue( user_can( self::$admin_id, 'edit_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'edit_comment', $comment_id ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_default_moderate_comment_requires_moderate_comments() {
		$comment_id = $this->make_comment();

		$this->assertTrue( user_can( self::$admin_id, 'moderate_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'moderate_comment', $comment_id ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_default_delete_comment_follows_parent_post() {
		$comment_id = $this->make_comment();

		$this->assertTrue( user_can( self::$admin_id, 'delete_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'delete_comment', $comment_id ) );
	}

	/**
	 * @ticket 35214
	 *
	 * @expectedIncorrectUsage map_meta_cap
	 */
	public function test_moderate_comment_without_comment_id_is_denied() {
		$this->assertFalse( user_can( self::$admin_id, 'moderate_comment' ) );
	}

	/*
	 * Independent capability model: gated by the type's own primitives, with no
	 * silent fallback to the default comment capabilities.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_independent_type_moderation_requires_its_own_primitive() {
		$review_id  = $this->make_comment( 'review' );
		$comment_id = $this->make_comment( 'comment' );

		$moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$moderator->add_cap( 'moderate_reviews' );

		// The review moderator can moderate reviews but not default comments.
		$this->assertTrue( user_can( $moderator->ID, 'moderate_comment', $review_id ) );
		$this->assertFalse( user_can( $moderator->ID, 'moderate_comment', $comment_id ) );

		// An administrator has moderate_comments but not moderate_reviews: the inverse.
		$this->assertTrue( user_can( self::$admin_id, 'moderate_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$admin_id, 'moderate_comment', $review_id ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_independent_type_edit_distinguishes_own_and_others() {
		$author = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$author->add_cap( 'edit_reviews' );

		$own_review    = $this->make_comment( 'review', $author->ID );
		$others_review = $this->make_comment( 'review', self::$admin_id );

		// edit_reviews grants editing one's own review comments only.
		$this->assertTrue( user_can( $author->ID, 'edit_comment', $own_review ) );
		$this->assertFalse( user_can( $author->ID, 'edit_comment', $others_review ) );

		// edit_others_reviews grants editing any review comment.
		$editor = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$editor->add_cap( 'edit_others_reviews' );

		$this->assertTrue( user_can( $editor->ID, 'edit_comment', $others_review ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_independent_type_delete_requires_its_own_primitive() {
		$review_id = $this->make_comment( 'review' );

		$deleter = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$deleter->add_cap( 'delete_reviews' );

		$this->assertTrue( user_can( $deleter->ID, 'delete_comment', $review_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'delete_comment', $review_id ) );
	}
}
