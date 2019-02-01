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

    }

    function feed_route_handler($data) {
        $args = array(
            'numberposts' => -1,
        );
        $posts = get_posts($args);

        $articles = array();
        $article = new Richie_News_Article($this->richie_news_options);
        foreach ($posts as $content_post) {
            $post = get_post($content_post);
            array_push($articles, $article->generate_article($post));
        }

        return array('articles' => $articles);
    }

    /**
    * Register rest api route for richie feed
    *
    * @since    1.0.0
    */
    public function register_richie_rest_api() {
        apply_filters("pmpro_has_membership_access_filter", true);
        register_rest_route( 'richie/v1', '/news', array(
            'methods' => 'GET',
            'callback' => array($this, 'feed_route_handler'),
            ) );
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


