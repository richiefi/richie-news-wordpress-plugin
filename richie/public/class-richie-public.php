<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie
 * @subpackage Richie/public
 */

?>
<?php
require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-post-type.php';

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Richie
 * @subpackage Richie/public
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Public {

	/**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
	private $plugin_name;

	/**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Richie options
     *
     * @since   1.0.0
     * @access  private
     * @var     array      $Richie_options
     */
    private $richie_options;

	/**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string $plugin_name  The name of the plugin.
     * @param    string $version      The version of this plugin.
     */
	public function __construct( $plugin_name, $version ) {
        require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-news-article.php';
        require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-template-loader.php';

		$this->plugin_name    = $plugin_name;
        $this->version        = $version;
        $this->richie_options = get_option( $plugin_name );
    }

    /**
     * Trigger a request-level integration hook for Richie REST routes.
     *
     * Runs on `init` with priority 0, so integration code can define constants
     * (e.g. DONOTCDN) before optimization plugins start response processing.
     *
     * @return void
     */
    public function maybe_trigger_request_init_action() {
        if ( ! $this->is_richie_rest_request() ) {
            return;
        }

        try {
            do_action( 'richie_request_init' );
        } catch ( Throwable $e ) {
            error_log( 'Error in richie_request_init action: ' . $e->getMessage() );
        }
    }

    /**
     * Detect whether current request targets Richie REST namespace.
     *
     * @return bool
     */
    private function is_richie_rest_request() {
        if ( isset( $_GET['rest_route'] ) ) {
            $rest_route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
            $rest_route = ltrim( $rest_route, '/' );

            if ( 0 === strpos( $rest_route, 'richie/' ) ) {
                return true;
            }
        }

        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
        $path        = wp_parse_url( $request_uri, PHP_URL_PATH );

        if ( ! is_string( $path ) ) {
            $path = $request_uri;
        }

        $prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/richie/';

        return false !== strpos( $path, $prefix );
    }

    public function get_ad_slots( $article_set ) {
        $adslots_option = get_option( $this->plugin_name . '_adslots' );
        return isset( $adslots_option['slots'] ) && isset( $adslots_option['slots'][ $article_set->term_id ] ) ? $adslots_option['slots'][ $article_set->term_id ] : array();
    }

    public function fetch_articles( $article_set, $unpublished, $include_original = false ) {
        // Get saved (and published) source list.
        $sourcelist = get_option( $this->plugin_name . 'news_sources' );

        if ( $unpublished ) {
            $richie_news_sources = isset( $sourcelist['sources'] ) ? $sourcelist['sources'] : array();
        } else {
            $richie_news_sources = isset( $sourcelist['published'] ) ? $sourcelist['published'] : array();
        }

        $posts     = array();
        $found_ids = array();
        $errors    = array();

        // Check for custom collection order
        if ( $unpublished ) {
            $collection_order = isset( $sourcelist['collection_order'][ $article_set->term_id ] )
                ? $sourcelist['collection_order'][ $article_set->term_id ]
                : null;
        } else {
            $published_orders = isset( $sourcelist['published_collection_order'] )
                ? $sourcelist['published_collection_order']
                : array();

            $collection_order = isset( $published_orders[ $article_set->term_id ] )
                ? $published_orders[ $article_set->term_id ]
                : ( isset( $sourcelist['collection_order'][ $article_set->term_id ] )
                    ? $sourcelist['collection_order'][ $article_set->term_id ]
                    : null );
        }

        // Filter sources matching this article set.
        $matching_sources = array_filter(
            $richie_news_sources,
            function ( $source ) use ( $article_set ) {
                return isset( $source['article_set'] ) && $article_set->term_id === $source['article_set'];
            }
        );

        // Index sources by ID for quick lookup when using custom order
        $sources_by_id = array();
        foreach ( $matching_sources as $source ) {
            $sources_by_id[ $source['id'] ] = $source;
        }

        // Get ad slots indexed by UUID
        $adslots_option  = get_option( $this->plugin_name . '_adslots' );
        $adslots_raw     = isset( $adslots_option['slots'][ $article_set->term_id ] )
            ? $adslots_option['slots'][ $article_set->term_id ]
            : array();
        $adslots_by_uuid = array();
        foreach ( $adslots_raw as $slot ) {
            if ( isset( $slot['attributes']['id'] ) ) {
                $adslots_by_uuid[ $slot['attributes']['id'] ] = $slot;
            }
        }

        // Determine iteration order
        $use_custom_order = ! empty( $collection_order );
        if ( $use_custom_order ) {
            $ordered_items = $collection_order;
        } else {
            $ordered_items = array();
        }

        // Use legacy ad slot handling if no custom order
        $adslots = $use_custom_order ? array() : $this->get_ad_slots( $article_set );

        // When using custom order, we'll build articles directly with interleaved ads
        $articles_with_order = array();

        // Process sources in order
        if ( $use_custom_order ) {
            // Custom order: look up sources by ID from ordered_items
            $sources_to_process = array();
            foreach ( $ordered_items as $item ) {
                if ( $item['type'] === 'source' && isset( $sources_by_id[ $item['id'] ] ) ) {
                    $sources_to_process[ $item['id'] ] = $sources_by_id[ $item['id'] ];
                }
            }
        } else {
            // Legacy: use all matching sources (preserves duplicates with same ID)
            $sources_to_process = $matching_sources;
        }

        foreach ( $sources_to_process as $source ) {
            $args = array(
                'posts_per_page' => $source['number_of_posts'],
                'post__not_in'   => array(),
                'post_type'      => 'post', // Default post type.
            );

            $allow_duplicates = isset( $source['allow_duplicates'] ) && true === $source['allow_duplicates'];

            if ( ! $allow_duplicates ) {
                $args['post__not_in'] = $found_ids;
            }

            if ( isset( $source['categories'] ) && ! empty( $source['categories'] ) ) {
                $args['cat'] = $source['categories'];
            }

            if ( isset( $source['tags'] ) && ! empty( $source['tags'] ) ) {
                $args['tag_slug__in'] = $source['tags'];
            }

            if ( isset( $source['order_by'] ) && ! empty( $source['order_by'] ) ) {
                $order_by   = 'date';
                $is_metakey = strpos( $source['order_by'], 'metakey:' ) === 0;

                if ( true === $is_metakey ) {
                    $meta = explode( ':', $source['order_by'] );
                    if ( count( $meta ) === 3 ) {
                        if ( isset( $meta[1] ) && isset( $meta[2] ) && ! empty( $meta[1] ) && ! empty( $meta[2] ) ) {
                            $args['meta_key'] = $meta[1];
                            $order_by         = $meta[2] . ' ID';
                        }
                    }
                } else {
                    $order_by = $source['order_by'];
                }

                $args['orderby'] = $order_by;
                $args['order']   = isset( $source['order_direction'] ) ? $source['order_direction'] : 'DESC';
            }

            if ( isset( $source['max_age'] ) && ! empty( $source['max_age'] ) ) {
                $args['date_query'] = array(
                    array(
                        'after' => sprintf( '%s ago', $source['max_age'] ),
                    ),
                );
            }

            if ( ! empty( $source['post_type'] ) ) {
                $args['post_type'] = $source['post_type'];
            }

            $source_posts = get_posts( $args );

            $article_attributes = array(
                'list_layout_style' => $source['list_layout_style'],
            );

            if ( ! empty( $source['list_group_title'] ) ) {
                $article_attributes['collection_header_title'] = $source['list_group_title'];
            }

            if ( isset( $source['disable_summary'] ) && true === $source['disable_summary'] ) {
                $article_attributes['summary'] = null;
            }

            if ( ! empty( $source['background_color'] ) ) {
                $article_attributes['background_color'] = ltrim( $source['background_color'], '#' );
            }

            foreach ( $source_posts as $p ) {
                $is_valid = Richie_Post_Type::validate_post( $p );

                if ( ! $is_valid ) {
                    continue;
                }

                if ( $allow_duplicates || ! in_array( $p->ID, $found_ids, true ) ) {
                    if ( empty( $p->guid ) ) {
                        $errors[] = array(
                            'description' => 'Missing guid',
                            'post_id'     => $p->ID,
                            'timestamp'   => time(),
                        );

                        continue;
                    }
                    array_push(
                        $posts,
                        array(
                            'id'                 => $p->ID,
                            'post_data'          => $p,
                            'article_attributes' => $article_attributes,
                            'source_id'          => $source['id'],
                        )
                    );

                    if ( ! $allow_duplicates ) {
                        array_push( $found_ids, $p->ID );
                    }

                    // Keep collection header title for all items in the group.
                }
            }
        }

        $articles = array();

        // Custom order: build articles by iterating through ordered_items with interleaved ads
        if ( $use_custom_order ) {
            // Index posts by source_id for lookup
            $posts_by_source = array();
            foreach ( $posts as $p ) {
                $source_id = $p['source_id'];
                if ( ! isset( $posts_by_source[ $source_id ] ) ) {
                    $posts_by_source[ $source_id ] = array();
                }
                $posts_by_source[ $source_id ][] = $p;
            }

            foreach ( $ordered_items as $item ) {
                if ( $item['type'] === 'source' ) {
                    $source_id = $item['id'];
                    if ( isset( $posts_by_source[ $source_id ] ) ) {
                        foreach ( $posts_by_source[ $source_id ] as $p ) {
                            $content_post = $p['post_data'];
                            $date         = ( new DateTime( $content_post->post_date_gmt ) )->format( 'c' );
                            $updated_date = ( new DateTime( $content_post->post_modified_gmt ) )->format( 'c' );

                            $article = array(
                                'id'                 => strval( $content_post->ID ),
                                'fetch_id'           => $content_post->ID,
                                'last_updated'       => max( $date, $updated_date ),
                                'article_attributes' => $p['article_attributes'],
                            );

                            if ( $include_original ) {
                                $article['original_post'] = $content_post;
                            }

                            array_push( $articles, $article );
                        }
                    }
                } elseif ( $item['type'] === 'ad' ) {
                    $ad_id = $item['id'];
                    if ( isset( $adslots_by_uuid[ $ad_id ] ) ) {
                        $slot                  = $adslots_by_uuid[ $ad_id ];
                        $attributes            = $slot['attributes'];
                        $attributes['updated'] = date( 'c', $slot['updated'] );

                        array_push(
                            $articles,
                            array(
                                'id'                 => $attributes['id'],
                                'last_updated'       => date( 'c', $slot['updated'] ),
                                'article_attributes' => $attributes,
                            )
                        );
                    }
                }
            }
        } else {
            // Legacy order: position-based ad insertion
            foreach ( $posts as $key => $p ) {
                // Check available adslot, adslot index is 1-based.
                if ( isset( $adslots[ $key + 1 ] ) ) {
                    $slot                  = $adslots[ $key + 1 ];
                    $attributes            = $slot['attributes'];
                    $attributes['updated'] = date( 'c', $slot['updated'] );

                    // We have adslots for the index, include it first.
                    array_push(
                        $articles,
                        array(
                            'id'                 => $attributes['id'],
                            'last_updated'       => date( 'c', $slot['updated'] ),
                            'article_attributes' => $attributes,
                        )
                    );
                    unset( $adslots[ $key + 1 ] );
                }
                $content_post = $p['post_data'];
                $date         = ( new DateTime( $content_post->post_date_gmt ) )->format( 'c' );
                $updated_date = ( new DateTime( $content_post->post_modified_gmt ) )->format( 'c' );

                $article = array(
                    'id'                 => strval( $content_post->ID ),
                    'fetch_id'           => $content_post->ID,
                    'last_updated'       => max( $date, $updated_date ),
                    'article_attributes' => $p['article_attributes'],
                );

                if ( $include_original ) {
                    $article['original_post'] = $content_post;
                }

                array_push(
                    $articles,
                    $article
                );
            }

            if ( ! empty( $adslots ) ) {
                // We have slots left, include them at the end.
                foreach ( $adslots as $slot ) {
                    $attributes = $slot['attributes'];
                    array_push(
                        $articles,
                        array(
                            'id'                 => $slot['attributes']['id'],
                            'last_updated'       => date( 'c', $slot['updated'] ),
                            'article_attributes' => $attributes,
                        )
                    );
                }
            }
        }

        return array(
			'articles' => $articles,
			'errors'   => $errors,
        );
    }

    public function get_section_article( $article ) {
        $section_article = array(
            'layout' => $article['article_attributes']['list_layout_style'],
        );

        if ( 'ad' !== $article['article_attributes']['list_layout_style'] ) {
            $post                            = $article['original_post'];
            $article_instance                = new Richie_Article( $this->richie_options );
            $generated_article               = $article_instance->generate_article( $post, Richie_Article::EXCLUDE_CONTENT );
            $section_article['publisher_id'] = $article['id'];

            $premium_categories = isset( $this->richie_options['premium_categories'] ) ? (array) $this->richie_options['premium_categories'] : array();
            if ( ! empty( $premium_categories ) ) {
                $post_categories               = wp_get_post_categories( $post->ID );
                $section_article['is_premium'] = ! empty( array_intersect( $post_categories, $premium_categories ) );
            }

            $summary_disabled = false;
            if ( array_key_exists( 'summary', $article['article_attributes'] ) && null === $article['article_attributes']['summary'] ) {
                $summary_disabled = true;
            }

            if ( isset( $article['article_attributes']['collection_header_title'] ) ) {
                $section_article['collection_header_title'] = $article['article_attributes']['collection_header_title'];
            }

            if ( isset( $article['article_attributes']['background_color'] ) ) {
                $section_article['background_color'] = $article['article_attributes']['background_color'];
            }

            foreach ( $generated_article as $key => $value ) {
                if ( 'id' === $key || 'hash' === $key ) {
                    continue;
                }
                $section_article[ $key ] = $value;
            }

            if ( $summary_disabled ) {
                // Exclude summary if disabled in settings.
                unset( $section_article['summary'] );
            }
        } else {
            $section_article = array_merge(
                $section_article,
                array(
                    'ad_provider' => $article['article_attributes']['ad_provider'],
                    'ad_data'     => $article['article_attributes']['ad_data'],
                )
            );
        }

        return array_filter(
            $section_article,
            function ( $v ) {
                return ! is_null( $v );
            }
        );
    }

    public function feed_route_handler( $data ) {
        $article_set = get_term_by( 'slug', $data['article_set'], 'richie_article_set' );

        if ( empty( $article_set ) ) {
            return new WP_Error( 'article_set_not_found', 'Article set not found', array( 'status' => 404 ) );
        }

        $params      = $data->get_query_params();
        $unpublished = isset( $params['unpublished'] ) && '1' === $params['unpublished'];
        $result      = $this->fetch_articles( $article_set, $unpublished, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $articles = $result['articles'];
        $errors   = $result['errors'];

        if ( ! headers_sent() ) {
            $etag = 'W/"' . md5( wp_json_encode( $articles ) ) . '"';
            // if_none_match may contain slashes before ", so strip those.
            $etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

            header( "Etag: {$etag}" );
            header( 'Cache-Control: private, no-cache' );

            if ( $etag_header === $etag ) {
                header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified' );
                die(); // send response and exit.
            }
        }

        $output = array(
            'section'  => array(
                'name' => $article_set->name,
            ),
            'articles' => array_map( array( $this, 'get_section_article' ), $articles ),
        );

        if ( ! empty( $errors ) ) {
            $output['errors'] = $errors;
        }

        return $output;
    }

    public function search_route_handler( $data ) {
        $term = sanitize_text_field( $data->get_param( 'q' ) );

        if ( ! $term ) {
            return array( 'articles' => array() );
        }

        $args = array(
            'numberposts' => 50,
            'post_type'   => 'post', // Default post type.
            's'           => $term,
        );

        $source_posts = get_posts( $args );

        $posts   = array();
        $article = new Richie_Article( $this->richie_options );

        foreach ( $source_posts as $p ) {
            $is_valid = Richie_Post_Type::validate_post( $p );

            if ( $is_valid !== true ) {
                continue;
            }

            $generated_article         = $article->generate_article( $p, Richie_Article::EXCLUDE_CONTENT );
            $generated_article->layout = isset( $this->richie_options['search_list_layout_style'] ) ? $this->richie_options['search_list_layout_style'] : 'small';
            array_push( $posts, $generated_article );
        }

        return array(
            'section'  => array(
                'name' => 'Search results',
            ),
            'articles' => $posts,
        );
    }

    public function article_route_handler( $data, $version = 1 ) {
        $assets = $this->get_assets();

        if ( false === $assets ) {
            $assets = array();
        }

        $article      = new Richie_Article( $this->richie_options, $assets, $version );
        $post         = get_post( $data['id'] );
        $last_updated = $post->post_modified_gmt ?? $post->post_date_gtm;

        if ( ! headers_sent() ) {
            $etag = 'W/"' . md5( $post->ID . $last_updated ) . '"';
            // if_none_match may contain slashes before ", so strip those.
            $etag_header = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

            header( "Etag: {$etag}" );
            header( 'Cache-Control: private, no-cache' );

            if ( $etag_header === $etag ) {
                header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified' );
                die(); // send response and exit.
            }
        }

        if ( empty( $post ) ) {
            return new WP_Error( 'no_id', 'Invalid article id', array( 'status' => 404 ) );
        } else {
            $template_name     = isset( $data['template'] ) ? $data['template'] : 'article';
            $generated_article = $article->generate_article( $post, $version >= 3 ? Richie_Article::EXCLUDE_METADATA : Richie_Article::EXCLUDE_NONE, $template_name );
            return $generated_article;
        }
    }

    public function article_route_handler_v3( $data ) {
        return $this->article_route_handler( $data, 3 );
    }

    public function check_permission( $request ) {
        $options = get_option( $this->plugin_name );
        $params  = $request->get_query_params();
        if ( isset( $params['token'] ) && ! empty( $options['access_token'] ) && hash_equals( $options['access_token'], $params['token'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Generate assets list (scripts and styles). Caches it for one hour.
     *
     * @return array
     */
    public function get_assets() {
        require_once plugin_dir_path( __DIR__ ) . 'includes/class-richie-app-asset.php';

        $transient_key = RICHIE_ASSET_CACHE_KEY;

        // Allow cache flush via ?flush_cache=1 (useful during development).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['flush_cache'] ) ) {
            delete_transient( $transient_key );
        }

        $cached_assets = get_transient( $transient_key );

        if ( ! empty( $cached_assets ) ) {
            // Cache exists, return it.
            return $cached_assets;
        }

        global $wp_scripts, $wp_styles;

        ob_start();
        // These will populate $wp_scripts->done and $wp_styles->done.
        wp_head();
        wp_footer();
        ob_end_clean();

        // Read from ->done (handles already output), not do_items() which re-runs output.
        $general_assets = richie_get_emitted_assets( 'app-assets/' );

        // Discover CSS sub-resources (fonts, @import, url() — e.g. @font-face).
        // The Richie app does not crawl CSS for sub-resources, so they must be listed explicitly.
        $css_deps = richie_discover_css_dependencies( $general_assets, 'app-assets/' );

        // When RICHIE_INCLUDE_ALL_BLOCK_STYLES is true, add all registered WP block styles to
        // the global feed so they are available without requiring an article render first.
        if ( defined( 'RICHIE_INCLUDE_ALL_BLOCK_STYLES' ) && RICHIE_INCLUDE_ALL_BLOCK_STYLES ) {
            $block_handles = array();
            foreach ( $wp_styles->registered as $handle => $style ) {
                $url = richie_get_registered_asset_url( $style );
                if ( is_string( $url ) && ( false !== strpos( $url, 'wp-includes/blocks' ) || 0 === strpos( $handle, 'wp-block-' ) ) ) {
                    $block_handles[] = $handle;
                }
            }
            $block_assets   = richie_collect_registered_assets( array(), $block_handles, 'app-assets/' );
            $css_deps       = array_merge( $css_deps, richie_discover_css_dependencies( $block_assets, 'app-assets/' ) );
            $general_assets = array_merge( $general_assets, $block_assets );
        }

        // Reset "done" state so subsequent wp_head()/wp_footer() calls
        // (e.g. in render_template) can still output these assets.
        $wp_scripts->done = array();
        $wp_styles->done  = array();

        $custom_assets = get_option( $this->plugin_name . '_assets' );

        if ( false === $custom_assets ) {
            $custom_assets = array();
        }

        $all_assets = array();

        // Map assets by local_name. Later entries override earlier ones, so custom assets
        // added last will override auto-discovered assets with the same local_name.
        foreach ( $general_assets as $asset ) {
            $all_assets[ $asset->local_name ] = $asset;
        }

        foreach ( $css_deps as $asset ) {
            // Only add if not already present — explicit assets take precedence.
            if ( ! isset( $all_assets[ $asset->local_name ] ) ) {
                $all_assets[ $asset->local_name ] = $asset;
            }
        }

        foreach ( $custom_assets as $asset ) {
            $all_assets[ $asset->local_name ] = $asset;
        }

        $all_assets = array_values( $all_assets );
        set_transient( $transient_key, $all_assets, HOUR_IN_SECONDS ); // Cache for one hour.
        return $all_assets;
    }

    public function asset_feed_handler() {
        $assets = $this->get_assets();

        $etag = md5( wp_json_encode( $assets ) );

        if ( ! headers_sent() ) {
            header( "Etag: $etag" );
            header( 'Cache-Control: private, no-cache' );
        }

        return array( 'app_assets' => $assets );
    }

    /**
     * Register rest api route for richie feed
     *
     * @since    1.0.0
     */
    public function register_richie_rest_api() {
        // Clean up headers.
        remove_action( 'wp_head', 'rsd_link' ); // Remove really simple discovery link.
        remove_action( 'wp_head', 'wp_generator' ); // Remove WordPress version.
        remove_action( 'wp_head', 'feed_links', 2 ); // Remove rss feed links (make sure you add them in yourself if youre using feedblitz or an rss service).
        remove_action( 'wp_head', 'feed_links_extra', 3 ); // Removes all extra rss feed links.
        remove_action( 'wp_head', 'index_rel_link' ); // Remove link to index page.
        remove_action( 'wp_head', 'wlwmanifest_link' ); // Remove wlwmanifest.xml (needed to support windows live writer).
        remove_action( 'wp_head', 'start_post_rel_link', 10, 0 ); // Remove random post link.
        remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 ); // Remove parent post link.
        remove_action( 'wp_head', 'adjacent_posts_rel_link', 10, 0 ); // Remove the next and previous post links.
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 ); // Remove shortlink.

        register_rest_route(
            'richie/v1',
            '/news(?:/(?P<article_set>\S+))',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'feed_route_handler' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'article_set' => array(
                        'sanitize_callback' => 'sanitize_title',
                    ),
                ),
            )
        );

        register_rest_route(
            'richie/v1',
            '/search',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'search_route_handler' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );

        register_rest_route(
            'richie/v1',
            '/article/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'article_route_handler_v3' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'id'       => array(
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'template' => array(
                        'sanitize_callback' => 'sanitize_title',
                        'default'           => 'article',
                    ),
                ),
            )
        );

        register_rest_route(
            'richie/v1',
            '/assets',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'asset_feed_handler' ),
                'permission_callback' => function () {
                    return true;
                },
            )
        );
    }

    public function richie_template( $template ) {
        if ( isset( $_GET['token'] ) && ! empty( $this->richie_options['access_token'] ) && hash_equals( $this->richie_options['access_token'], sanitize_text_field( wp_unslash( $_GET['token'] ) ) ) ) {
            if ( isset( $_GET['richie_news'] ) ) {
                // Remove jquery-migrate from article output.
                wp_deregister_script( 'jquery-migrate' );
                wp_register_script( 'jquery-migrate', false, array(), false, false );

                $name = 'article';

                if ( isset( $_GET['template'] ) ) {
                    $name = sanitize_title( $_GET['template'] );
                }

                $resolved = richie_resolve_template( 'richie-news', $name, $this->richie_options );

                switch ( $resolved['type'] ) {
                    case 'block_path':
                        set_query_var( 'richie_block_template_path', $resolved['path'] );
                        return Richie_PLUGIN_DIR . 'templates/richie-news-block.php';

                    case 'block_slug':
                        set_query_var( 'richie_block_template_slug', $resolved['slug'] );
                        return Richie_PLUGIN_DIR . 'templates/richie-news-block.php';

                    case 'php':
                        $richie_template_loader = new Richie_Template_Loader();
                        $template               = $richie_template_loader->get_template_part( $resolved['slug'], $resolved['name'], false );
                        break;
                }
            }
        }
        return $template;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-public.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-public.js', array( 'jquery' ), $this->version, false );
    }

    /**
     * Register short codes
     */
    public function register_shortcodes() {
    }

    /**
     * Register block templates for block themes.
     */
    public function register_block_templates() {
        if ( ! function_exists( 'register_block_template' ) ) {
            return;
        }

        if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
            return;
        }

        $template_path = Richie_PLUGIN_DIR . 'templates/block-templates/' . richie_get_block_template_slug() . '.html';
        if ( ! file_exists( $template_path ) ) {
            return;
        }

        $content = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
        if ( false === $content ) {
            return;
        }

        $template_id = $this->plugin_name . '//' . richie_get_block_template_slug();
        register_block_template(
            $template_id,
            array(
                'title'          => __( 'Richie Article', 'richie' ),
                'description'    => __( 'Richie news article template.', 'richie' ),
                'content'        => $content,
                'post_types'     => array( 'post' ),
                'template_types' => array( 'single' ),
            )
        );
    }
}
