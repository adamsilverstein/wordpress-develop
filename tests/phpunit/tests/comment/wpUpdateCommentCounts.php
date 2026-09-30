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

		$recalculated = wp_update_comment_counts();

		$this->assertSame( 2, $recalculated );
		$this->assertSame( '1', get_comments_number( $post_a ) );
		$this->assertSame( '1', get_comments_number( $post_b ) );
	}

	/**
	 * Nonexistent, zero, and negative post IDs are skipped, not recounted.
	 *
	 * @ticket 65537
	 */
	public function test_invalid_post_ids_are_skipped() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);
		$this->set_stored_count( $post_id, 99 );

		// A negative ID must not be silently coerced into a valid one.
		$this->assertSame( 0, wp_update_comment_counts( array( -$post_id, 0, PHP_INT_MAX ) ) );
		$this->assertSame( '99', get_comments_number( $post_id ), 'The negated ID should not recount the positive post.' );
	}

	/**
	 * An explicit ID for a post with no remaining comment rows forces a stale
	 * stored count back to zero.
	 *
	 * @ticket 65537
	 */
	public function test_explicit_id_resets_stale_count_on_commentless_post() {
		$post_id = self::factory()->post->create();
		$this->set_stored_count( $post_id, 5 );

		$this->assertSame( 1, wp_update_comment_counts( $post_id ) );
		$this->assertSame( '0', get_comments_number( $post_id ) );
	}

	/**
	 * The null path also visits posts with a nonzero stored count but no
	 * remaining comment rows, which the comments table alone cannot reveal.
	 *
	 * @ticket 65537
	 */
	public function test_null_path_resets_stale_count_on_commentless_post() {
		$post_id = self::factory()->post->create();
		$this->set_stored_count( $post_id, 5 );

		$recalculated = wp_update_comment_counts();

		$this->assertSame( 1, $recalculated );
		$this->assertSame( '0', get_comments_number( $post_id ) );
	}

	/**
	 * Recounting bumps the comment last_changed key so cached query results
	 * from before the excluded set changed are invalidated.
	 *
	 * @ticket 65537
	 */
	public function test_recount_invalidates_comment_query_cache() {
		$post_id = self::factory()->post->create();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			)
		);

		$before = wp_cache_get_last_changed( 'comment' );

		wp_update_comment_counts( $post_id );

		$this->assertNotSame( $before, wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * A call with nothing to recount should not flush every cached comment query.
	 *
	 * @ticket 65537
	 *
	 * @dataProvider data_no_op_recount_arguments
	 *
	 * @param int[] $post_ids Post IDs to pass to wp_update_comment_counts().
	 */
	public function test_no_op_recount_leaves_the_comment_query_cache_alone( array $post_ids ) {
		$before = wp_cache_get_last_changed( 'comment' );

		$this->assertSame( 0, wp_update_comment_counts( $post_ids ) );
		$this->assertSame( $before, wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * Data provider for test_no_op_recount_leaves_the_comment_query_cache_alone().
	 *
	 * @return array<string, array<int[]>>
	 */
	public function data_no_op_recount_arguments(): array {
		return array(
			'empty list'       => array( array() ),
			'only invalid IDs' => array( array( 0, -1 ) ),
		);
	}

	/**
	 * The keyset loop has to advance across batches, and a post that has both comments
	 * and a stale nonzero count appears in both arms of the union.
	 *
	 * @ticket 65537
	 */
	public function test_recount_crosses_batch_boundaries() {
		$post_ids = self::factory()->post->create_many( 5 );

		foreach ( $post_ids as $post_id ) {
			self::factory()->comment->create(
				array(
					'comment_post_ID'  => $post_id,
					'comment_approved' => 1,
				)
			);

			// Stale count, so each post is returned by both union arms.
			$this->set_stored_count( $post_id, 7 );
		}

		add_filter(
			'wp_update_comment_counts_batch_size',
			static function () {
				return 2;
			}
		);

		$this->assertSame(
			5,
			wp_update_comment_counts(),
			'Every post should be visited exactly once across the batches.'
		);

		foreach ( $post_ids as $post_id ) {
			$this->assertSame( '1', get_comments_number( $post_id ) );
		}
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
