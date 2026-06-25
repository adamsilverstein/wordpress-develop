<?php

/**
 * @group comment
 *
 * @covers ::wp_update_comment_counts
 */
class Tests_Comment_wpUpdateCommentCounts extends WP_UnitTestCase {

	/**
	 * Overwrites a post's stored comment_count to simulate a stale value.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Stored comment count to write.
	 */
	private function set_stored_count( $post_id, $count ) {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'comment_count' => $count ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	}

	/**
	 * @ticket 65537
	 */
	public function test_returns_zero_when_there_is_nothing_to_recalculate() {
		$this->assertSame( 0, wp_update_comment_counts( array() ) );
		$this->assertSame( 0, wp_update_comment_counts( 0 ) );
	}

	/**
	 * @ticket 65537
	 */
	public function test_recalculates_only_the_given_posts() {
		$post_a = self::factory()->post->create();
		$post_b = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_a,
				'comment_approved' => 1,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_b,
				'comment_approved' => 1,
			)
		);

		// Corrupt both stored counts, then recalculate only the first post.
		$this->set_stored_count( $post_a, 99 );
		$this->set_stored_count( $post_b, 99 );

		$recalculated = wp_update_comment_counts( $post_a );

		$this->assertSame( 1, $recalculated );
		$this->assertSame( '1', get_comments_number( $post_a ) );
		$this->assertSame( '99', get_comments_number( $post_b ) );
	}

	/**
	 * @ticket 65537
	 */
	public function test_deduplicates_repeated_post_ids() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);
		$this->set_stored_count( $post_id, 99 );

		$recalculated = wp_update_comment_counts( array( $post_id, $post_id ) );

		$this->assertSame( 1, $recalculated );
		$this->assertSame( '1', get_comments_number( $post_id ) );
	}

	/**
	 * @ticket 65537
	 */
	public function test_null_recalculates_all_posts_with_comments() {
		$post_a = self::factory()->post->create();
		$post_b = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_a,
				'comment_approved' => 1,
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_b,
				'comment_approved' => 1,
			)
		);
		$this->set_stored_count( $post_a, 99 );
		$this->set_stored_count( $post_b, 99 );

		wp_update_comment_counts();

		$this->assertSame( '1', get_comments_number( $post_a ) );
		$this->assertSame( '1', get_comments_number( $post_b ) );
	}

	/**
	 * The headline scenario: a type that joins the excluded set after comments
	 * already exist must not keep inflating a previously stored count.
	 *
	 * @ticket 65537
	 */
	public function test_recalculation_drops_a_newly_excluded_type() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'review',
				'comment_approved' => 1,
			)
		);

		// Baseline: 'review' is counted, so the stored count is 1.
		wp_update_comment_count_now( $post_id );
		$this->assertSame( '1', get_comments_number( $post_id ) );

		// A plugin now excludes 'review'. The stored count stays stale until a recount.
		$filter = static function ( $types ) {
			$types[] = 'review';
			return $types;
		};
		add_filter( 'default_excluded_comment_types', $filter );

		$this->assertSame( '1', get_comments_number( $post_id ) );

		$recalculated = wp_update_comment_counts( $post_id );

		remove_filter( 'default_excluded_comment_types', $filter );

		$this->assertSame( 1, $recalculated );
		$this->assertSame( '0', get_comments_number( $post_id ) );
	}
}
