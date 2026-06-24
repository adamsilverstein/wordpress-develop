<?php

/**
 * Tests for the WP_Comment_Type class.
 *
 * @group comment
 *
 * @coversDefaultClass WP_Comment_Type
 */
class Tests_Comment_WpCommentType extends WP_UnitTestCase {

	/**
	 * @ticket 35214
	 *
	 * @covers ::__construct
	 * @covers ::set_props
	 */
	public function test_instance_defaults() {
		$comment_type = new WP_Comment_Type( 'foo' );

		$this->assertSame( 'foo', $comment_type->name );
		$this->assertTrue( $comment_type->public );
		$this->assertFalse( $comment_type->internal );
		$this->assertFalse( $comment_type->_builtin );
		$this->assertTrue( $comment_type->show_ui );
		$this->assertFalse( $comment_type->hierarchical );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_set_props_overrides_defaults() {
		$comment_type = new WP_Comment_Type(
			'foo',
			array(
				'public'      => false,
				'internal'    => true,
				'description' => 'A test comment type.',
			)
		);

		$this->assertFalse( $comment_type->public );
		$this->assertTrue( $comment_type->internal );
		$this->assertSame( 'A test comment type.', $comment_type->description );
		// show_ui follows public when not explicitly set.
		$this->assertFalse( $comment_type->show_ui );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_register_comment_type_args_filter() {
		$filter = static function ( $args ) {
			$args['public'] = false;
			return $args;
		};

		add_filter( 'register_comment_type_args', $filter );
		$comment_type = new WP_Comment_Type( 'foo' );
		remove_filter( 'register_comment_type_args', $filter );

		$this->assertFalse( $comment_type->public );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_register_specific_comment_type_args_filter() {
		$filter = static function ( $args ) {
			$args['description'] = 'Filtered description.';
			return $args;
		};

		add_filter( 'register_foo_comment_type_args', $filter );
		$comment_type = new WP_Comment_Type( 'foo' );
		$other_type   = new WP_Comment_Type( 'bar' );
		remove_filter( 'register_foo_comment_type_args', $filter );

		$this->assertSame( 'Filtered description.', $comment_type->description );
		$this->assertSame( '', $other_type->description );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_default_labels
	 * @covers ::reset_default_labels
	 */
	public function test_get_default_labels_returns_expected_defaults() {
		WP_Comment_Type::reset_default_labels();

		$labels = WP_Comment_Type::get_default_labels();

		$this->assertSame( 'Comments', $labels['name'][0] );
		$this->assertSame( 'Comment', $labels['singular_name'][0] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::get_default_labels
	 * @covers ::reset_default_labels
	 */
	public function test_reset_default_labels_clears_cache() {
		// Prime the cache, then mutate the returned (by-value) array.
		WP_Comment_Type::get_default_labels();

		WP_Comment_Type::reset_default_labels();

		// A fresh call rebuilds the defaults from translation functions.
		$labels = WP_Comment_Type::get_default_labels();
		$this->assertSame( 'Comments', $labels['name'][0] );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_default_capability_type_and_cap_object() {
		$comment_type = new WP_Comment_Type( 'foo' );

		$this->assertSame( 'comment', $comment_type->capability_type );
		$this->assertIsObject( $comment_type->cap );
		$this->assertSame( 'edit_comment', $comment_type->cap->edit_comment );
		$this->assertSame( 'moderate_comments', $comment_type->cap->moderate_comments );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_custom_capability_type_builds_cap_object() {
		$comment_type = new WP_Comment_Type( 'foo', array( 'capability_type' => 'review' ) );

		$this->assertSame( 'review', $comment_type->capability_type );
		$this->assertSame( 'edit_review', $comment_type->cap->edit_comment );
		$this->assertSame( 'edit_reviews', $comment_type->cap->edit_comments );
		$this->assertSame( 'moderate_reviews', $comment_type->cap->moderate_comments );
	}

	/**
	 * An array capability type allows an explicit plural and is collapsed to its singular base.
	 *
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_array_capability_type_uses_explicit_plural() {
		$comment_type = new WP_Comment_Type( 'foo', array( 'capability_type' => array( 'story', 'stories' ) ) );

		$this->assertSame( 'story', $comment_type->capability_type );
		$this->assertSame( 'edit_story', $comment_type->cap->edit_comment );
		$this->assertSame( 'edit_stories', $comment_type->cap->edit_comments );
	}

	/**
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_capabilities_argument_overrides_generated_caps() {
		$comment_type = new WP_Comment_Type(
			'foo',
			array(
				'capability_type' => 'review',
				'capabilities'    => array(
					'moderate_comments' => 'manage_reviews',
				),
			)
		);

		$this->assertSame( 'manage_reviews', $comment_type->cap->moderate_comments );
		// Non-overridden caps are still generated from the capability type.
		$this->assertSame( 'edit_reviews', $comment_type->cap->edit_comments );
	}

	/**
	 * The input `capabilities` array is consumed and not kept as a public property.
	 *
	 * @ticket 35214
	 *
	 * @covers ::set_props
	 */
	public function test_capabilities_input_is_not_retained_as_property() {
		$comment_type = new WP_Comment_Type( 'foo', array( 'capabilities' => array( 'edit_comments' => 'x' ) ) );

		$this->assertObjectNotHasProperty( 'capabilities', $comment_type );
	}
}
