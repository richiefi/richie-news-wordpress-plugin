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

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-maggio-service.php';

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

    private $richie_news_sources;
	/**
    * Initialize the class and set its properties.
    *
    * @since    1.0.0
    * @param      string    $plugin_name       The name of the plugin.
    * @param      string    $version    The version of this plugin.
    */
	public function __construct( $plugin_name, $version ) {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-news-article.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-template-loader.php';

		$this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->richie_options = get_option($plugin_name);
        $sourcelist = get_option($plugin_name . 'news_sources');
        $this->richie_news_sources = isset($sourcelist['published']) ? $sourcelist['published'] : array();
    }

    function feed_route_handler($data) {
        $posts = array();
        $found_ids = array();
        $article_set = get_term_by('slug', $data['article_set'], 'richie_article_set');

        if( empty( $article_set ) ) {
            return new WP_Error( 'article_set_not_found', 'Article set not found', array( 'status' => 404 ) );
        }

        $sources = array_filter( $this->richie_news_sources, function( $source ) use ($article_set) {
            return $article_set->term_id === $source['article_set'];
        } );

        $adslots_option = get_option($this->plugin_name . '_adslots');
        $adslots = isset($adslots_option['slots']) && isset($adslots_option['slots'][$article_set->term_id]) ? $adslots_option['slots'][$article_set->term_id] : array();

        foreach( $sources as $source ) {
            $args = array(
                'posts_per_page' => $source['number_of_posts'],
                'post__not_in' => $found_ids
            );

            if ( isset($source['herald_featured_post_id']) ) {
                if ( !defined('HERALD_THEME_VERSION') ) {
                    // herald not active, ignore source
                    continue;
                }

                // use herald featured module
                $page_id = (int)$source['herald_featured_post_id'];
                $meta = get_post_meta( $page_id, '_herald_meta', true );
                if (empty($meta)) {
                    // no metadata found
                    continue;
                }

                if ( isset($meta['sections']) && !empty( $meta['sections'] ) ) {
                    $module = null;
                    foreach( $meta['sections'] as $sec ) {
                        if ( $module ) {
                            break;
                        }
                        if( isset($sec['modules']) && !empty($sec['modules'] ) ) {
                            foreach ( $sec['modules'] as $mod ) {
                                if ( $mod['type'] === 'featured' ) {
                                    // use first
                                    $module = $mod;
                                    break;
                                }
                            }
                        }
                    }


                    if ( $module !== null ) {
                        if ( isset( $module['manual'] ) && !empty( $module['manual']) ) {
                            $args['post__in'] = array_diff($module['manual'], $found_ids); // post__not_in is ignored if post__in used, so use diff
                            $args['ignore_sticky_posts'] = true;  //https://developer.wordpress.org/reference/classes/wp_query/parse_query/
                            $args['orderby'] = 'post__in';
                        } else {
                            $args['orderby'] = $module['order'];
                            $args['order'] = $module['sort'] === 'ASC' ? 'ASC' : 'DESC';
                            if ( isset( $module['cat'] ) ) {
                                $args['cat'] = $module['cat'];
                            }

                            if ( isset( $module['exclude_by_id'] ) && !empty( $module['exclude_by_id'] ) ) {
                                $args['post__not_in'] = array_merge( $args['post__not_in'], $module['exclude_by_id'] );
                            }
                        }
                    }

                }



            } else {
                if ( isset( $source['categories'] ) && ! empty( $source['categories'] ) ) {
                    $args['cat'] = $source['categories'];
                }

                if ( isset( $source['order_by'] ) && ! empty( $source['order_by'] ) ) {
                    $order_by = 'date';
                    $is_metakey = strpos($source['order_by'], 'metakey:') === 0;
                    $is_popular = strpos($source['order_by'], 'popular:') === 0;

                    if ( $is_metakey === true) {
                        $meta = explode(':', $source['order_by']);
                        if (count( $meta ) === 3) {
                            if( isset( $meta[1] ) && isset( $meta[2] ) && !empty( $meta[1] ) && !empty( $meta[2] ) ) {
                                $args['meta_key'] = $meta[1];
                                $order_by = $meta[2] . ' ID';
                            }
                        }
                    } elseif ( $is_popular === true ) {
                        if ( !class_exists('WPP_query') ) {
                            continue; // no plugin found, ignore source
                        }
                        // popular:<unit>
                        $popular_settings = explode(':', $source['order_by']);
                        if ( count( $popular_settings ) !== 2) {
                            continue; // invalid configuration, ignore source
                        }

                        $popular_range = $popular_settings[1];

                        $popular_args = array(
                            'range' => $popular_range,
                            'limit' => (int)$source['number_of_posts'],
                            'post_type' => 'post',
                            'pid' => implode(',', $found_ids)
                        );

                        //print_r($popular_args);

                        if ( isset( $source['categories'] ) && ! empty( $source['categories'] ) ) {
                            $popular_args['cat'] = $source['categories'];
                        }
                        $popular_query = new WPP_query( $popular_args );
                        $popular_posts = array_column($popular_query->get_posts(), 'id');
                        $args['post__in'] = $popular_posts;
                        $args['ignore_sticky_posts'] = true;
                        $order_by = 'post__in';
                    } else {
                        $order_by = $source['order_by'];
                    }

                    $args['orderby'] = $order_by;
                    $args['order'] = isset($source['order_direction']) ? $source['order_direction'] : 'DESC';
                }

                if ( isset( $source['max_age'] ) && !empty( $source['max_age'] ) ) {
                    $args['date_query'] = array(
                        array(
                        'after' => sprintf('%s ago', $source['max_age'])
                        )
                    );
                }
            }
            //print_r($args);
            $source_posts = get_posts($args);

            $article_attributes = array(
                'list_layout_style' => $source['list_layout_style']
            );

            if ( !empty( $source['list_group_title']) ) {
                $article_attributes['list_group_title'] = $source['list_group_title'];
            }

            if ( isset( $source['disable_summary'] ) && $source['disable_summary'] === true ) {
                $article_attributes['summary'] = null;
            }

            foreach ( $source_posts as $p) {
                if ( ! in_array( $p->ID, $found_ids) ) {
                    array_push( $posts, array (
                        'id' => $p->ID,
                        'post_data' => $p,
                        'article_attributes' => $article_attributes
                    ));
                    array_push( $found_ids, $p->ID );
                }
            }
        }
        $articles = array();

        foreach ($posts as $key => $p) {
            // check available adslot, adslot index is 1-based
            if( isset( $adslots[$key + 1] ) ) {
                $slot = $adslots[$key + 1];
                $attributes = $slot['attributes'];
                $attributes['updated'] = date('c', $slot['updated']);

                // we have adslots for the index, include it first
                array_push($articles, array(
                    'id' => $attributes['id'],
                    'last_updated' => date('c', $slot['updated']),
                    'article_attributes' => $attributes
                ));
                unset($adslots[$key + 1]);
            }
            $content_post = $p['post_data'];
            $date = (new DateTime($content_post->post_date_gmt))->format('c');
            $updated_date = (new DateTime($content_post->post_modified_gmt))->format('c');


            array_push($articles, array(
                'id' => $content_post->guid,
                'fetch_id' => $content_post->ID,
                'last_updated' => max($date, $updated_date),
                'article_attributes' => $p['article_attributes']
            ));
        }

        $etag = 'W/"' . md5(serialize($articles)) . '"';
        // if_none_match may contain slashes before ", so strip those
        $etagHeader = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;

        header("Etag: {$etag}");
        header('Cache-Control: private, no-cache');

        if ( $etagHeader === $etag ) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            wp_send_json(); // send response and exit
        }

        return array( 'article_ids' => $articles );
    }

    public function article_route_handler($data) {
        $assets = get_option($this->plugin_name . '_assets');

        if ( $assets === false) {
            $assets = [];
        }

        header('Cache-Control: private, no-cache');

        $article = new Richie_Article($this->richie_options, $assets);
        $post = get_post($data['id']);
        if ( empty( $post ) ) {
            return new WP_Error( 'no_id', 'Invalid article id', array( 'status' => 404 ) );
        } else {
            $generated_article = $article->generate_article($post);
            return $generated_article;
        }
    }

    public function check_permission() {
        if (isset( $_GET['token']) && $this->richie_options['access_token'] === $_GET['token']) {
            return true;
        }
        return false;
    }

    public function asset_feed_handler() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-app-asset.php';
        $result = [];

        if (isset( $_GET['generate']) && $_GET['generate'] === 'true') {
            global $wp_scripts, $wp_styles;

            ob_start();
            // these will cause styles/scripts to be included in global variables
            wp_head();
            wp_footer();
            ob_end_clean();

            foreach ( $wp_scripts->do_items() as $script_name ) {
                $script = $wp_scripts->registered[$script_name];
                $remote_url = $script->src;
                if ((substr($remote_url, -3) === '.js') && !strpos($remote_url, 'wp-admin')) {
                    $result[] = new Richie_App_Asset($script);
                }
            }
            // Print all loaded Styles (CSS)
            foreach( $wp_styles->do_items() as $style_name ) {
                $style = $wp_styles->registered[$style_name];
                $remote_url = $style->src;
                if ((substr($remote_url, -4) === '.css') && !strpos($remote_url, 'wp-admin')) {
                    $result[] = new Richie_App_Asset($style);
                }
            }
        } else {
            $result = get_option($this->plugin_name . '_assets');
            if ( $result === false ) {
                $result = [];
            }
            $etag = md5(json_encode($result));
            header("Etag: $etag");
            header('Cache-Control: private, no-cache');
        }
        return array('app_assets' => $result);
    }

    /**
    * Register rest api route for richie feed
    *
    * @since    1.0.0
    */
    public function register_richie_rest_api() {
        // clean up headers
        remove_action('wp_head', 'rsd_link'); // remove really simple discovery link
        remove_action('wp_head', 'wp_generator'); // remove wordpress version
        remove_action('wp_head', 'feed_links', 2); // remove rss feed links (make sure you add them in yourself if youre using feedblitz or an rss service)
        remove_action('wp_head', 'feed_links_extra', 3); // removes all extra rss feed links
        remove_action('wp_head', 'index_rel_link'); // remove link to index page
        remove_action('wp_head', 'wlwmanifest_link'); // remove wlwmanifest.xml (needed to support windows live writer)
        remove_action('wp_head', 'start_post_rel_link', 10, 0); // remove random post link
        remove_action('wp_head', 'parent_post_rel_link', 10, 0); // remove parent post link
        remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0); // remove the next and previous post links
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0); // Remove shortlink

        register_rest_route( 'richie/v1', '/news(?:/(?P<article_set>\S+))', array(
            'methods' => 'GET',
            'callback' => array($this, 'feed_route_handler'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array (
                'article_set' => array (
                    'sanitize_callback' => 'sanitize_title'
                )
            ),
        ) );

        register_rest_route( 'richie/v1', '/article/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'article_route_handler'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                  'validate_callback' => function($param, $request, $key) {
                    return is_numeric( $param );
                  }
                ),
            ),
        ));

        register_rest_route( 'richie/v1', '/assets', array(
            'methods' => 'GET',
            'callback' => array($this, 'asset_feed_handler')
        ));
    }

    public function richie_template($template) {
        if (isset( $_GET['token']) && $this->richie_options['access_token'] === $_GET['token']) {
            if( isset( $_GET['richie_news'] ) ) {
                // remove version from scripts and styles
                // remove_action('wp_head', 'wp_generator'); // remove wordpress version
                // function remove_version_scripts_styles($src) {
                //     if (strpos($src, 'ver=')) {
                //         $src = remove_query_arg('ver', $src);
                //     }
                //     return $src;
                // }
                // add_filter('style_loader_src', 'remove_version_scripts_styles', 9999);
                // add_filter('script_loader_src', 'remove_version_scripts_styles', 9999);
                // disable pmpro
                add_filter( 'pmpro_has_membership_access_filter', '__return_true', 20, 4 );

                $name = 'article';

                if ( isset( $_GET['template'] ) ) {
                    $name = sanitize_title($_GET['template']);
                }

                $richie_template_loader = new Richie_Template_Loader;
                $template = $richie_template_loader->get_template_part( 'richie-news', $name, false );
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

        /**
        * This function is provided for demonstration purposes only.
        *
        * An instance of this class should be passed to the run() function
        * defined in Richie_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-public.css', array(), $this->version, 'all' );

    }

    /**
    * Register the JavaScript for the public-facing side of the site.
    *
    * @since    1.0.0
    */
    public function enqueue_scripts() {

        /**
        * This function is provided for demonstration purposes only.
        *
        * An instance of this class should be passed to the run() function
        * defined in Richie_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-public.js', array( 'jquery' ), $this->version, false );

    }

    /**
     * Load maggio display content
     */
    public function load_maggio_index_content($attributes) {
        if ( !isset( $this->richie_options['maggio_hostname'] ) || empty( $this->richie_options['maggio_hostname'] ) ) {
            return sprintf('<div>%s</div>', __('Invalid configuration, missing hostname in settings', $this->plugin_name));
        }

        $atts = shortcode_atts(
            array(
                'product' => null,
                'organization' => isset( $this->richie_options['maggio_organization']) ? $this->richie_options['maggio_organization'] : null,
                'number_of_issues' => null,
            ), $attributes, 'maggio' );

        if( empty( $atts['product'] ) ) {
            return sprintf('<div>%s</div>', __('"product" attribute is required', $this->plugin_name));
        }

        if( empty( $atts['organization'] ) ) {
            return sprintf('<div>%s</div>', __('Invalid organization', $this->plugin_name));
        }

        $host_name = $this->richie_options['maggio_hostname'];
        $organization = $atts['organization'];
        $product = $atts['product'];

        try {
            $maggio_service = new Richie_Maggio_Service($host_name);
        } catch ( Exception $e ) {
            return sprintf('<div>%s</div>', __('Failed to fetch issues'));
        }

        $issues = $maggio_service->get_issues($organization, $product, intval($atts['number_of_issues']));
        $required_pmpro_level = isset( $this->richie_options['maggio_required_pmpro_level'] ) ? $this->richie_options['maggio_required_pmpro_level'] : 0;
        $user_has_access = richie_has_maggio_access( $required_pmpro_level );

        if( $issues === false ) {
            return sprintf('<div>%s</div>', __('Failed to fetch issues', $this->plugin_name));
        }

        $richie_template_loader = new Richie_Template_Loader();
        $template = $richie_template_loader->locate_template( 'richie-maggio-index.php', false, false );
        ob_start();
        include $template;

        return ob_get_clean();
    }

    /**
     * Register short code for displaying maggio index page
     */
    public function register_shortcodes() {
        add_shortcode('maggio', array($this, 'load_maggio_index_content'));
    }

    /**
     * create redirection for maggio issues
     */
    public function register_redirect_route() {


        richie_create_maggio_rewrite_rules();
        add_action('parse_request', array( $this, 'maggio_redirect_request') );
    }

    public function maggio_redirect_request ( $wp ) {
        if(
            !empty($wp->query_vars['maggio_redirect']) &&
            wp_is_uuid($wp->query_vars['maggio_redirect'])
        ) {
            if (
                !isset( $this->richie_options['maggio_secret'] ) ||
                !isset( $this->richie_options['maggio_hostname'])
            ) {
                // invalid configuration
                if (wp_get_referer()) {
                    wp_safe_redirect( wp_get_referer() );
                } else {
                    wp_safe_redirect( get_home_url() );
                }
                exit();
            }

            $hostname = $this->richie_options['maggio_hostname'];
            $uuid = $wp->query_vars['maggio_redirect'];

            try {
                $maggio_service = new Richie_Maggio_Service($hostname);
            } catch ( Exception $e ) {
                return sprintf('<div>%s</div>', __('Failed to fetch issues'));
            }

            $is_free_issue = $maggio_service->is_issue_free( $uuid );

            $required_pmpro_level = isset( $this->richie_options['maggio_required_pmpro_level'] ) ? $this->richie_options['maggio_required_pmpro_level'] : 0;

            if ( !$is_free_issue && !richie_has_maggio_access( $required_pmpro_level ) ) {
                if (wp_get_referer()) {
                    wp_safe_redirect( wp_get_referer() );
                } else {
                    wp_safe_redirect( get_home_url() );
                }
                exit();
            }

            // has access, continue redirect
            $timestamp = time();

            $secret = $this->richie_options['maggio_secret'];

            $return_link = wp_get_referer() ? wp_get_referer() : get_home_url();

            $auth_params = array(
                array( 'key' => 'return_link', 'value' => $return_link )
            );

            $query_string = richie_build_query( $auth_params );

            $hash = richie_generate_signature_hash( $secret, $uuid, $timestamp, $query_string );

            $url = "{$hostname}/_signin/${uuid}/${timestamp}/${hash}" . '?' . $query_string;

            wp_redirect( esc_url($url) );
            exit();
        }
    }

}


