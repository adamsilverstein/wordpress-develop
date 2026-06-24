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
	 * Ensures any comment type registered during a test is cleaned up.
	 */
	public function tear_down() {
		global $wp_comment_types;

		foreach ( array_keys( $wp_comment_types ) as $comment_type ) {
			if ( ! $wp_comment_types[ $comment_type ]->_builtin ) {
				unset( $wp_comment_types[ $comment_type ] );
			}
		}

		parent::tear_down();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/comment-types', $routes );
		$this->assertArrayHasKey( '/wp/v2/comment-types/(?P<type>[\w-]+)', $routes );
	}

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
	 */
	public function test_get_items_excludes_non_rest_types() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'note', $data );
	}

	public function test_get_items_includes_registered_custom_type() {
		register_comment_type( 'review', array( 'public' => true ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'review', $data );
	}

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

	public function test_get_item() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_comment_type_object_response( 'view', $response );
	}

	public function test_get_item_invalid_type() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_type_invalid', $response, 404 );
	}

	public function test_get_item_non_rest_type_is_not_readable() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/comment-types/note' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read_type', $response, 401 );
	}

	public function test_get_item_edit_context_requires_permission() {
		wp_set_current_user( self::$subscriber_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 403 );
	}

	public function test_get_item_edit_context_returns_labels() {
		wp_set_current_user( self::$admin_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/comment-types/comment' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_comment_type_object_response( 'edit', $response );
	}

	public function test_create_item() {
		/** Comment types can't be created */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/comment-types' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_update_item() {
		/** Comment types can't be updated */
		$request  = new WP_REST_Request( 'POST', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_delete_item() {
		/** Comment types can't be deleted */
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/comment-types/comment' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_prepare_item() {
		$obj      = get_comment_type_object( 'comment' );
		$endpoint = new WP_REST_Comment_Types_Controller();
		$request  = new WP_REST_Request();
		$request->set_param( 'context', 'view' );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->check_comment_type_obj( 'view', $obj, $response->get_data(), $response->get_links() );
	}

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
