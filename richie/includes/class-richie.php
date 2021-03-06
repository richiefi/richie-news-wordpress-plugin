<?php

/**
* The file that defines the core plugin class
*
* A class definition that includes attributes and functions used across both the
* public-facing side of the site and the admin area.
*
* @link       https://www.richie.fi
* @since      1.0.0
*
* @package    Richie
* @subpackage Richie/includes
*/

/**
* The core plugin class.
*
* This is used to define internationalization, admin-specific hooks, and
* public-facing site hooks.
*
* Also maintains the unique identifier of this plugin as well as the current
* version of the plugin.
*
* @since      1.0.0
* @package    Richie
* @subpackage Richie/includes
* @author     Markku Uusitupa <markku@richie.fi>
*/
class Richie {

    /**
    * The loader that's responsible for maintaining and registering all hooks that power
    * the plugin.
    *
    * @since    1.0.0
    * @access   protected
    * @var      Richie_Loader    $loader    Maintains and registers all hooks for the plugin.
    */
    protected $loader;

    /**
    * The unique identifier of this plugin.
    *
    * @since    1.0.0
    * @access   protected
    * @var      string    $plugin_name    The string used to uniquely identify this plugin.
    */
    protected $plugin_name;

    /**
    * The current version of the plugin.
    *
    * @since    1.0.0
    * @access   protected
    * @var      string    $version    The current version of the plugin.
    */
    protected $version;

    /**
    * Define the core functionality of the plugin.
    *
    * Set the plugin name and the plugin version that can be used throughout the plugin.
    * Load the dependencies, define the locale, and set the hooks for the admin area and
    * the public-facing side of the site.
    *
    * @since    1.0.0
    */
    public function __construct() {
        if ( defined( 'Richie_VERSION' ) ) {
            $this->version = Richie_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'richie';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    /**
    * Load the required dependencies for this plugin.
    *
    * Include the following files that make up the plugin:
    *
    * - Richie_Loader. Orchestrates the hooks of the plugin.
    * - Richie_i18n. Defines internationalization functionality.
    * - Richie_Admin. Defines all hooks for the admin area.
    * - Richie_Public. Defines all hooks for the public side of the site.
    *
    * Create an instance of the loader which will be used to register the hooks
    * with WordPress.
    *
    * @since    1.0.0
    * @access   private
    */
    private function load_dependencies() {

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';
        /**
         * Richie dependencies
         */

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/functions.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-custom-taxonomies.php';

        /**
        * The class responsible for orchestrating the actions and filters of the
        * core plugin.
        */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-loader.php';

        /**
        * The class responsible for defining internationalization functionality
        * of the plugin.
        */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-i18n.php';

        /**
        * The class responsible for defining all actions that occur in the admin area.
        */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-richie-admin.php';

        /**
        * The class responsible for defining all actions that occur in the public-facing
        * side of the site.
        */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-richie-public.php';

        $this->loader = new Richie_Loader();

    }

    /**
    * Define the locale for this plugin for internationalization.
    *
    * Uses the Richie_i18n class in order to set the domain and to register the hook
    * with WordPress.
    *
    * @since    1.0.0
    * @access   private
    */
    private function set_locale() {

        $plugin_i18n = new Richie_i18n();

        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

    }

    /**
    * Register all of the hooks related to the admin area functionality
    * of the plugin.
    *
    * @since    1.0.0
    * @access   private
    */
    private function define_admin_hooks() {

        $plugin_admin      = new Richie_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );

        // Add Settings link to the plugin.
        $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
        $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

        // Custom taxonomies.
        $custom_taxonomies = new Richie_Custom_Taxonomies();
        $this->loader->add_action( 'admin_init', $custom_taxonomies, 'register_taxonomies', 5 );

        // Options.
        $this->loader->add_action( 'admin_init', $plugin_admin, 'options_update' );

        // allow origin
        $this->loader->add_filter( 'allowed_http_origins', $plugin_admin, 'add_allowed_origin' );
    }

    /**
    * Register all of the hooks related to the public-facing functionality
    * of the plugin.
    *
    * @since    1.0.0
    * @access   private
    */
    private function define_public_hooks() {

        $plugin_public = new Richie_Public( $this->get_plugin_name(), $this->get_version() );
        // Custom taxonomies.
        $custom_taxonomies = new Richie_Custom_Taxonomies();
        $this->loader->add_action( 'init', $custom_taxonomies, 'register_taxonomies', 5 );

        // Routes.
        $this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
        $this->loader->add_action( 'init', $plugin_public, 'register_redirect_route' );
        $this->loader->add_action( 'rest_api_init', $plugin_public, 'register_richie_rest_api' );

        // Other.
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_filter( 'template_include', $plugin_public, 'richie_template' );

        $this->loader->add_action( 'richie_cron_hook', $plugin_public, 'refresh_maggio_cache' );

        if ( ! wp_next_scheduled( 'richie_cron_hook' ) ) {
            wp_schedule_event( time(), 'hourly', 'richie_cron_hook' );
        }
    }

    /**
    * Run the loader to execute all of the hooks with WordPress.
    *
    * @since    1.0.0
    */
    public function run() {
        $this->loader->run();
    }

    /**
    * The name of the plugin used to uniquely identify it within the context of
    * WordPress and to define internationalization functionality.
    *
    * @since     1.0.0
    * @return    string    The name of the plugin.
    */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
    * The reference to the class that orchestrates the hooks with the plugin.
    *
    * @since     1.0.0
    * @return    Richie_Loader    Orchestrates the hooks of the plugin.
    */
    public function get_loader() {
        return $this->loader;
    }

    /**
    * Retrieve the version number of the plugin.
    *
    * @since     1.0.0
    * @return    string    The version number of the plugin.
    */
    public function get_version() {
        return $this->version;
    }

}
