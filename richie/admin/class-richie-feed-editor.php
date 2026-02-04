<?php
/**
 * Feed Editor Controller
 *
 * Handles REST API endpoints for the visual feed editor.
 *
 * @link       https://www.richie.fi
 * @since      2.0.0
 *
 * @package    Richie
 * @subpackage Richie/admin
 */

class Richie_Feed_Editor {

	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 * @param    string    $plugin_name    The name of this plugin.
	 * @param    string    $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since    2.0.0
	 */
	public function register_routes() {
		$namespace = 'richie/v1';

		// Get collections (article sets)
		register_rest_route( $namespace, '/editor/collections', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_collections' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Get items for a collection
		register_rest_route( $namespace, '/editor/items/(?P<collection_id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Save item order
		register_rest_route( $namespace, '/editor/order/(?P<collection_id>\d+)', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_order' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Preview section articles
		register_rest_route( $namespace, '/editor/preview/(?P<section_id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'preview_section' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Section CRUD
		register_rest_route( $namespace, '/editor/section', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_section' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( $namespace, '/editor/section/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_section' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_section' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		// Ad slot CRUD
		register_rest_route( $namespace, '/editor/adslot', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'create_adslot' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		register_rest_route( $namespace, '/editor/adslot/(?P<id>[a-f0-9\-]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_adslot' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_adslot' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );

		// Get available post types
		register_rest_route( $namespace, '/editor/post-types', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_post_types' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	/**
	 * Check if user has permission to access editor endpoints.
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all collections (article sets).
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function get_collections( $request ) {
		$terms = get_terms( array(
			'taxonomy'   => 'richie_article_set',
			'hide_empty' => false,
		) );

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( array( 'error' => $terms->get_error_message() ), 500 );
		}

		$collections = array_map( function( $term ) {
			return array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}, $terms );

		return new WP_REST_Response( $collections, 200 );
	}

	/**
	 * Get feed items (sections and ad slots) for a collection.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function get_items( $request ) {
		$collection_id = intval( $request['collection_id'] );
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		$adslots_option = get_option( $this->plugin_name . '_adslots' );

		$sources = isset( $sources_option['sources'] ) ? $sources_option['sources'] : array();
		$adslots = isset( $adslots_option['slots'][ $collection_id ] ) ? $adslots_option['slots'][ $collection_id ] : array();

		// Filter sources for this collection
		$collection_sources = array_filter( $sources, function( $source ) use ( $collection_id ) {
			return isset( $source['article_set'] ) && intval( $source['article_set'] ) === $collection_id;
		} );

		// Check for custom order
		$custom_order = isset( $sources_option['collection_order'][ $collection_id ] )
			? $sources_option['collection_order'][ $collection_id ]
			: null;

		$items = array();

		if ( $custom_order ) {
			// Use custom order
			foreach ( $custom_order as $order_item ) {
				if ( $order_item['type'] === 'source' ) {
					$source_id = $order_item['id'];
					if ( isset( $collection_sources[ $source_id ] ) ) {
						$source = $collection_sources[ $source_id ];
						$items[] = $this->format_source_item( $source );
					}
				} elseif ( $order_item['type'] === 'ad' ) {
					$ad_id = $order_item['id'];
					// Find ad slot by UUID
					foreach ( $adslots as $adslot ) {
						if ( isset( $adslot['attributes']['id'] ) && $adslot['attributes']['id'] === $ad_id ) {
							$items[] = $this->format_adslot_item( $adslot );
							break;
						}
					}
				}
			}
		} else {
			// Default order: sources first, then ad slots
			foreach ( $collection_sources as $source ) {
				$items[] = $this->format_source_item( $source );
			}

			foreach ( $adslots as $adslot ) {
				$items[] = $this->format_adslot_item( $adslot );
			}
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/**
	 * Format source for response.
	 *
	 * @since    2.0.0
	 * @param    array    $source    Source data.
	 * @return   array
	 */
	private function format_source_item( $source ) {
		return array(
			'type'                => 'source',
			'id'                  => $source['id'],
			'uniqueId'            => 'source-' . $source['id'],
			'name'                => $source['name'],
			'number_of_posts'     => $source['number_of_posts'],
			'post_type'           => isset( $source['post_type'] ) ? $source['post_type'] : 'post',
			'categories'          => isset( $source['categories'] ) ? $this->normalize_categories( $source['categories'] ) : array(),
			'tags'                => isset( $source['tags'] ) ? $source['tags'] : array(),
			'order_by'            => isset( $source['order_by'] ) ? $source['order_by'] : 'date',
			'order_direction'     => isset( $source['order_direction'] ) ? $source['order_direction'] : 'DESC',
			'max_age'             => $this->normalize_max_age( isset( $source['max_age'] ) ? $source['max_age'] : '' ),
			'list_layout_style'   => isset( $source['list_layout_style'] ) ? $source['list_layout_style'] : 'small',
			'list_group_title'    => isset( $source['list_group_title'] ) ? $source['list_group_title'] : '',
			'background_color'    => isset( $source['background_color'] ) ? $source['background_color'] : '',
			'allow_duplicates'    => isset( $source['allow_duplicates'] ) ? $source['allow_duplicates'] : false,
			'disable_summary'     => isset( $source['disable_summary'] ) ? $source['disable_summary'] : false,
			'article_set'         => $source['article_set'],
		);
	}

	/**
	 * Format ad slot for response.
	 *
	 * @since    2.0.0
	 * @param    array    $adslot    Ad slot data.
	 * @return   array
	 */
	private function format_adslot_item( $adslot ) {
		$attributes = isset( $adslot['attributes'] ) ? $adslot['attributes'] : array();
		$ad_id = isset( $attributes['id'] ) ? $attributes['id'] : '';

		return array(
			'type'        => 'ad',
			'id'          => $ad_id,
			'uniqueId'    => 'ad-' . $ad_id,
			'ad_provider' => isset( $attributes['ad_provider'] ) ? $attributes['ad_provider'] : 'smart',
			'ad_data'     => isset( $attributes['ad_data'] ) ? $attributes['ad_data'] : null,
			'article_set' => isset( $adslot['article_set'] ) ? $adslot['article_set'] : 0,
		);
	}

	/**
	 * Save item order for a collection.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function save_order( $request ) {
		$collection_id = intval( $request['collection_id'] );
		$items = $request->get_param( 'items' );

		if ( ! is_array( $items ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid items data' ), 400 );
		}

		$sources_option = get_option( $this->plugin_name . 'news_sources' );

		if ( ! isset( $sources_option['collection_order'] ) ) {
			$sources_option['collection_order'] = array();
		}

		$sources_option['collection_order'][ $collection_id ] = $items;
		$sources_option['updated'] = time();

		update_option( $this->plugin_name . 'news_sources', $sources_option );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Preview articles for a section.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function preview_section( $request ) {
		$section_id = intval( $request['section_id'] );
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		$sources = isset( $sources_option['sources'] ) ? $sources_option['sources'] : array();

		if ( ! isset( $sources[ $section_id ] ) ) {
			return new WP_REST_Response( array( 'error' => 'Section not found' ), 404 );
		}

		$source = $sources[ $section_id ];

		// Use transient caching (5 minutes)
		$cache_key = 'richie_preview_' . $section_id;
		$cached = get_transient( $cache_key );

		if ( $cached !== false ) {
			return new WP_REST_Response( $cached, 200 );
		}

		// Build query args
		$args = array(
			'post_type'      => isset( $source['post_type'] ) ? $source['post_type'] : 'post',
			'posts_per_page' => min( intval( $source['number_of_posts'] ), 10 ), // Limit preview to 10
			'orderby'        => isset( $source['order_by'] ) ? $source['order_by'] : 'date',
			'order'          => isset( $source['order_direction'] ) ? $source['order_direction'] : 'DESC',
		);

		// Add category filter (use 'cat' for compatibility with legacy comma-separated format)
		if ( ! empty( $source['categories'] ) ) {
			$args['cat'] = $source['categories'];
		}

		// Add tag filter
		if ( ! empty( $source['tags'] ) ) {
			$args['tag_slug__in'] = $source['tags'];
		}

		// Add date query for max_age
		$normalized_max_age = $this->normalize_max_age( isset( $source['max_age'] ) ? $source['max_age'] : '' );
		if ( 'all_time' !== $normalized_max_age ) {
			$args['date_query'] = array(
				array(
					'after' => $normalized_max_age . ' ago',
				),
			);
		}

		$posts = get_posts( $args );

		$articles = array_map( function( $post ) {
			return array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ),
			);
		}, $posts );

		$result = array( 'articles' => $articles );

		// Cache for 5 minutes
		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Create a new section.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function create_section( $request ) {
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		$sources = isset( $sources_option['sources'] ) ? $sources_option['sources'] : array();

		// Generate new ID
		$new_id = empty( $sources ) ? 1 : max( array_keys( $sources ) ) + 1;

		// Build source data
		$source = $this->sanitize_section_data( $request->get_params() );
		$source['id'] = $new_id;

		// Unset max_age if empty (legacy compatibility - "All time" means key doesn't exist)
		if ( isset( $source['max_age'] ) && $source['max_age'] === '' ) {
			unset( $source['max_age'] );
		}

		$sources[ $new_id ] = $source;
		$sources_option['sources'] = $sources;
		$sources_option['updated'] = time();

		update_option( $this->plugin_name . 'news_sources', $sources_option );

		// Clear preview cache
		delete_transient( 'richie_preview_' . $new_id );

		return new WP_REST_Response( $this->format_source_item( $source ), 201 );
	}

	/**
	 * Update an existing section.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function update_section( $request ) {
		$section_id = intval( $request['id'] );
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		$sources = isset( $sources_option['sources'] ) ? $sources_option['sources'] : array();

		if ( ! isset( $sources[ $section_id ] ) ) {
			return new WP_REST_Response( array( 'error' => 'Section not found' ), 404 );
		}

		// Update source data
		$source = $this->sanitize_section_data( $request->get_params() );
		$source['id'] = $section_id;

		// Unset max_age if empty (legacy compatibility - "All time" means key doesn't exist)
		if ( isset( $source['max_age'] ) && $source['max_age'] === '' ) {
			unset( $source['max_age'] );
		}

		$sources[ $section_id ] = $source;
		$sources_option['sources'] = $sources;
		$sources_option['updated'] = time();

		update_option( $this->plugin_name . 'news_sources', $sources_option );

		// Clear preview cache
		delete_transient( 'richie_preview_' . $section_id );

		return new WP_REST_Response( $this->format_source_item( $source ), 200 );
	}

	/**
	 * Delete a section.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function delete_section( $request ) {
		$section_id = intval( $request['id'] );
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		$sources = isset( $sources_option['sources'] ) ? $sources_option['sources'] : array();

		if ( ! isset( $sources[ $section_id ] ) ) {
			return new WP_REST_Response( array( 'error' => 'Section not found' ), 404 );
		}

		$collection_id = $sources[ $section_id ]['article_set'];

		unset( $sources[ $section_id ] );
		$sources_option['sources'] = $sources;
		$sources_option['updated'] = time();

		// Remove from custom order if present
		if ( isset( $sources_option['collection_order'][ $collection_id ] ) ) {
			$sources_option['collection_order'][ $collection_id ] = array_filter(
				$sources_option['collection_order'][ $collection_id ],
				function( $item ) use ( $section_id ) {
					return ! ( $item['type'] === 'source' && $item['id'] === $section_id );
				}
			);
			$sources_option['collection_order'][ $collection_id ] = array_values( $sources_option['collection_order'][ $collection_id ] );
		}

		update_option( $this->plugin_name . 'news_sources', $sources_option );

		// Clear preview cache
		delete_transient( 'richie_preview_' . $section_id );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Sanitize section data.
	 *
	 * @since    2.0.0
	 * @param    array    $data    Raw section data.
	 * @return   array
	 */
	private function sanitize_section_data( $data ) {
		return array(
			'name'                => sanitize_text_field( $data['name'] ),
			'article_set'         => intval( $data['article_set'] ),
			'number_of_posts'     => intval( $data['number_of_posts'] ),
			'post_type'           => sanitize_text_field( isset( $data['post_type'] ) ? $data['post_type'] : 'post' ),
			'categories'          => isset( $data['categories'] ) ? array_map( 'intval', (array) $data['categories'] ) : array(),
			'tags'                => isset( $data['tags'] ) ? array_map( 'sanitize_text_field', (array) $data['tags'] ) : array(),
			'order_by'            => sanitize_text_field( isset( $data['order_by'] ) ? $data['order_by'] : 'date' ),
			'order_direction'     => sanitize_text_field( isset( $data['order_direction'] ) ? $data['order_direction'] : 'DESC' ),
			'max_age'             => $this->sanitize_max_age( isset( $data['max_age'] ) ? $data['max_age'] : '' ),
			'list_layout_style'   => sanitize_text_field( isset( $data['list_layout_style'] ) ? $data['list_layout_style'] : 'small' ),
			'list_group_title'    => sanitize_text_field( isset( $data['list_group_title'] ) ? $data['list_group_title'] : '' ),
			'background_color'    => sanitize_text_field( isset( $data['background_color'] ) ? $data['background_color'] : '' ),
			'allow_duplicates'    => isset( $data['allow_duplicates'] ) ? (bool) $data['allow_duplicates'] : false,
			'disable_summary'     => isset( $data['disable_summary'] ) ? (bool) $data['disable_summary'] : false,
		);
	}

	/**
	 * Normalize categories to array format.
	 *
	 * Legacy sources may store categories as comma-separated string.
	 *
	 * @since    2.0.0
	 * @param    mixed    $categories    Categories (array or comma-separated string).
	 * @return   array
	 */
	private function normalize_categories( $categories ) {
		if ( is_array( $categories ) ) {
			return array_map( 'intval', $categories );
		}
		if ( is_string( $categories ) && ! empty( $categories ) ) {
			return array_map( 'intval', explode( ',', $categories ) );
		}
		return array();
	}

	/**
	 * Sanitize max_age value.
	 *
	 * Converts 'all_time' sentinel value to empty string for storage.
	 *
	 * @since    2.0.0
	 * @param    string    $value    The max_age value.
	 * @return   string
	 */
	private function sanitize_max_age( $value ) {
		$value = sanitize_text_field( $value );
		// Convert 'all_time' sentinel value to empty string for storage
		if ( $value === 'all_time' || $value === 'All time' ) {
			return '';
		}
		return $value;
	}

	/**
	 * Normalize max_age value for editor usage.
	 *
	 * Ensures legacy "All time" and empty values map to the 'all_time' sentinel.
	 *
	 * @since    2.0.0
	 * @param    string    $value    The max_age value.
	 * @return   string
	 */
	private function normalize_max_age( $value ) {
		$value = sanitize_text_field( $value );
		if ( $value === '' || $value === 'all_time' || $value === 'All time' ) {
			return 'all_time';
		}
		return $value;
	}

	/**
	 * Create a new ad slot.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function create_adslot( $request ) {
		$collection_id = intval( $request->get_param( 'article_set' ) );
		$adslots_option = get_option( $this->plugin_name . '_adslots' );

		if ( ! isset( $adslots_option['slots'] ) ) {
			$adslots_option['slots'] = array();
		}

		if ( ! isset( $adslots_option['slots'][ $collection_id ] ) ) {
			$adslots_option['slots'][ $collection_id ] = array();
		}

		// Generate UUID for ad slot
		$ad_id = wp_generate_uuid4();

		// Find next available index
		$existing_indices = array_keys( $adslots_option['slots'][ $collection_id ] );
		$next_index = empty( $existing_indices ) ? 1 : max( $existing_indices ) + 1;

		$adslot = array(
			'index'       => $next_index,
			'article_set' => $collection_id,
			'updated'     => time(),
			'attributes'  => array(
				'id'                => $ad_id,
				'list_layout_style' => 'ad',
				'ad_provider'       => sanitize_text_field( $request->get_param( 'ad_provider' ) ),
				'ad_data'           => $request->get_param( 'ad_data' ),
			),
		);

		$adslots_option['slots'][ $collection_id ][ $next_index ] = $adslot;
		$adslots_option['updated'] = time();

		update_option( $this->plugin_name . '_adslots', $adslots_option );

		return new WP_REST_Response( $this->format_adslot_item( $adslot ), 201 );
	}

	/**
	 * Update an existing ad slot.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function update_adslot( $request ) {
		$ad_id = $request['id'];
		$collection_id = intval( $request->get_param( 'article_set' ) );
		$adslots_option = get_option( $this->plugin_name . '_adslots' );

		// Find the ad slot by UUID
		$found = false;
		if ( isset( $adslots_option['slots'][ $collection_id ] ) ) {
			foreach ( $adslots_option['slots'][ $collection_id ] as $index => &$adslot ) {
				if ( isset( $adslot['attributes']['id'] ) && $adslot['attributes']['id'] === $ad_id ) {
					$adslot['attributes']['ad_provider'] = sanitize_text_field( $request->get_param( 'ad_provider' ) );
					$adslot['attributes']['ad_data'] = $request->get_param( 'ad_data' );
					$adslot['updated'] = time();
					$found = true;
					break;
				}
			}
		}

		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Ad slot not found' ), 404 );
		}

		$adslots_option['updated'] = time();
		update_option( $this->plugin_name . '_adslots', $adslots_option );

		return new WP_REST_Response( $this->format_adslot_item( $adslot ), 200 );
	}

	/**
	 * Delete an ad slot.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function delete_adslot( $request ) {
		$ad_id = $request['id'];
		$collection_id = intval( $request->get_param( 'collection_id' ) );
		$adslots_option = get_option( $this->plugin_name . '_adslots' );

		// Find and remove the ad slot by UUID
		$found = false;
		if ( isset( $adslots_option['slots'][ $collection_id ] ) ) {
			foreach ( $adslots_option['slots'][ $collection_id ] as $index => $adslot ) {
				if ( isset( $adslot['attributes']['id'] ) && $adslot['attributes']['id'] === $ad_id ) {
					unset( $adslots_option['slots'][ $collection_id ][ $index ] );
					$found = true;
					break;
				}
			}

			// Clean up empty collection
			if ( empty( $adslots_option['slots'][ $collection_id ] ) ) {
				unset( $adslots_option['slots'][ $collection_id ] );
			}
		}

		if ( ! $found ) {
			return new WP_REST_Response( array( 'error' => 'Ad slot not found' ), 404 );
		}

		// Remove from custom order if present
		$sources_option = get_option( $this->plugin_name . 'news_sources' );
		if ( isset( $sources_option['collection_order'][ $collection_id ] ) ) {
			$sources_option['collection_order'][ $collection_id ] = array_filter(
				$sources_option['collection_order'][ $collection_id ],
				function( $item ) use ( $ad_id ) {
					return ! ( $item['type'] === 'ad' && $item['id'] === $ad_id );
				}
			);
			$sources_option['collection_order'][ $collection_id ] = array_values( $sources_option['collection_order'][ $collection_id ] );
			update_option( $this->plugin_name . 'news_sources', $sources_option );
		}

		$adslots_option['updated'] = time();
		update_option( $this->plugin_name . '_adslots', $adslots_option );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Get available post types.
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request    $request    Request object.
	 * @return   WP_REST_Response
	 */
	public function get_post_types( $request ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$types = array();
		foreach ( $post_types as $post_type ) {
			$types[] = array(
				'name'  => $post_type->name,
				'label' => $post_type->label,
			);
		}

		return new WP_REST_Response( $types, 200 );
	}
}
