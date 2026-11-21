<?php
/**
 * Unit tests covering WP_REST_Comment_Types_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 * @group comment
 *
 * @covers WP_REST_Comment_Types_Controller
 */
class WP_Test_REST_Comment_Types_Controller extends WP_Test_REST_Controller_Testcase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	/**
	 * @ticket 35214
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/comment-types', $routes );
		$this->assertArrayHasKey( '/wp/v2/comment-types/(?P<type>[\w-]+)', $routes );
	}

	/**
	 * @ticket 35214
	 */
	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_items() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );

		$data          = $response->get_data();
		$comment_types = get_comment_types( array( 'show_in_rest' => true ), 'objects' );
		$this->assertCount( count( $comment_types ), $data );
		$this->assertSame( $comment_types['comment']->name, $data['comment']['slug'] );
		$this->check_comment_type_obj( 'view', $comment_types['comment'], $data['comment'], $data['comment']['_links'] );
		$this->assertArrayHasKey( 'pingback', $data );
		$this->assertArrayHasKey( 'trackback', $data );
	}

	/**
	 * The internal `note` type is not exposed (show_in_rest defaults to public, which is false).
	 *
	 * @ticket 35214
	 */
	public function test_get_items_excludes_non_rest_types() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'note', $data );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_items_includes_registered_custom_type() {
		register_comment_type( 'review', array( 'public' => true ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'review', $data );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_items_excludes_custom_type_opted_out_of_rest() {
		register_comment_type(
			'review',
			array(
				'public'       => true,
				'show_in_rest' => false,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'review', $data );
	}

	/**
	 * @ticket 35214
	 *
	 * @dataProvider data_readable_http_methods
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_invalid_permission_for_context( $method ) {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( $method, '/wp/v2/comment-types' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_view', $response, 401 );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public static function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_comment_type_object_response( 'view', $response );
	}

	/**
	 * @ticket 35214
	 *
	 * @dataProvider data_readable_http_methods
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_item_invalid_type( $method ) {
		$request  = new WP_REST_Request( $method, '/wp/v2/comment-types/invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_type_invalid', $response, 404 );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item_non_rest_type_is_not_readable() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/note' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_type', $response, 401 );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item_edit_context_requires_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	/**
	 * An anonymous edit-context request is rejected with 401 before the type is resolved.
	 *
	 * This deliberately diverges from the post types controller, which resolves the type
	 * first and returns 404 for unknown types: here the permission callback runs before
	 * the route callback, so authentication is always demanded first.
	 *
	 * @ticket 35214
	 */
	public function test_get_item_edit_context_as_anonymous_returns_401() {
		wp_set_current_user( 0 );

		// A known type.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );

		// An unknown type: still 401, not 404.
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/unknown' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item_with_fields_limits_response_keys() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( '_fields', 'slug' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( array( 'slug' ), array_keys( $response->get_data() ) );
	}

	/**
	 * @ticket 35214
	 */
	public function test_additional_field_registered_for_comment_type_is_included() {
		register_rest_field(
			'comment-type',
			'my_custom_field',
			array(
				'get_callback' => static function () {
					return 'custom value';
				},
				'schema'       => array( 'type' => 'string' ),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_field', $data );
		$this->assertSame( 'custom value', $data['my_custom_field'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	/**
	 * In the edit context, labels are returned for every exposed type, and the internal
	 * 'note' type stays hidden even from users who can moderate comments.
	 *
	 * @ticket 35214
	 */
	public function test_get_items_edit_context_returns_labels_and_still_excludes_note() {
		wp_set_current_user( self::$admin_id );
		register_comment_type(
			'review',
			array(
				'label'  => 'Reviews',
				'public' => true,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'review', $data );
		$this->assertArrayNotHasKey( 'note', $data );

		foreach ( $data as $slug => $item ) {
			$this->assertArrayHasKey( 'labels', $item, "Labels missing for '{$slug}' in edit context." );
		}
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item_edit_context_returns_labels() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_comment_type_object_response( 'edit', $response );
	}

	/**
	 * @ticket 35214
	 */
	public function test_create_item() {
		/** Comment types can't be created */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_update_item() {
		/** Comment types can't be updated */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_delete_item() {
		/** Comment types can't be deleted */
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_prepare_item() {
		$obj      = get_comment_type_object( 'comment' );
		$endpoint = new WP_REST_Comment_Types_Controller();
		$request  = new WP_REST_Request();
		$request->set_param( 'context', 'view' );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->check_comment_type_obj( 'view', $obj, $response->get_data(), $response->get_links() );
	}

	/**
	 * @ticket 35214
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/comment-types' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 4, $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'labels', $properties );
	}

	/**
	 * The `api.w.org/items` link should point at the type-filtered comments collection.
	 *
	 * @ticket 35214
	 */
	public function test_get_item_links_to_filtered_comments_collection() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$links    = test_rest_expand_compact_links( $response->get_links() );

		$this->assertArrayHasKey( 'https://api.w.org/items', $links );
		$this->assertSame(
			add_query_arg( 'type', 'comment', rest_url( 'wp/v2/comments' ) ),
			$links['https://api.w.org/items'][0]['href']
		);
	}

	/**
	 * A HEAD request to the collection should succeed without preparing any item data.
	 *
	 * @ticket 56481
	 */
	public function test_get_items_with_head_request_should_not_prepare_comment_types_data() {
		$request   = new WP_REST_Request( 'HEAD', '/wp/v2/comment-types' );
		$hook_name = 'rest_prepare_comment_type';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method The HTTP method to use.
	 */
	public function test_get_item_should_allow_adding_headers_via_filter( $method ) {
		$request = new WP_REST_Request( $method, '/wp/v2/comment-types/comment' );

		$hook_name = 'rest_prepare_comment_type';
		$filter    = new MockAction();
		$callback  = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$header_filter = new class() {
			public static function add_custom_header( $response ) {
				$response->header( 'X-Test-Header', 'Test' );

				return $response;
			}
		};
		add_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );
		remove_filter( $hook_name, array( $header_filter, 'add_custom_header' ) );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
		$this->assertSame( 1, $filter->get_call_count(), 'The "' . $hook_name . '" filter should be called once.' );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-Test-Header', $headers, 'The "X-Test-Header" header should be present in the response.' );
		$this->assertSame( 'Test', $headers['X-Test-Header'], 'The "X-Test-Header" header value should be equal to "Test".' );
		if ( 'HEAD' !== $method ) {
			return null;
		}
		$this->assertSame( array(), $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}

	/**
	 * @dataProvider data_head_request_with_specified_fields_returns_success_response
	 * @ticket 56481
	 *
	 * @param string $path The path to test.
	 */
	public function test_head_request_with_specified_fields_returns_success_response( $path ) {
		$request = new WP_REST_Request( 'HEAD', $path );
		$request->set_param( '_fields', 'slug' );
		$server   = rest_get_server();
		$response = $server->dispatch( $request );
		add_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10, 3 );
		$response = apply_filters( 'rest_post_dispatch', $response, $server, $request );
		remove_filter( 'rest_post_dispatch', 'rest_filter_response_fields', 10 );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );
	}

	/**
	 * Data provider intended to provide paths for testing HEAD requests.
	 *
	 * @return array
	 */
	public static function data_head_request_with_specified_fields_returns_success_response() {
		return array(
			'get_item request'  => array( '/wp/v2/comment-types/comment' ),
			'get_items request' => array( '/wp/v2/comment-types' ),
		);
	}

	protected function check_comment_type_obj( $context, $comment_type_obj, $data, $links ) {
		$this->assertSame( $comment_type_obj->label, $data['name'] );
		$this->assertSame( $comment_type_obj->name, $data['slug'] );
		$this->assertSame( $comment_type_obj->description, $data['description'] );

		$links = test_rest_expand_compact_links( $links );
		$this->assertSame( rest_url( 'wp/v2/comment-types' ), $links['collection'][0]['href'] );
		$this->assertArrayHasKey( 'https://api.w.org/items', $links );

		if ( 'edit' === $context ) {
			$this->assertSame( (array) $comment_type_obj->labels, (array) $data['labels'] );
		} else {
			$this->assertArrayNotHasKey( 'labels', $data );
		}
	}

	protected function check_comment_type_object_response( $context, $response, $comment_type = 'comment' ) {
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$obj  = get_comment_type_object( $comment_type );
		$this->check_comment_type_obj( $context, $obj, $data, $response->get_links() );
	}
}
