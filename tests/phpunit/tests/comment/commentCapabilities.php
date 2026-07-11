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

	/**
	 * A comment stored with the legacy empty string type uses the default model
	 * for all three meta capabilities (the null type object fallback).
	 *
	 * @ticket 35214
	 */
	public function test_legacy_empty_type_uses_default_model_for_all_meta_caps() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => '',
			)
		);

		foreach ( array( 'edit_comment', 'delete_comment', 'moderate_comment' ) as $meta_cap ) {
			$this->assertTrue( user_can( self::$admin_id, $meta_cap, $comment_id ), "Admin should have {$meta_cap} on a legacy comment." );
			$this->assertFalse( user_can( self::$subscriber_id, $meta_cap, $comment_id ), "Subscriber should not have {$meta_cap} on a legacy comment." );
		}
	}

	/**
	 * The independent model never consults the parent post: an orphaned comment
	 * of an independent type is fully controlled by the type's primitives.
	 *
	 * @ticket 35214
	 */
	public function test_independent_type_ignores_parent_post_entirely() {
		$orphan_review = self::factory()->comment->create(
			array(
				'comment_post_ID' => 0,
				'comment_type'    => 'review',
			)
		);

		$power_user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$power_user->add_cap( 'edit_others_reviews' );
		$power_user->add_cap( 'delete_reviews' );
		$power_user->add_cap( 'moderate_reviews' );

		$this->assertTrue( user_can( $power_user->ID, 'edit_comment', $orphan_review ) );
		$this->assertTrue( user_can( $power_user->ID, 'delete_comment', $orphan_review ) );
		$this->assertTrue( user_can( $power_user->ID, 'moderate_comment', $orphan_review ) );

		// The edit_posts fallback for orphaned default comments does not apply.
		$this->assertFalse( user_can( self::$admin_id, 'edit_comment', $orphan_review ) );
	}

	/**
	 * An invalid comment ID maps to do_not_allow for all three meta capabilities.
	 *
	 * @ticket 35214
	 */
	public function test_invalid_comment_id_is_denied_for_all_meta_caps() {
		$invalid_id = PHP_INT_MAX;

		foreach ( array( 'edit_comment', 'delete_comment', 'moderate_comment' ) as $meta_cap ) {
			$this->assertSame( array( 'do_not_allow' ), map_meta_cap( $meta_cap, self::$admin_id, $invalid_id ), "{$meta_cap} should map to do_not_allow." );
			$this->assertFalse( user_can( self::$admin_id, $meta_cap, $invalid_id ), "Admin should not have {$meta_cap} for an invalid comment." );
		}

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
			$this->assertFalse( user_can( self::$admin_id, 'edit_comment', $invalid_id ), 'do_not_allow should deny even super admins.' );
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * @ticket 35214
	 *
	 * @expectedIncorrectUsage map_meta_cap
	 */
	public function test_delete_comment_without_comment_id_is_denied() {
		$this->assertFalse( user_can( self::$admin_id, 'delete_comment' ) );
	}

	/**
	 * An anonymous comment (user_id 0) never matches the "own comment" branch
	 * of the independent model.
	 *
	 * @ticket 35214
	 */
	public function test_anonymous_independent_comment_never_matches_own() {
		$anon_review = $this->make_comment( 'review', 0 );

		$author = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$author->add_cap( 'edit_reviews' );

		$this->assertFalse( user_can( $author->ID, 'edit_comment', $anon_review ), 'edit_reviews alone should not grant editing an anonymous comment.' );

		$editor = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$editor->add_cap( 'edit_others_reviews' );

		$this->assertTrue( user_can( $editor->ID, 'edit_comment', $anon_review ), 'edit_others_reviews should grant editing an anonymous comment.' );
	}

	/**
	 * An array capability_type supplies the plural base enforced by map_meta_cap().
	 *
	 * @ticket 35214
	 */
	public function test_array_capability_type_is_enforced_end_to_end() {
		register_comment_type( 'story_note', array( 'capability_type' => array( 'story', 'stories' ) ) );

		$story_id = $this->make_comment( 'story_note', self::$admin_id );

		$editor = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$editor->add_cap( 'edit_others_stories' );

		$this->assertTrue( user_can( $editor->ID, 'edit_comment', $story_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'edit_comment', $story_id ) );

		$moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$moderator->add_cap( 'moderate_stories' );

		$this->assertTrue( user_can( $moderator->ID, 'moderate_comment', $story_id ) );
		$this->assertFalse( user_can( self::$admin_id, 'moderate_comment', $story_id ) );
	}

	/**
	 * Overriding a single meta capability flips only that action to the
	 * independent model; the other actions keep the default model.
	 *
	 * This also pins the documented footgun: with the default capability_type,
	 * the flipped action is gated by generated primitives no default role has.
	 *
	 * @ticket 35214
	 */
	public function test_single_capability_override_flips_only_that_action() {
		register_comment_type(
			'annotation',
			array(
				'capabilities' => array( 'edit_comment' => 'edit_annotation' ),
			)
		);

		$annotation_id = $this->make_comment( 'annotation' );

		// Editing is now independent, gated by 'edit_others_comments' - which even admins lack.
		$this->assertFalse( user_can( self::$admin_id, 'edit_comment', $annotation_id ) );

		// Deletion and moderation stay on the default model.
		$this->assertTrue( user_can( self::$admin_id, 'delete_comment', $annotation_id ) );
		$this->assertTrue( user_can( self::$admin_id, 'moderate_comment', $annotation_id ) );
	}

	/**
	 * Unregistering a type reverts its stored comments to the default model
	 * (the plugin-deactivation scenario).
	 *
	 * @ticket 35214
	 */
	public function test_unregistered_type_falls_back_to_default_model() {
		$review_id = $this->make_comment( 'review' );

		$moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$moderator->add_cap( 'moderate_reviews' );

		$this->assertTrue( user_can( $moderator->ID, 'moderate_comment', $review_id ) );
		$this->assertFalse( user_can( self::$admin_id, 'moderate_comment', $review_id ) );

		unregister_comment_type( 'review' );

		$this->assertFalse( user_can( $moderator->ID, 'moderate_comment', $review_id ) );
		$this->assertTrue( user_can( self::$admin_id, 'moderate_comment', $review_id ) );
		$this->assertTrue( user_can( self::$admin_id, 'edit_comment', $review_id ) );
	}

	/**
	 * The default model still consults a trashed parent post normally.
	 *
	 * @ticket 35214
	 */
	public function test_default_model_still_consults_trashed_parent_post() {
		$post_id    = self::factory()->post->create( array( 'post_author' => self::$admin_id ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'comment',
			)
		);

		wp_trash_post( $post_id );

		$this->assertTrue( user_can( self::$admin_id, 'edit_comment', $comment_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'edit_comment', $comment_id ) );
	}

	/*
	 * Custom meta capability names: current_user_can( 'edit_review', $comment_id )
	 * translates to the generic meta capability, mirroring post type meta caps.
	 */

	/**
	 * @ticket 35214
	 */
	public function test_custom_meta_cap_name_translates_via_map_meta_cap() {
		$review_id = $this->make_comment( 'review' );

		$moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$moderator->add_cap( 'moderate_reviews' );

		$this->assertTrue( user_can( $moderator->ID, 'moderate_review', $review_id ) );
		$this->assertFalse( user_can( self::$subscriber_id, 'moderate_review', $review_id ) );

		$this->assertSame(
			map_meta_cap( 'moderate_comment', $moderator->ID, $review_id ),
			map_meta_cap( 'moderate_review', $moderator->ID, $review_id ),
			'The custom name should map exactly like the generic name.'
		);
	}

	/**
	 * @ticket 35214
	 */
	public function test_custom_edit_meta_cap_name_respects_own_others_split() {
		$author = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$author->add_cap( 'edit_reviews' );

		$own_review    = $this->make_comment( 'review', $author->ID );
		$others_review = $this->make_comment( 'review', self::$admin_id );

		$this->assertTrue( user_can( $author->ID, 'edit_review', $own_review ) );
		$this->assertFalse( user_can( $author->ID, 'edit_review', $others_review ) );
	}

	/**
	 * Unregistering a type removes its custom meta capability translations.
	 *
	 * @ticket 35214
	 */
	public function test_unregistering_type_removes_custom_meta_cap_translation() {
		$review_id = $this->make_comment( 'review' );

		$moderator = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$moderator->add_cap( 'moderate_reviews' );

		$this->assertTrue( user_can( $moderator->ID, 'moderate_review', $review_id ) );

		unregister_comment_type( 'review' );

		$this->assertFalse( user_can( $moderator->ID, 'moderate_review', $review_id ), 'The translation should be removed with the type.' );
	}

	/**
	 * A comment type cannot claim a meta capability name already registered
	 * for a post type; the post type translation is preserved.
	 *
	 * @ticket 35214
	 *
	 * @expectedIncorrectUsage register_comment_type
	 */
	public function test_comment_type_cannot_claim_post_type_meta_cap_names() {
		global $post_type_meta_caps, $comment_type_meta_caps;

		register_post_type(
			'book',
			array(
				'capability_type' => 'book',
				'map_meta_cap'    => true,
			)
		);
		register_comment_type( 'book_note', array( 'capability_type' => 'book' ) );

		$this->assertSame( 'edit_post', $post_type_meta_caps['edit_book'], 'The post type translation should be preserved.' );
		$this->assertArrayNotHasKey( 'edit_book', (array) $comment_type_meta_caps );
		$this->assertArrayNotHasKey( 'delete_book', (array) $comment_type_meta_caps );

		// The non-colliding moderation capability is still registered.
		$this->assertSame( 'moderate_comment', $comment_type_meta_caps['moderate_book'] );
	}
}
