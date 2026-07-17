<?php

/**
 * @group comment
 *
 * @covers ::separate_comments
 */
class Tests_Comment_SeparateComments extends WP_UnitTestCase {

	/**
	 * Comment post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Builds a comment of the given type on the shared post.
	 *
	 * @param string $comment_type Comment type slug.
	 * @return WP_Comment The created comment object.
	 */
	private function make_comment( $comment_type ) {
		return get_comment(
			self::factory()->comment->create(
				array(
					'comment_post_ID' => self::$post_id,
					'comment_type'    => $comment_type,
				)
			)
		);
	}

	/**
	 * The standard buckets are always present, even when empty.
	 *
	 * @ticket 35214
	 */
	public function test_default_buckets_are_always_present() {
		$comments  = array();
		$separated = separate_comments( $comments );

		$this->assertArrayHasKey( 'comment', $separated );
		$this->assertArrayHasKey( 'trackback', $separated );
		$this->assertArrayHasKey( 'pingback', $separated );
		$this->assertArrayHasKey( 'pings', $separated );
	}

	/**
	 * Built-in pingbacks and trackbacks are grouped into the 'pings' bucket.
	 *
	 * @ticket 35214
	 */
	public function test_built_in_pings_are_grouped_into_pings_bucket() {
		$comments = array(
			$this->make_comment( 'comment' ),
			$this->make_comment( 'pingback' ),
			$this->make_comment( 'trackback' ),
		);

		$separated = separate_comments( $comments );

		$this->assertCount( 1, $separated['comment'] );
		$this->assertCount( 1, $separated['pingback'] );
		$this->assertCount( 1, $separated['trackback'] );
		$this->assertCount( 2, $separated['pings'] );
	}

	/**
	 * A registered comment type marked as a ping is grouped into 'pings'.
	 *
	 * @ticket 35214
	 */
	public function test_registered_ping_type_is_grouped_into_pings_bucket() {
		register_comment_type( 'webmention', array( 'is_ping' => true ) );

		$comments = array( $this->make_comment( 'webmention' ) );

		$separated = separate_comments( $comments );

		$this->assertCount( 1, $separated['webmention'] );
		$this->assertCount( 1, $separated['pings'] );
	}

	/**
	 * A registered comment type that is not a ping stays out of the 'pings' bucket.
	 *
	 * @ticket 35214
	 */
	public function test_non_ping_type_is_not_grouped_into_pings_bucket() {
		register_comment_type( 'review' );

		$comments = array( $this->make_comment( 'review' ) );

		$separated = separate_comments( $comments );

		$this->assertCount( 1, $separated['review'] );
		$this->assertCount( 0, $separated['pings'] );
	}

	/**
	 * An unregistered comment type gets its own bucket and stays out of 'pings',
	 * matching the previous hard-coded behavior.
	 *
	 * @ticket 35214
	 */
	public function test_unregistered_type_gets_own_bucket_and_is_not_a_ping() {
		$comments = array( $this->make_comment( 'webmention' ) );

		$separated = separate_comments( $comments );

		$this->assertCount( 1, $separated['webmention'] );
		$this->assertCount( 0, $separated['pings'] );
	}

	/**
	 * A comment stored with the legacy empty string type lands in the 'comment' bucket.
	 *
	 * @ticket 35214
	 */
	public function test_legacy_empty_type_lands_in_comment_bucket() {
		$comments = array( $this->make_comment( '' ) );

		$separated = separate_comments( $comments );

		$this->assertCount( 1, $separated['comment'] );
		$this->assertCount( 0, $separated['pings'] );
	}
}
