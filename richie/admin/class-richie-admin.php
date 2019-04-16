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

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-maggio-service.php';

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
            'none',
        );

        add_action( 'wp_ajax_list_update_order', array( $this, 'order_source_list' ) );
        add_action( 'wp_ajax_remove_source_item', array( $this, 'remove_source_item' ) );
        add_action( 'wp_ajax_set_disable_summary', array( $this, 'set_disable_summary' ) );
        add_action( 'wp_ajax_publish_source_changes', array( $this, 'publish_source_changes' ) );
        add_action( 'wp_ajax_revert_source_changes', array( $this, 'revert_source_changes' ) );
        add_action( 'wp_ajax_remove_ad_slot', array( $this, 'remove_ad_slot' ) );
        add_action( 'wp_ajax_get_adslot_data', array( $this, 'get_adslot_data' ) );

        add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-admin.css', array(), $this->version, 'all' );

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        add_thickbox();
        wp_enqueue_script( 'suggest' );
        wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-admin.js', array( 'jquery' ), $this->version, false );
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
        add_options_page( 'Richie Settings', 'Richie', 'manage_options', $this->settings_page_slug, array( $this, 'load_admin_page_content' ) );
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

    /**
     * Validate general settings
     *
     * @param array $input Data from settings form.
     * @return array Validated values
     */
    public function validate_settings( $input ) {
        $valid = array();

        // Paywall.
        $valid['metered_pmpro_level']     = isset( $input['metered_pmpro_level'] ) ? intval( $input['metered_pmpro_level'] ) : 0;
        $valid['member_only_pmpro_level'] = isset( $input['member_only_pmpro_level'] ) ? intval( $input['member_only_pmpro_level'] ) : 0;
        if ( isset( $input['access_token'] ) && ! empty( $input['access_token'] ) ) {
            $valid['access_token'] = sanitize_text_field( $input['access_token'] );
        }
        $valid['maggio_secret']               = isset( $input['maggio_secret'] ) ? sanitize_text_field( $input['maggio_secret'] ) : '';
        $valid['maggio_hostname']             = isset( $input['maggio_hostname'] ) ? esc_url_raw( $input['maggio_hostname'] ) : '';
        $valid['maggio_organization']         = isset( $input['maggio_organization'] ) ? sanitize_text_field( $input['maggio_organization'] ) : '';
        $valid['maggio_required_pmpro_level'] = isset( $input['maggio_required_pmpro_level'] ) ? intval( $input['maggio_required_pmpro_level'] ) : '';
        $valid['maggio_index_range']          = isset( $input['maggio_index_range'] ) ? sanitize_text_field( $input['maggio_index_range'] ) : '';

        $options          = get_option( $this->settings_option_name );
        $current_hostname = isset( $options['maggio_hostname'] ) ? $options['maggio_hostname'] : '';
        $current_index    = isset( $options['maggio_index_range'] ) ? $options['maggio_index_range'] : '';

        if ( ! empty( $valid['maggio_hostname'] ) && ( $current_hostname !== $valid['maggio_hostname'] || $current_index !== $valid['maggio_index_range'] ) ) {
            // Force cache refresh if hostname or index range changes.
            $maggio_service = new Richie_Maggio_Service( $valid['maggio_hostname'], $valid['maggio_index_range'] );
            $maggio_service->refresh_cached_response( true );
        }

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

        if (
            isset( $input['source_name'] ) &&
            isset( $input['number_of_posts'] ) &&
            ! empty( $input['source_name'] ) &&
            intval( $input['number_of_posts'] ) > 0 &&
            isset( $input['article_set'] ) &&
            ! empty( $input['article_set'] )
        ) {

            $source = array(
                'id'              => $next_id,
                'name'            => sanitize_text_field( $input['source_name'] ),
                'number_of_posts' => intval( $input['number_of_posts'] ),
                'order_by'        => sanitize_text_field( $input['order_by'] ),
                'order_direction' => $input['order_direction'] === 'DESC' ? 'DESC' : 'ASC',
                'article_set'     => intval( $input['article_set'] ),
            );

            if ( isset( $input['herald_featured_post_id'] ) && ! empty( $input['herald_featured_post_id'] ) ) {
                $source['herald_featured_post_id'] = intval( $input['herald_featured_post_id'] );
            }

            if ( isset( $input['source_categories'] ) && ! empty( $input['source_categories'] ) ) {
                $source['categories'] = $input['source_categories'];
            }

            $source['list_layout_style'] = in_array( $input['list_layout_style'], $this->available_layout_names, true ) ? sanitize_text_field( $input['list_layout_style'] ) : 'none';

            if ( isset( $input['list_group_title'] ) && ! empty( $input['list_group_title'] ) ) {
                $source['list_group_title'] = sanitize_text_field( $input['list_group_title'] );
            }

            if ( isset( $input['disable_summary'] ) && intval( $input['disable_summary'] ) === 1 ) {
                $source['disable_summary'] = true;
            }

            if ( isset( $input['max_age'] ) && $input['max-age'] !== 'All time' ) {
                $source['max_age'] = sanitize_text_field( $input['max_age'] );
            }

            $sources[ $next_id ] = $source;
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

        if ( json_last_error() !== JSON_ERROR_NONE || $assets === false || empty( $assets ) ) {
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
            <div class="notice notice-warning">
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

        if ( $this->maggio_cache_updated() ) {
            ?>
            <div class="notice notice-success is-dismissible">
            <p>
                <strong>Richie: <?php esc_html_e( 'Maggio index cache updated.', 'richie' ); ?></strong>
            </p>
            </div>
            <?php
        }
    }

    /**
     * Check if maggio cache was updated recently.
     *
     * @param  int $threshold Optional. Compare age against threshold. Defaults to 5 seconds.
     *
     * @return boolean
     */
    public function maggio_cache_updated( $threshold = 5 ) {
        $options         = get_option( $this->settings_option_name );
        $maggio_hostname = isset( $options['maggio_hostname'] ) ? $options['maggio_hostname'] : '';
        $maggio_index    = isset( $options['maggio_index_range'] ) ? $options['maggio_index_range'] : '';

        if ( ! empty( $maggio_hostname ) ) {
            // Check if cache was updated recently.
            $maggio_service = new Richie_Maggio_Service( $maggio_hostname, $maggio_index );
            $cache          = $maggio_service->get_cache();
            if ( false !== $cache ) {
                $timestamp = isset( $cache['timestamp'] ) ? intval( $cache['timestamp'] ) : 0;
                $age       = time() - $timestamp;
                if ( $age < $threshold ) {
                    return true;
                }
            }
        }

        return false;
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

        $general_section_name = 'richie_general';
        $paywall_section_name = 'richie_paywall';
        $sources_section_name = 'richie_news_source';
        $assets_section_name  = 'richie_feed_assets';
        $maggio_section_name  = 'richie_maggio';
        $adslots_section_name = 'richie_ad_slot';

        // Create general section.
        $section = new Richie_Settings_Section( $general_section_name, __( 'General settings', 'richie' ), $this->settings_option_name );
        $section->add_field( 'access_token', __( 'Access token', 'richie' ), 'input_field', array( 'value' => $options['access_token'] ) );

        // Create paywall section.
        $section = new Richie_Settings_Section( $paywall_section_name, __( 'Paywall', 'richie' ), $this->settings_option_name );
        $section->add_field( 'metered_pmpro_level', __( 'Metered level', 'richie' ), 'pmpro_level', array( 'value' => $options['metered_pmpro_level'] ) );
        $section->add_field( 'member_only_pmpro_level', __( 'Member only level', 'richie' ), 'pmpro_level', array( 'value' => $options['member_only_pmpro_level'] ) );

        // Create maggio section.
        $section = new Richie_Settings_Section( $maggio_section_name, __( 'Maggio settings', 'richie' ), $this->settings_option_name );
        $section->add_field( 'maggio_organization', __( 'Maggio organization', 'richie' ), 'input_field', array( 'value' => $options['maggio_organization'] ) );
        $section->add_field( 'maggio_hostname', __( 'Maggio hostname', 'richie' ), 'input_field', array( 'value' => $options['maggio_hostname'] ) );
        $section->add_field( 'maggio_secret', __( 'Maggio secret', 'richie' ), 'input_field', array( 'value' => $options['maggio_secret'] ) );
        $section->add_field( 'maggio_required_pmpro_level', __( 'Required membership level', 'richie' ), 'pmpro_level', array( 'value' => $options['maggio_required_pmpro_level'] ) );

        // 'all' and 'latest' are available as default, other options can be updated.
        $available_indexes = $this->get_available_indexes();
        $selected = isset( $options['maggio_index_range'] ) ? $options['maggio_index_range'] : '/_data/index.json';
        $section->add_field( 'maggio_index_range', __( 'Maggio index range', 'richie' ), 'select_field', array( 'options' => $available_indexes, 'selected' => $selected, 'description' => 'Select index to use. "All" contains all issues, other options contain issues from specific range. To get available options, save Maggio Hostname setting first.' ) );

        // Create source section.
        $section = new Richie_Settings_Section( $sources_section_name, __( 'Add new feed source', 'richie' ), $this->sources_option_name );
        $section->add_field( 'source_name', __( 'Name', 'richie' ), 'input_field' );
        $section->add_field( 'richie_article_set', __( 'Article set', 'richie' ), 'article_set' );
        $section->add_field( 'number_of_posts', __( 'Number of posts', 'richie' ), 'input_field', array( 'type' => 'number', 'class' => 'small-text', 'description' => __( 'Number of posts included in the feed', 'richie' ) ) );

        if ( defined( 'HERALD_THEME_VERSION' ) ) {
            $front_page  = (int) get_option( 'page_on_front' );
            $description = __( 'Fetch posts from first featured module for given page id. Rest of filters will be ignored.', 'richie' );
            if ( $front_page > 0 ) {
                $description = sprintf( '%s %s %u.', $description, __( 'Current front page id is', 'richie' ), $front_page );
            }
            $section->add_field( 'herald_featured_post_id', __( 'Herald featured module', 'richie' ), 'input_field', array( 'description' => $description, 'class' => '' ) );
        }
        $section->add_field( 'source_categories', __( 'Categories', 'richie' ), 'category_list' );
        $section->add_field( 'order_by', __( 'Order by', 'richie' ), 'order_by' );
        $section->add_field( 'order_dir', __( 'Order direction', 'richie' ), 'order_direction' );
        $section->add_field( 'max_age', __( 'Post max age', 'richie' ), 'max_age' );
        $section->add_field( 'list_layout_style', __( 'List layout', 'richie' ), 'select_field', array( 'options' => $this->available_layout_names, 'required' => true ) );
        $section->add_field( 'list_group_title', __( 'List group title', 'richie' ), 'input_field', array( 'description' => __( 'Header to display before the story, useful on the first small_group_item of a group', 'richie' ) ) );
        $section->add_field( 'disable_summary', __( 'Disable article summary', 'richie' ), 'checkbox', array( 'description' => __( 'Do not show summary text in news list', 'richie' ) ) );

        // Create adslots section.
        $slot_index_description = __( 'Specify an index number for the ad slot placement in the article set feed. 1-based index, so 1 means the first item on the feed. Existing index for the article set is overwritten.', 'richie' );

        $section = new Richie_Settings_Section( $adslots_section_name, __( 'Add new ad slot', 'richie' ), $this->adslots_option_name );
        $section->add_field( 'richie_article_set', __( 'Article set', 'richie' ), 'article_set' );
        $section->add_field( 'adslot_position_index', __( 'Slot position', 'richie' ), 'input_field', array( 'description' => $slot_index_description, 'class' => '' ) );

        $ad_providers = array( 'smart' );
        $section->add_field( 'adslot_provider', __( 'Ad provider', 'richie' ), 'select_field', array( 'options' => $ad_providers ) );
        $section->add_field( 'adslot_ad_data', __( 'Ad data', 'richie' ), 'adslot_ad_data_editor' );

        // Create assets section.
        $section = new Richie_Settings_Section( $assets_section_name, __( 'Asset feed', 'richie' ), $this->assets_option_name );
        $section->add_field( 'richie_news_assets', __( 'Assets', 'richie' ), 'asset_editor' );
    }

    public function get_available_indexes() {
        $options = get_option( $this->settings_option_name );

        $available_indexes = array();

        if ( isset( $options['maggio_hostname'] ) ) {
            // We have hostname set, fetch available from the server.
            $url      = $options['maggio_hostname'] . '/_data/server_config.json';
            $response = wp_remote_get( $url );
            $body     = wp_remote_retrieve_body( $response );

            if ( ! empty( $body ) ) {
                $config = json_decode( $body, true );
                if ( JSON_ERROR_NONE === json_last_error() ) {
                    $indexes = $config['indexes'];

                    $available_indexes = array_map(
                        function( $index ) {
                            return array(
                                'title' => $index['range'],
                                'value' => $index['path'],
                            );
                        },
                        $indexes
                    );
                }
            }
        } else {
            // 'all' and 'latest' are available as default, other options can be updated.
            $available_indexes = array(
                array(
                    'title' => 'all',
                    'value' => '_data/index.json',
                ),
                array(
                    'title' => 'latest',
                    'value' => '_data/latest.json',
                ),
            );
        }
        return $available_indexes;
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
     * Ajax hook for disabling source feed summary. Sends json response.
     *
     * @return void
     */
    public function set_disable_summary() {
        if ( ! check_ajax_referer( 'richie-security-nonce', 'security', false ) ) {
            wp_send_json_error( 'Invalid security token sent.' );
        }

        if ( ! isset( $_POST['source_id'] ) ) {
            echo 'Missing source id';
            wp_die();
        }

        $source_id       = intval( $_POST['source_id'] );
        $disable_summary = $_POST['disable_summary'] === 'true';
        $option          = get_option( $this->sources_option_name );
        $current_list    = isset( $option['sources'] ) ? $option['sources'] : array();

        if ( isset( $current_list[ $source_id ] ) ) {
            if ( $disable_summary ) {
                $current_list[ $source_id ]['disable_summary'] = $disable_summary;
            } else {
                unset( $current_list[ $source_id ]['disable_summary'] );
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
                    <th><?php echo esc_html_x( 'Categories', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Posts', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Order', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'Max age', 'column name', 'richie' ); ?></th>
                    <th><?php echo esc_html_x( 'List layout', 'column name', 'richie' ); ?></th>
                    <th style="text-align: center"><?php echo esc_html_x( 'Disable summary', 'column name', 'richie' ); ?></th>
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

                    ?>
                    <tr id="source-<?php echo esc_attr( $source['id'] ); ?>" data-source-id="<?php echo esc_attr( $source['id'] ); ?>" class="source-item">
                        <td><span class="dashicons dashicons-menu"></span></td>
                        <td><?php echo esc_html( $source['id'] ); ?></td>
                        <td><?php echo esc_html( $article_set->name ); ?></td>
                        <td><?php echo esc_html( $source['name'] ); ?></td>
                        <td><?php echo ! $herald_featured ? esc_html( implode( ', ', $category_names ) ) : 'Herald featured module'; ?></td>
                        <td><?php echo esc_html( $source['number_of_posts'] ); ?></td>
                        <td><?php echo isset( $source['order_by'] ) && ! $herald_featured ? esc_html( "{$source['order_by']} {$source['order_direction']}" ) : ''; ?> </td>
                        <td><?php echo isset( $source['max_age'] ) ? esc_html( $source['max_age'] ) : 'All time'; ?></td>
                        <td><?php echo isset( $source['list_layout_style'] ) ? esc_html( $source['list_layout_style'] ) : 'none'; ?></td>
                        <td style="text-align: center">
                            <input class="disable-summary" type="checkbox" <?php echo isset( $source['disable_summary'] ) && $source['disable_summary'] === true ? 'checked' : ''; ?>>
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
