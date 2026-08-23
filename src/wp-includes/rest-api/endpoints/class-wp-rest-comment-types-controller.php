<?php
/**
 * REST API: WP_REST_Comment_Types_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

/**
 * Core class to access comment types via the REST API.
 *
 * @since 7.2.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Comment_Types_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 7.2.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'comment-types';
	}

	/**
	 * Registers the routes for comment types.
	 *
	 * @since 7.2.0
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[\w-]+)',
			array(
				'args'   => array(
					'type' => array(
						'description' => __( 'An alphanumeric identifier for the comment type.' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks whether a given request has permission to read comment types.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error(
				'rest_cannot_view',
				__( 'Sorry, you are not allowed to edit comments.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retrieves comment types exposed in the REST API.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		if ( $request->is_method( 'HEAD' ) ) {
			// Return early as this handler doesn't add any response headers.
			return new WP_REST_Response( array() );
		}

		$data  = array();
		$types = get_comment_types( array( 'show_in_rest' => true ), 'objects' );

		// Edit-context requests are gated globally by get_items_permissions_check().
		foreach ( $types as $type ) {
			$comment_type        = $this->prepare_item_for_response( $type, $request );
			$data[ $type->name ] = $this->prepare_response_for_collection( $comment_type );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Checks if a given request has access to read a comment type.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to edit comments.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retrieves a specific comment type.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$obj = get_comment_type_object( $request['type'] );

		if ( empty( $obj ) ) {
			return new WP_Error(
				'rest_type_invalid',
				__( 'Invalid comment type.' ),
				array( 'status' => 404 )
			);
		}

		if ( empty( $obj->show_in_rest ) ) {
			return new WP_Error(
				'rest_cannot_read_type',
				__( 'Cannot view comment type.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$data = $this->prepare_item_for_response( $obj, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Prepares a comment type object for serialization.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Comment_Type $item    Comment type object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$comment_type = $item;

		// Don't prepare the response body for HEAD requests.
		if ( $request->is_method( 'HEAD' ) ) {
			/** This filter is documented in wp-includes/rest-api/endpoints/class-wp-rest-comment-types-controller.php */
			return apply_filters( 'rest_prepare_comment_type', new WP_REST_Response( array() ), $comment_type, $request );
		}

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( rest_is_field_included( 'description', $fields ) ) {
			$data['description'] = $comment_type->description;
		}

		if ( rest_is_field_included( 'labels', $fields ) ) {
			$data['labels'] = $comment_type->labels;
		}

		if ( rest_is_field_included( 'name', $fields ) ) {
			$data['name'] = $comment_type->label;
		}

		if ( rest_is_field_included( 'slug', $fields ) ) {
			$data['slug'] = $comment_type->name;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$response->add_links( $this->prepare_links( $comment_type ) );
		}

		/**
		 * Filters a comment type returned from the REST API.
		 *
		 * Allows modification of the comment type data right before it is returned.
		 *
		 * @since 7.2.0
		 *
		 * @param WP_REST_Response $response     The response object.
		 * @param WP_Comment_Type  $comment_type The original comment type object.
		 * @param WP_REST_Request  $request      Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_comment_type', $response, $comment_type, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * Note that the 'items' link is only followable by a user who can pass the comments
	 * endpoint's `type` parameter. That parameter is protected: callers without
	 * `edit_posts` may only request the 'comment' type, so for every other type the link
	 * answers 401 to an anonymous client. The link is still advertised, because what a
	 * caller may query is a question for the comments endpoint rather than something
	 * discovery should second-guess, and because the answer changes once per-type
	 * capabilities land.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_Comment_Type $comment_type The comment type.
	 * @return array Links for the given comment type.
	 */
	protected function prepare_links( $comment_type ) {
		return array(
			'collection'              => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
			'https://api.w.org/items' => array(
				'href' => add_query_arg( 'type', $comment_type->name, rest_url( 'wp/v2/comments' ) ),
			),
		);
	}

	/**
	 * Retrieves the comment type's schema, conforming to JSON Schema.
	 *
	 * @since 7.2.0
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'comment-type',
			'type'       => 'object',
			'properties' => array(
				'description' => array(
					'description' => __( 'A human-readable description of the comment type.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'labels'      => array(
					'description' => __( 'Human-readable labels for the comment type for various contexts.' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'name'        => array(
					'description' => __( 'The title for the comment type.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'slug'        => array(
					'description' => __( 'An alphanumeric identifier for the comment type.' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 7.2.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
		);
	}
}
