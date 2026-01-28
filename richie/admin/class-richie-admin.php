<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie
 * @subpackage Richie/admin
 */

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-post-type.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Richie
 * @subpackage Richie/admin
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Admin {

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
     * Option name for general settings
     *
     * @var string
     */
    private $settings_option_name;

    /**
     * Option name for source list
     *
     * @var string
     */
    private $sources_option_name;

    /**
     * Option name for asset feed
     *
     * @var string
     */
    private $assets_option_name;

    /**
     * Slug for settings page, used in /wp-admin/options-general.php?page=<slug>
     *
     * @var string
     */
    private $settings_page_slug;

    /**
     * Available news list layout names
     *
     * @var array
     */
    private $available_layout_names;

    /**
     * If set, dump source database
     *
     * @var boolean
     */
    private $debug_sources;

    /**
     * Option name for ad slots
     *
     * @var string
     */
    private $adslots_option_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name    The name of this plugin.
     * @param string $version       The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name            = $plugin_name;
        $this->version                = $version;
        $this->settings_page_slug     = $plugin_name;
        $this->settings_option_name   = $plugin_name;
        $this->sources_option_name    = $plugin_name . 'news_sources';
        $this->assets_option_name     = $plugin_name . '_assets';
        $this->adslots_option_name    = $plugin_name . '_adslots';
        $this->available_layout_names = array(
            'big',
            'small',
            'small_group_item',
            'featured',
            'full_width_text',
            'text_left_square_thumb_right',
            'none',
        );
        add_action( 'added_option', array( __CLASS__, 'maybe_clear_alloptions_cache' ) );
        add_action( 'updated_option', array( __CLASS__, 'maybe_clear_alloptions_cache' ) );
        add_action( 'deleted_option', array( __CLASS__, 'maybe_clear_alloptions_cache' ) );

        add_action( 'wp_ajax_list_update_order', array( $this, 'order_source_list' ) );
        add_action( 'wp_ajax_remove_source_item', array( $this, 'remove_source_item' ) );
        add_action( 'wp_ajax_set_checkbox_field', array( $this, 'set_checkbox_field' ) );
        add_action( 'wp_ajax_publish_source_changes', array( $this, 'publish_source_changes' ) );
        add_action( 'wp_ajax_revert_source_changes', array( $this, 'revert_source_changes' ) );
        add_action( 'wp_ajax_remove_ad_slot', array( $this, 'remove_ad_slot' ) );
        add_action( 'wp_ajax_get_adslot_data', array( $this, 'get_adslot_data' ) );

        add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );
        add_action( 'richie_plugin_add_settings_sections', array( $this, 'generate_settings' ) );
    }

    /**
     * Fix a race condition in options caching
     *
     * See https://core.trac.wordpress.org/ticket/31245
     * and https://github.com/tillkruss/redis-cache/issues/58
     *
     */
    public static function maybe_clear_alloptions_cache( $option ) {
        if ( wp_installing() === false ) {
            $alloptions = wp_load_alloptions();

            // If option is part of the alloptions collection then clear it.
            if ( array_key_exists( $option, $alloptions ) ) {
                wp_cache_delete( $option, 'options' );
            }
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-admin.css', array(), $this->get_version_id(), 'all' );
        wp_enqueue_style( 'wp-color-picker' );
    }

    /**
     * Get version string for scripts and styles
     * If debug mode, return time to prevent caching
     *
     * @return string
     */
    public function get_version_id() {
        if ( WP_DEBUG ) {
            return time();
        }
        return $this->version;
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook) {
        /* These scripts are only needed on actual plugin settings page */
        if ( 'settings_page_richie' === $hook ) {
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            add_thickbox();
            wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
            wp_enqueue_script( 'suggest' );
        }

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-admin.js', array( 'jquery', 'wp-color-picker' ), $this->get_version_id(), false );

        wp_localize_script(
            $this->plugin_name,
            'richie_ajax',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'security' => wp_create_nonce( 'richie-security-nonce' ),
            ]
        );
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        /*
         * Add a settings page for this plugin to the Settings menu.
         *
         * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
         *
         *        Administration Menus: http://codex.wordpress.org/Administration_Menus
         *
         */
        add_options_page( 'Richie News Settings', 'Richie News', 'manage_options', $this->settings_page_slug, array( $this, 'load_admin_page_content' ) );
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since 1.0.0
     * @param array $links Existing links array.
     */
    public function add_action_links( $links ) {
        /*
        *  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
        */
        $settings_link = array(
            '<a href="' . admin_url( 'options-general.php?page=' . $this->settings_page_slug ) . '">' . __( 'Settings', 'richie' ) . '</a>',
        );
        return array_merge( $settings_link, $links );

    }

    /**
     * Load admin page
     *
     * @return void
     */
    public function load_admin_page_content() {
        require_once plugin_dir_path( __FILE__ ) . 'partials/richie-admin-display.php';
    }

    public function add_allowed_origin( $origins ) {
        $origins[] = 'richienews://';
        return $origins;
    }

    /**
     * Function that will check if value is a valid HEX color.
     *
     * @param string $value Hex color value
     * @return boolean
     */
    public function check_color( $value ) {
        if ( preg_match( '/^#[a-f0-9]{6}$/i', $value ) ) {
            return true;
        }

        return false;
    }

    /**
     * Validate general settings
     *
     * @param array $input Data from settings form.
     * @return array Validated values
     */
    public function validate_settings( $input ) {
        $valid = array();

        if ( isset( $input['access_token'] ) && ! empty( $input['access_token'] ) ) {
            $valid['access_token'] = sanitize_text_field( $input['access_token'] );
        }

        $valid['search_list_layout_style']    = in_array( $input['search_list_layout_style'], $this->available_layout_names, true ) ? sanitize_text_field( $input['search_list_layout_style'] ) : 'small';

        return $valid;
    }

    /**
     * Validate news source item
     *
     * @param array $input Data from source form.
     * @return array Updated source data structure
     */
    public function validate_source( $input ) {
        $current_option = get_option( $this->sources_option_name );

        $sources = isset( $current_option['sources'] ) ? $current_option['sources'] : array();
        $next_id = 0;

        foreach ( $sources as $source ) {
            if ( $source['id'] >= $next_id ) {
                $next_id = $source['id'] + 1;
            }
        }

        $available_types = Richie_Post_Type::available_post_types();

        if (
            isset( $input['source_name'] ) &&
            isset( $input['number_of_posts'] ) &&
            ! empty( $input['source_name'] ) &&
            intval( $input['number_of_posts'] ) > 0 &&
            isset( $input['article_set'] ) &&
            ! empty( $input['article_set'] )
        ) {
            $error = false;

            $source = array(
                'id'              => $next_id,
                'name'            => sanitize_text_field( $input['source_name'] ),
                'number_of_posts' => intval( $input['number_of_posts'] ),
                'order_by'        => sanitize_text_field( $input['order_by'] ),
                'order_direction' => $input['order_direction'] === 'DESC' ? 'DESC' : 'ASC',
                'article_set'     => intval( $input['article_set'] ),
            );

            if ( ! empty( $input['herald_featured_module_title'] ) && empty( $input['herald_featured_post_id'] ) ) {
                add_settings_error(
                    $this->sources_option_name,
                    esc_attr( 'sources_error' ),
                    __( 'Herald module name given but post id was empty', 'richie' ),
                    'error'
                );
                $error = true;
            }

            if ( in_array( $input['post_type'], $available_types, true ) ) {
                $source['post_type'] = $input['post_type'];
            } else {
                add_settings_error(
                    $this->sources_option_name,
                    esc_attr( 'sources_error' ),
                    __( 'Given post type not supported', 'richie' ),
                    'error'
                );
                $error = true;
            }

            if ( isset( $input['herald_featured_post_id'] ) && ! empty( $input['herald_featured_post_id'] ) ) {
                $source['herald_featured_post_id'] = intval( $input['herald_featured_post_id'] );
                if ( ! empty( $input['herald_featured_module_title'] ) ) {
                    $source['herald_featured_module_title'] = strval( $input['herald_featured_module_title'] );
                }
            }

            if ( isset( $input['source_categories'] ) && ! empty( $input['source_categories'] ) ) {
                $source['categories'] = $input['source_categories'];
            }

            if ( isset( $input['source_tags'] ) && ! empty( $input['source_tags'] ) ) {
                $source['tags'] = wp_parse_slug_list( $input['source_tags'] );
            }

            $source['list_layout_style'] = in_array( $input['list_layout_style'], $this->available_layout_names, true ) ? sanitize_text_field( $input['list_layout_style'] ) : 'none';

            if ( isset( $input['list_group_title'] ) && ! empty( $input['list_group_title'] ) ) {
                $source['list_group_title'] = sanitize_text_field( $input['list_group_title'] );
            }

            if ( isset( $input['disable_summary'] ) && intval( $input['disable_summary'] ) === 1 ) {
                $source['disable_summary'] = true;
            }

            if ( isset( $input['allow_duplicates'] ) && intval( $input['allow_duplicates'] ) === 1 ) {
                $source['allow_duplicates'] = true;
            }

            if ( isset( $input['max_age'] ) && $input['max_age'] !== 'All time' ) {
                $source['max_age'] = sanitize_text_field( $input['max_age'] );
            }

            if ( isset( $input['background_color'] ) ) {
                $background_color = sanitize_text_field( $input['background_color'] );
                if ( $this->check_color( $background_color ) ) {
                    $source['background_color'] = ltrim( $background_color, '#' );
                }
            }

            if ( false === $error ) {
                // include new source
                $sources[ $next_id ] = $source;
            }
        } else {
            add_settings_error(
                $this->sources_option_name,
                esc_attr( 'sources_error' ),
                __( 'Name and number of posts are required', 'richie' ),
                'error'
            );
        }

        $current_option['sources'] = $sources;
        $current_option['updated'] = time();
        return $current_option;
    }

    /**
     * Validate asset feed data
     *
     * @param array $input Form data.
     * @return array Valid asset feed array
     */
    public function validate_assets( $input ) {
        if ( ! isset( $input['data'] ) ) {
            add_settings_error(
                $this->assets_option_name,
                esc_attr( 'assets_error' ),
                __( 'No data found', 'richie' ),
                'error'
            );
            return get_option( $this->assets_option_name );
        }

        $assets = json_decode( $input['data'] );

        if ( json_last_error() !== JSON_ERROR_NONE || $assets === false ) {
            $error = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : 'Unknown error';
            add_settings_error(
                $this->assets_option_name,
                esc_attr( 'assets_error' ),
                /* translators: %s is replaced with the error */
                sprintf( __( 'Failed to parse json, unable to save: %s', 'richie' ), $error ),
                'error'
            );
            return get_option( $this->assets_option_name );
        } else {
            delete_transient(RICHIE_ASSET_CACHE_KEY);
            return $assets;
        }
    }

    /**
     * Validate ad slot data
     *
     * @param array $input Form data.
     * @return array Ad slots data structure
     */
    public function validate_adslot( $input ) {
        $current_option = get_option( $this->adslots_option_name );
        $adslots        = isset( $current_option['slots'] ) ? $current_option['slots'] : array();
        $slot           = array();
        $error          = null;
        $ad_data        = null;

        if ( empty( $input['article_set'] ) ) {
            $error = 'Invalid article_set';
        }

        if ( empty( $input['adslot_position_index'] ) || ! is_numeric( $input['adslot_position_index'] ) || intval( $input['adslot_position_index'] ) < 1 ) {
            $error = 'Invalid slot position index, it must be 1 or bigger integer';
        }

        if ( empty( $input['adslot_provider'] ) ) {
            $error = 'Invalid input provider';
        }

        if ( ! empty( $input['ad_data'] ) ) {
            $ad_data = json_decode( $input['ad_data'] );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $error = sprintf( 'JSON parsing failed for ad data: %s', json_last_error_msg() );
            }
        }

        if ( $error !== null ) {
            add_settings_error(
                $this->assets_option_name,
                esc_attr( 'adslot_error' ),
                /* translators: %s is replaced with the error */
                sprintf( __( 'Failed to save adslot, validation failed: %s', 'richie' ), $error ),
                'error'
            );
            return $current_option;
        }
        $index             = intval( $input['adslot_position_index'] );
        $article_set       = intval( $input['article_set'] );
        $current_set_slots = isset( $adslots[ $article_set ] ) ? $adslots[ $article_set ] : array();

        $current_set_slots[ $index ] = array(
            'index'       => $index,
            'article_set' => intval( $input['article_set'] ),
            'updated'     => time(),
            'attributes'  => array(
                'id'                => wp_generate_uuid4(),
                'list_layout_style' => 'ad',
                'ad_provider'       => sanitize_text_field( $input['adslot_provider'] ),
                'ad_data'           => $ad_data,
            ),
        );

        $adslots[ $article_set ] = $current_set_slots;
        $current_option['slots'] = $adslots;

        return $current_option;
    }

    /**
     * Include admin notices
     *
     * @return void
     */
    public function add_admin_notices() {
        if ( $this->has_unpublished_changes() ) {
            ?>
            <div class="richie-notice notice notice-warning">
            <p>
                <strong>Richie: <?php esc_html_e( 'News sources have unpublished changes.', 'richie' ); ?></strong>
                <span>
                <a class="button-link" href="#" id="publish-sources"><?php esc_html_e( 'Publish now', 'richie' ); ?></a> |
                <a class="button-link" href="#" id="revert-source-changes"><?php esc_html_e( 'Revert changes', 'richie' ); ?></a>
                </span>
            </p>
            </div>
            <?php
        }

    }


    /**
     * Setup and register options using settings api
     *
     * @return void
     */
    public function options_update() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-settings-section.php';

        // Run on admin_init.
        $this->debug_sources = false;

        if ( isset( $_GET['richie_debug_sources'] ) && $_GET['richie_debug_sources'] === '1' ) {
            $this->debug_sources = true;
        }

        if ( get_option( $this->sources_option_name ) === false ) {
            add_option(
                $this->sources_option_name,
                array(
                    'sources' => array(),
                    'version' => 2,
                    'updated' => time(),
                )
            );
        }

        if ( get_option( $this->adslots_option_name ) === false ) {
            add_option(
                $this->adslots_option_name,
                array(
                    'slots'   => array(),
                    'updated' => time(),
                )
            );
        }

        $sources = get_option( $this->sources_option_name );

        if ( isset( $sources['sources'] ) ) {
            if ( isset( $_GET['richie_migrate_sources'] ) && $_GET['richie_migrate_sources'] === '1' && ( ! isset( $sources['version'] ) || $sources['version'] < 2 ) ) {
                // Migrate sources to associative array using id as key.
                $new_sources     = [];
                $current_sources = $sources['sources'];

                foreach ( $current_sources as $s ) {
                    $new_sources[ $s['id'] ] = $s;
                }
                $sources['sources'] = $new_sources;
                $sources['version'] = 2;
                $sources['updated'] = time();
                update_option( $this->sources_option_name, $sources );
            }
        }

        register_setting(
            $this->settings_option_name,
            $this->settings_option_name,
            array(
                'sanitize_callback' => array( $this, 'validate_settings' ),
            )
        );

        register_setting(
            $this->sources_option_name,
            $this->sources_option_name,
            array(
                'sanitize_callback' => array( $this, 'validate_source' ),
            )
        );

        register_setting(
            $this->assets_option_name,
            $this->assets_option_name,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'validate_assets' ),
            )
        );

        register_setting(
            $this->adslots_option_name,
            $this->adslots_option_name,
            array(
                'sanitize_callback' => array( $this, 'validate_adslot' ),
            )
        );

        $options = get_option( $this->settings_option_name );
        if ( ! isset( $options['access_token'] ) ) {
            $options['access_token'] = bin2hex( random_bytes( 16 ) );
            update_option( $this->settings_option_name, $options );
        }

        if ( ! isset( $options['search_list_layout_style'] ) ) {
            $options['search_list_layout_style'] = 'none';
            update_option( $this->settings_option_name, $options );
        }

    }

    /**
     * Generate settings groups and fields
     *
     * @return void
     */
    public function generate_settings() {
        $options = get_option( $this->settings_option_name );

        $general_section_name = 'richie_general';
        $paywall_section_name = 'richie_paywall';
        $sources_section_name = 'richie_news_source';
        $assets_section_name  = 'richie_feed_assets';
        $search_section_name  = 'richie_search';
        $adslots_section_name = 'richie_ad_slot';

        // Create general section.
        $section = new Richie_Settings_Section( $general_section_name, __( 'General settings', 'richie' ), $this->settings_option_name );
        $section->add_field( 'access_token', __( 'Access token', 'richie' ), 'input_field', array( 'value' => $options['access_token'] ) );

        // Create search settings section.
        $section  = new Richie_Settings_Section( $search_section_name, __( 'Search API settings', 'richie' ), $this->settings_option_name );
        $selected = isset( $options['search_list_layout_style'] ) ? $options['search_list_layout_style'] : 'none';
        $section->add_field( 'search_list_layout_style', __( 'Search list layout', 'richie' ), 'select_field', array( 'options' => $this->available_layout_names, 'selected' => $selected, 'required' => true ) );

        // Create source section.
        $source_section = new Richie_Settings_Section( $sources_section_name, __( 'Basic data', 'richie' ), $this->sources_option_name );
        $source_section->add_field( 'source_name', __( 'Name', 'richie' ), 'input_field' );
        $source_section->add_field( 'richie_article_set', __( 'Article set', 'richie' ), 'article_set' );
        $source_section->add_field( 'number_of_posts', __( 'Number of posts', 'richie' ), 'input_field', array( 'type' => 'number', 'class' => 'small-text', 'description' => __( 'Number of posts included in the feed', 'richie' ) ) );

        if ( defined( 'HERALD_THEME_VERSION' ) ) {
            $source_herald_section = new Richie_Settings_Section( $sources_section_name . 'herald', __( 'Herald featured module', 'richie' ), $this->sources_option_name );
            $front_page            = (int) get_option( 'page_on_front' );
            $description           = __( 'Fetch posts from herald modules of this page id. Rest of filters will be ignored.', 'richie' );

            if ( $front_page > 0 ) {
                $description = sprintf( '%s %s %u.', $description, __( 'Current front page id is', 'richie' ), $front_page );
            }

            $source_herald_section->add_field( 'herald_featured_post_id', __( 'Herald page ID', 'richie' ), 'input_field', array( 'description' => $description, 'class' => '' ) );
            $source_herald_section->add_field( 'herald_featured_module_title', __( 'Herald module title', 'richie' ), 'input_field', array( 'description' => __('Module title from the given page to be used as a source. If empty, defaults to first featured type module.', 'richie'), 'class' => '' ) );
        }

        $source_section->add_field( 'post_type', __( 'Post type', 'richie' ), 'select_field', array( 'options' => Richie_Post_Type::available_post_types( 'object' ), 'required' => true ) );

        $source_filters = new Richie_Settings_Section( $sources_section_name . 'filters', __( 'Filters', 'richie' ), $this->sources_option_name );
        $source_filters->add_field( 'source_categories', __( 'Categories', 'richie' ), 'category_list' );
        $source_filters->add_field( 'source_tags', __( 'Tags', 'richie' ), 'tag_field' );
        $source_filters->add_field( 'order_by', __( 'Order by', 'richie' ), 'order_by' );
        $source_filters->add_field( 'order_dir', __( 'Order direction', 'richie' ), 'order_direction' );
        $source_filters->add_field( 'max_age', __( 'Post max age', 'richie' ), 'max_age' );

        $source_options = new Richie_Settings_Section( $sources_section_name . 'options', __( 'Options', 'richie' ), $this->sources_option_name );
        $source_options->add_field( 'list_layout_style', __( 'List layout', 'richie' ), 'select_field', array( 'options' => $this->available_layout_names, 'required' => true ) );
        $source_options->add_field( 'list_group_title', __( 'List group title', 'richie' ), 'input_field', array( 'description' => __( 'Header to display before the story, useful on the first small_group_item of a group', 'richie' ) ) );
        $source_options->add_field( 'background_color', __( 'Background color', 'richie' ), 'color_picker', array( 'description' => __( 'Background color to be used with layout types. Not all layout types support this.', 'richie' ) ) );
        $source_options->add_field( 'allow_duplicates', __( 'Allow duplicates', 'richie' ), 'checkbox', array( 'description' => __( 'Allow duplicate articles in this source', 'richie' ) ) );
        $source_options->add_field( 'disable_summary', __( 'Disable article summary', 'richie' ), 'checkbox', array( 'description' => __( 'Do not show summary text in news list', 'richie' ) ) );

        // Create adslots section.
        $slot_index_description = __( 'Specify an index number for the ad slot placement in the article set feed. 1-based index, so 1 means the first item on the feed. Existing index for the article set is overwritten.', 'richie' );

        $section = new Richie_Settings_Section( $adslots_section_name, __( 'Add new ad slot', 'richie' ), $this->adslots_option_name );
        $section->add_field( 'richie_article_set', __( 'Article set', 'richie' ), 'article_set' );
        $section->add_field( 'adslot_position_index', __( 'Slot position', 'richie' ), 'input_field', array( 'description' => $slot_index_description, 'class' => '' ) );

        $ad_providers = array( 'smart', 'google', 'readpeak' );
        $section->add_field( 'adslot_provider', __( 'Ad provider', 'richie' ), 'select_field', array( 'options' => $ad_providers ) );
        $section->add_field( 'adslot_ad_data', __( 'Ad data', 'richie' ), 'adslot_ad_data_editor' );

        // Create assets section.
        $section = new Richie_Settings_Section( $assets_section_name, __( 'Asset feed', 'richie' ), $this->assets_option_name );
        $section->add_field( 'richie_news_assets', __( 'Assets', 'richie' ), 'asset_editor' );
    }

    /**
     * Ajax hook for ordering news source list.
     * Sends json response
     *
     * @return void
     */
    public function order_source_list() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['source_items'] ) ) {
            echo 'Missing source list';
            wp_die();
        }
        $option       = get_option( $this->sources_option_name );
        $current_list = isset( $option['sources'] ) ? $option['sources'] : array();
        $new_order    = array_map( 'intval', wp_unslash( $_POST['source_items'] ) );
        $new_list     = array();

        if ( count( $current_list ) !== count( $new_order ) ) {
            $error = new WP_Error( '-1', 'Current list and received list size doesn\'t match' );
            wp_send_json_error( $error, 400 );
        }

        $new_list = array_replace( array_flip( $new_order ), $current_list );

        // Sanitize, make sure that array sizes match and keys matches.
        if ( count( $current_list ) === count( $new_list ) && empty( array_diff_key( $current_list, $new_list ) ) ) {
            $option['sources'] = $new_list;
            $option['updated'] = time();
            // Skip validate source.
            remove_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
            $updated = update_option( $this->sources_option_name, $option );
            add_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
            wp_send_json_success( array( 'updated' => $updated ) );
        } else {
            $error = new WP_Error( '-1', 'Current list and sorted list doesn\'t match, something wrong' );
            wp_send_json_error( $error, 500 );
        }

    }

    /**
     * Ajax hook for removing source item. Sends json response.
     *
     * @return void
     */
    public function remove_source_item() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['source_id'] ) ) {
            echo 'Missing source id';
            wp_die();
        }

        $source_id    = intval( $_POST['source_id'] );
        $option       = get_option( $this->sources_option_name );
        $current_list = isset( $option['sources'] ) ? $option['sources'] : array();
        $deleted      = false;

        if ( isset( $current_list[ $source_id ] ) ) {
            unset( $current_list[ $source_id ] ); // Delete from Array.
            $deleted = true;
        }

        $option['sources'] = $current_list;
        $option['updated'] = time();

        // Skip validate source.
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        $updated = update_option( $this->sources_option_name, $option );
        add_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        wp_send_json( array( 'deleted' => $updated && $deleted ) );
    }

    /**
     * Ajax hook for setting source feed boolean values. Sends json response.
     *
     * @return void
     */
    public function set_checkbox_field() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['source_id'] ) ) {
            echo 'Missing source id';
            wp_die();
        }

        if ( ! isset( $_POST['field_name'] ) ) {
            echo 'Missing field name';
            wp_die();
        }

        $field_name   = strval( $_POST['field_name'] );
        $source_id    = intval( $_POST['source_id'] );
        $is_checked   = 'true' === $_POST['checked'];
        $option       = get_option( $this->sources_option_name );
        $current_list = isset( $option['sources'] ) ? $option['sources'] : array();

        if ( isset( $current_list[ $source_id ] ) ) {
            if ( $is_checked ) {
                $current_list[ $source_id ][ $field_name ] = $is_checked;
            } else {
                unset( $current_list[ $source_id ][ $field_name ] );
            }
        }

        $option['sources'] = $current_list;
        $option['updated'] = time();

        // Skip validate source.
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        $updated = update_option( $this->sources_option_name, $option );
        add_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        wp_send_json( array( 'updated' => $updated ) );
    }


    /**
     * Ajax hook for publishing source changes. Sends json response.
     *
     * @return void
     */
    public function publish_source_changes() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        $option                 = get_option( $this->sources_option_name );
        $sources                = ! empty( $option['sources'] ) ? $option['sources'] : array();
        $option['published']    = $sources;
        $option['published_at'] = time();

        remove_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        $updated = update_option( $this->sources_option_name, $option );
        add_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        wp_send_json( array( 'updated' => $updated ) );
    }

    /**
     * Ajax hook for reverting source changes. Sends json response.
     *
     * @return void
     */
    public function revert_source_changes() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        $option            = get_option( $this->sources_option_name );
        $published_sources = ! empty( $option['published'] ) ? $option['published'] : array();
        $option['sources'] = $published_sources;

        remove_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        $updated = update_option( $this->sources_option_name, $option );
        add_filter( 'sanitize_option_' . $this->sources_option_name, array( $this, 'validate_source' ) );
        wp_send_json( array( 'updated' => $updated ) );
    }

    /**
     * Check if sources have unpublished changes
     *
     * @return boolean
     */
    public function has_unpublished_changes() {
        $sources = get_option( $this->sources_option_name );

        if ( $sources !== false && isset( $sources['sources'] ) ) {
            if ( empty( $sources['published'] ) || $sources['sources'] !== $sources['published'] ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ajax hook for removing ad slot. Sends json response.
     *
     * @return void
     */
    public function remove_ad_slot() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['index'] ) || ! isset( $_POST['article_set_id'] ) ) {
            wp_send_json_error( 'Missing arguments', 400 );
        }
        $option      = get_option( $this->adslots_option_name );
        $article_set = intval( $_POST['article_set_id'] );
        $index       = intval( $_POST['index'] );
        if ( isset( $option['slots'] ) && isset( $option['slots'][ $article_set ] ) ) {
            $slots = $option['slots'][ $article_set ];
            if ( isset( $slots[ $index ] ) ) {
                unset( $slots[ $index ] );
                remove_filter( 'sanitize_option_' . $this->adslots_option_name, array( $this, 'validate_adslot' ) );
                if ( ! empty( $slots ) ) {
                    $option['slots'][ $article_set ] = $slots;
                } else {
                    unset( $option['slots'][ $article_set ] );
                }
                $updated = update_option( $this->adslots_option_name, $option );
                add_filter( 'sanitize_option_' . $this->adslots_option_name, array( $this, 'validate_adlot' ) );
                wp_send_json( array( 'deleted' => $updated ) );
            }
        } else {
            wp_send_json_error( 'Failed to remove slot', 500 );
        }
    }

    /**
     * Ajax hook for getting adslot data. Sends json response.
     *
     * @return void
     */
    public function get_adslot_data() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['index'] ) || ! isset( $_POST['article_set_id'] ) ) {
            wp_send_json_error( 'Missing arguments', 400 );
        }
        $option      = get_option( $this->adslots_option_name );
        $article_set = intval( $_POST['article_set_id'] );
        $index       = intval( $_POST['index'] );
        if ( isset( $option['slots'] ) && isset( $option['slots'][ $article_set ] ) ) {
            $slots = $option['slots'][ $article_set ];
            if ( isset( $slots[ $index ] ) ) {
                wp_send_json( wp_json_encode( $slots[ $index ] ) );
            }
        }

        wp_send_json_error( 'Not found', 404 );
    }

    /**
     * Render source list.
     *
     * @return void
     */
    public function source_list() {
        $options = get_option( $this->sources_option_name );
        if ( $this->debug_sources ) {
            echo '<pre>';
            print_r( $options );
            echo '</pre>';
        }
        if ( isset( $options['sources'] ) && ! empty( $options['sources'] ) ) :
            ?>
            <?php if ( ! empty( $options['published_at'] ) ) : ?>
            <span><?php esc_html_e( 'Last publish time:', 'richie' ); ?> <em><?php echo esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $options['published_at'] ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></em></span>
            <?php endif; ?>
            <a class="button-primary" style="float:right; margin-bottom: 1em;" href="#" id="publish-sources"><?php esc_html_e( 'Publish', 'richie' ); ?></a>
            <table class="widefat feed-source-list sortable-list">
                <thead>
                    <th style="width: 30px;"></th>
                    <th><?php echo esc_html_x( 'ID', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Article Set', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Name', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Post type', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Categories', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Posts', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Order', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Max age', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'List layout', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Background', 'column name', 'richie' ); ?></th>
                    <th style="text-align: center"><?php echo esc_html_x( 'Disable summary', 'column name', 'richie' ); ?></th>
                    <th style="text-align: center"><?php echo esc_html_x( 'Allow duplicates', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Actions', 'column name', 'richie' ); ?></th>
                </thead>
                <tbody>
                <?php
                foreach ( $options['sources'] as $key => $source ) {
                    $category_names = array();
                    if ( ! empty( $source['categories'] ) ) {
                        $categories = get_categories(
                            array(
                                'include' => $source['categories'],
                            )
                        );
                        foreach ( $categories as $cat ) {
                            array_push( $category_names, $cat->name );
                        }
                    } else {
                        array_push( $category_names, 'All categories' );
                    }

                    $article_set     = get_term( $source['article_set'] );
                    $herald_featured = isset( $source['herald_featured_post_id'] );

                    if ( $herald_featured ) {
                        $herald_category_name = 'Herald module';
                        if ( isset( $source['herald_featured_module_title'] ) ) {
                            $herald_category_name = $herald_category_name . ': ' . $source['herald_featured_module_title'];
                        } else {
                            $herald_category_name = 'Herald featured module';
                        }
                    }

                    $post_type = isset( $source['post_type'] ) ? $source['post_type'] : 'post';

                    ?>
                    <tr id="source-<?php echo esc_attr( $source['id'] ); ?>" data-source-id="<?php echo esc_attr( $source['id'] ); ?>" class="source-item">
                        <td><span class="dashicons dashicons-menu"></span></td>
                        <td><?php echo esc_html( $source['id'] ); ?></td>
                        <td><?php echo esc_html( $article_set->name ); ?></td>
                        <td><?php echo esc_html( $source['name'] ); ?></td>
                        <td><?php echo esc_html( $post_type ); ?></td>
                        <td>
                            <?php
                            echo ! $herald_featured ? esc_html( implode( ', ', $category_names ) ) : esc_html($herald_category_name);
                            if ( ! empty( $source['tags'] ) ) {
                                echo '<br/>Tags: ' . esc_html( implode( ', ', $source['tags'] ) );
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $source['number_of_posts'] ); ?></td>
                        <td><?php echo isset( $source['order_by'] ) && ! $herald_featured ? esc_html( "{$source['order_by']} {$source['order_direction']}" ) : ''; ?> </td>
                        <td><?php echo isset( $source['max_age'] ) ? esc_html( $source['max_age'] ) : 'All time'; ?></td>
                        <td><?php echo isset( $source['list_layout_style'] ) ? esc_html( $source['list_layout_style'] ) : 'none'; ?></td>
                        <td><div style="display:block; height: 20px; width: 20px; margin: 0 auto; background-color: #<?php echo isset( $source['background_color'] ) ? esc_html( $source['background_color'] ) : 'transparent'; ?>" /></td>
                        <td style="text-align: center">
                            <input class="disable-summary" type="checkbox" <?php echo isset( $source['disable_summary'] ) && $source['disable_summary'] === true ? 'checked' : ''; ?>>
                        </td>
                        <td style="text-align: center">
                            <input class="allow-duplicates" type="checkbox" <?php echo isset( $source['allow_duplicates'] ) && $source['allow_duplicates'] === true ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <a href="#" class="remove-source-item"><?php esc_html_e( 'Remove', 'richie' ); ?></a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
        else :
            printf( '<em>%s</em>', esc_html__( 'No sources configured. Add news feed sources with the form below.', 'richie' ) );
        endif;
    }

    /**
     * Render adslot list
     *
     * @return void
     */
    public function adslot_list() {
        $options = get_option( $this->adslots_option_name );

        if ( isset( $options['slots'] ) && ! empty( $options['slots'] ) ) :
            ?>
            <table class="widefat slot-list">
                <thead>
                    <th><?php echo esc_html_x( 'Article Set', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Index', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'ID', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Ad provider', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Ad data', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Actions', 'column name', 'richie' ); ?></th>
                </thead>
                <tbody>
                <?php
                foreach ( $options['slots'] as $article_set_id => $slots ) {
                    $article_set = get_term( $article_set_id );
                    ksort( $slots ); // Sort by array key, so ui shows ad slots ordered by index.
                    foreach ( $slots as $slot ) {
                        $attributes = $slot['attributes'];
                        $id         = $article_set->slug . '-slot-' . $slot['index'];
                        ?>
                        <tr id="<?php echo esc_attr( $id ); ?>" data-slot-article-set="<?php echo esc_attr( $article_set_id ); ?>" data-slot-id="<?php echo esc_attr( $slot['index'] ); ?>" class="slot-item">
                            <td><?php echo esc_html( $article_set->name ); ?></td>
                            <td><?php echo esc_html( $slot['index'] ); ?></td>
                            <td><?php echo esc_html( $attributes['id'] ); ?></td>
                            <td><?php echo esc_html( $attributes['ad_provider'] ); ?></td>
                            <td>
                                <div id="<?php echo esc_attr( $id ); ?>-data" style="display:none;">
                                    <div>
                                        <pre><?php echo wp_json_encode( $attributes['ad_data'], JSON_PRETTY_PRINT ); ?></pre>
                                    </div>
                                </div>

                                <a href="#TB_inline?width=600&height=350&inlineId=<?php echo esc_attr( $id ); ?>-data" title="Ad slot data" class="thickbox">View details</a>
                            </td>
                            <td>
                                <a href="#" class="copy-slot-value"><?php esc_html_e( 'Copy to form', 'richie' ); ?></a> |
                                <a href="#" class="remove-slot-item"><?php esc_html_e( 'Remove', 'richie' ); ?></a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
                </tbody>
            </table>
            <?php
        else :
            printf( '<em>%s</em>', esc_html__( 'No slots configured. Add ad slots with the form below.', 'richie' ) );
        endif;
    }
}
