<?php

/**
* The public-facing functionality of the plugin.
*
* @link       https://www.richie.fi
* @since      1.0.0
*
* @package    Richie_News
* @subpackage Richie_News/public
*/


/**
* The public-facing functionality of the plugin.
*
* Defines the plugin name, version, and two examples hooks for how to
* enqueue the public-facing stylesheet and JavaScript.
*
* @package    Richie_News
* @subpackage Richie_News/public
* @author     Markku Uusitupa <markku@richie.fi>
*/
class Richie_News_Public {

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
     * Richie News options
     *
     * @since   1.0.0
     * @access  private
     * @var     array      $richie_news_options
     */

    private $richie_news_options;

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
		$this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->richie_news_options = get_option($plugin_name);
        $sourcelist = get_option($plugin_name . '_sources');
        $this->richie_news_sources = isset($sourcelist['sources']) ? $sourcelist['sources'] : array();
    }

    function feed_route_handler($data) {
        $posts = array();
        foreach( $this->richie_news_sources as $source ) {
            $args = array(
                'numberposts' => $source['number_of_posts'],
            );
            if ( isset( $source['categories'] ) && ! empty( $source['categories'] ) ) {
                $args['cat'] = $source['categories'];
            }
            $source_posts = get_posts($args);
            $posts = array_unique(array_merge($posts, $source_posts), SORT_REGULAR);
        }
        $articles = array();
        foreach ($posts as $content_post) {
            $date = (new DateTime($content_post->post_date_gmt))->format('c');
            $updated_date = (new DateTime($content_post->post_modified_gmt))->format('c');

            array_push($articles, array(
                'id' => $content_post->ID,
                'last_updated' => max($date, $updated_date)
            ));
        }

        $last_updated = strtotime( max( array_column($articles, 'last_updated' ) ) );
        // $if_modified_since = null;
        // if (isset($_ENV['HTTP_IF_MODIFIED_SINCE']))
        //     $if_modified_since = strtotime(substr($_ENV['HTTP_IF_MODIFIED_SINCE'], 5));
        // if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
        //     $if_modified_since = strtotime(substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 5));
        // if ($if_modified_since && $if_modified_since >= $last_updated) {
        //     header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
        //     wp_send_json();
        // }
        header( 'Last-Modified: ' . date( 'D, d M Y H:i:s', $last_updated ) );
        return array('article_ids' => $articles );
    }

    public function article_route_handler($data) {
        $article = new Richie_News_Article($this->richie_news_options);
        $post = get_post($data['id']);
        if ( empty( $post ) ) {
            return new WP_Error( 'no_id', 'Invalid article id', array( 'status' => 404 ) );
        } else {
            $generated_article = $article->generate_article($post);
            return $generated_article;
        }
    }

    public function check_permission() {
        if (isset( $_GET['token']) && $this->richie_news_options['access_token'] === $_GET['token']) {
            return true;
        }
        return false;
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

        register_rest_route( 'richie/v1', '/news', array(
            'methods' => 'GET',
            'callback' => array($this, 'feed_route_handler'),
            'permission_callback' => array($this, 'check_permission')
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

        // register_rest_route( 'richie/v1', '/assets', array(

        // ));
    }

    public function richie_news_template($template) {
        if (isset( $_GET['token']) && $this->richie_news_options['access_token'] === $_GET['token']) {
            if( isset( $_GET['richie_news'] ) ) {
                add_filter( 'pmpro_has_membership_access_filter', '__return_true', 20, 4 );
                require_once plugin_dir_path( __FILE__ ) . '../includes/class-richie-news-template-loader.php';
                $richie_news_template_loader = new Richie_News_Template_Loader;
                $template = $richie_news_template_loader->locate_template( 'richie-news-article.php', false );
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
        * defined in Richie_News_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_News_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-news-public.css', array(), $this->version, 'all' );

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
        * defined in Richie_News_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_News_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-news-public.js', array( 'jquery' ), $this->version, false );

    }


}


