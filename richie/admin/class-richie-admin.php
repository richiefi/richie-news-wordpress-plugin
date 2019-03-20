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

    private $settings_option_name;
    private $sources_option_name;
    private $assets_option_name;
    private $settings_page_slug;
    private $available_layout_names;

    /**
    * Initialize the class and set its properties.
    *
    * @since    1.0.0
    * @param      string    $plugin_name       The name of this plugin.
    * @param      string    $version    The version of this plugin.
    */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings_page_slug = $plugin_name;
        $this->settings_option_name = $plugin_name;
        $this->sources_option_name = $plugin_name . 'news_sources';
        $this->assets_option_name = $plugin_name . '_assets';
        $this->available_layout_names = array(
            'big', 'small', 'small_group_item', 'featured', 'none'
        );

        add_action('wp_ajax_list_update_order', array($this, 'order_source_list'));
        add_action('wp_ajax_remove_source_item', array($this, 'remove_source_item'));
        add_action('wp_ajax_set_disable_summary', array($this, 'set_disable_summary'));

        $this->register_taxonomy_article_set();

    }

    /**
    * Register the stylesheets for the admin area.
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

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-admin.css', array(), $this->version, 'all' );

    }

    /**
    * Register the JavaScript for the admin area.
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

        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script('suggest');
        wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-admin.js', array( 'jquery' ), $this->version, false );
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
        add_options_page( 'Richie Settings', 'Richie', 'manage_options', $this->settings_page_slug, array($this, 'load_admin_page_content') );
    }

    /**
    * Add settings action link to the plugins page.
    *
    * @since    1.0.0
    */

    public function add_action_links( $links ) {
        /*
        *  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
        */
        $settings_link = array(
            '<a href="' . admin_url( 'options-general.php?page=' . $this->settings_page_slug ) . '">' . __('Settings', $this->plugin_name) . '</a>',
        );
        return array_merge(  $settings_link, $links );

    }

    // Load the plugin admin page partial.
    public function load_admin_page_content() {
        require_once plugin_dir_path( __FILE__ ). 'partials/richie-admin-display.php';
    }

    public function register_taxonomy_article_set() {
        $labels = [
            'name'              => _x('Richie Article Sets', 'taxonomy general name'),
            'singular_name'     => _x('Richie Article Set', 'taxonomy singular name'),
            'search_items'      => __('Search Richie Article Sets'),
            'all_items'         => __('All Richie Article Sets'),
            'parent_item'       => __('Parent Richie Article Set'),
            'parent_item_colon' => __('Parent Richie Article Set:'),
            'edit_item'         => __('Edit Richie Article Set'),
            'update_item'       => __('Update Richie Article Set'),
            'add_new_item'      => __('Add New Richie Article Set'),
            'new_item_name'     => __('New Richie Article Set Name'),
            'menu_name'         => __('Richie Article Set'),
        ];
        $args = [
            'hierarchical'      => false,
            'labels'            => $labels,
            'public'            => false,
            'rewrite'           => false,
            'show_ui'           => true
        ];
        register_taxonomy('richie_article_set', null, $args);
    }

    public function validate_settings($input) {
        $valid = array();

        //paywall
        $valid['metered_pmpro_level'] = isset($input['metered_pmpro_level']) ? intval($input['metered_pmpro_level']) : 0;
        $valid['member_only_pmpro_level'] = isset($input['member_only_pmpro_level']) ? intval($input['member_only_pmpro_level']) : 0;
        if (isset( $input['access_token']) && ! empty($input['access_token'])) {
            $valid['access_token'] = sanitize_text_field($input['access_token']);
        }
        $valid['maggio_secret']                 = isset( $input['maggio_secret'] )                  ? sanitize_text_field( $input['maggio_secret'] )        : '';
        $valid['maggio_hostname']               = isset( $input['maggio_hostname'] )                ? esc_url_raw( $input['maggio_hostname'] )              : '';
        $valid['maggio_organization']           = isset( $input['maggio_organization']  )           ? sanitize_text_field( $input['maggio_organization'] )  : '';
        $valid['maggio_required_pmpro_level']   = isset( $input['maggio_required_pmpro_level'] )    ? intval( $input['maggio_required_pmpro_level'] )       : '';

        return $valid;
    }

    public function validate_source($input) {
        $current_option = get_option($this->sources_option_name);

        $sources = isset($current_option['sources']) ? $current_option['sources'] : array();
        $next_id = 0;

        foreach ( $sources as $source ) {
            if ( $source['id'] >= $next_id ) {
                $next_id = $source['id'] + 1;
            }
        }

        if (
            isset( $input['source_name'] ) &&
            isset( $input['number_of_posts'] ) &&
            ! empty ( $input['source_name'] ) &&
            intval( $input['number_of_posts']) > 0 &&
            isset( $input['article_set'] ) &&
            ! empty ( $input['article_set'] )
        ) {

            $source = array(
                'id' => $next_id,
                'name' => sanitize_text_field($input['source_name']),
                'number_of_posts' => intval($input['number_of_posts']),
                'order_by' => sanitize_text_field($input['order_by']),
                'order_direction' => $input['order_direction'] === 'DESC' ? 'DESC' :  'ASC',
                'article_set' => intval($input['article_set'])
            );

            if (isset($input['source_categories']) && ! empty($input['source_categories'])) {
                $source['categories'] = $input['source_categories'];
            }

            $source['list_layout_style'] = in_array($input['list_layout_style'], $this->available_layout_names) ? sanitize_text_field($input['list_layout_style']) : 'none';

            if ( isset($input['list_group_title']) ) {
                $source['list_group_title'] = sanitize_text_field($input['list_group_title']);
            }

            if ( isset( $input['disable_summary' ] ) && intval($input['disable_summary']) === 1 ) {
                $source['disable_summary'] = true;
            }

            array_push($sources, $source);
        } else {
            add_settings_error(
                $this->sources_option_name,
                esc_attr( 'sources_error' ),
                __('Name and number of posts are required'),
                'error'
            );
        }
        return array(
            'sources' => $sources
        );
    }

    public function validate_assets($input) {
        $assets = json_decode($input);
        if ( $assets === false or empty( $assets ) ) {
            echo 'error';
            add_settings_error(
                $this->assets_option_name,
                esc_attr( 'assets_error' ),
                __('Failed to parse json, unable to save'),
                'error'
            );
            return get_option($this->assets_option_name);
        } else {
            return $assets;
        }
    }

    public function options_update() {
        // run on admin_init

        $sources = get_option( $this->sources_option_name );

        if ( $sources === false ) {
            add_option($this->sources_option_name, array('sources' => array()));
        }

        register_setting($this->settings_option_name, $this->settings_option_name, array(
            'sanitize_callback' => array($this, 'validate_settings'))
        );

        register_setting($this->sources_option_name, $this->sources_option_name, array(
            'sanitize_callback' => array($this, 'validate_source'))
        );

        register_setting($this->assets_option_name, $this->assets_option_name, array(
            'sanitize_callback' => array($this, 'validate_assets')
        ));
        $options = get_option( $this->settings_option_name );
        if ( ! isset($options['access_token'])) {
            $options['access_token'] = bin2hex(random_bytes(16));
            update_option($this->settings_option_name, $options);
        }

        $general_section_name = 'richie_general';
        $paywall_section_name = 'richie_paywall';
        $sources_section_name = 'richie_news_source';
        $assets_section_name = 'richie_feed_assets';
        $maggio_section_name = 'richie_maggio';

        // create general section
        add_settings_section ($general_section_name, __('General settings', $this->plugin_name), null, $this->settings_option_name);
        add_settings_field('richie_access_token', __('Access token', $this->plugin_name), array($this, 'input_field_render'), $this->settings_option_name, $general_section_name, array('id' => 'access_token'));

        // create paywall section
        add_settings_section ($paywall_section_name, __('Paywall', $this->plugin_name), null, $this->settings_option_name);
        add_settings_field('richie_metered_pmpro_level', __('Metered level', $this->plugin_name), array($this, 'pmpro_level_render'), $this->settings_option_name, $paywall_section_name, array('id' => 'metered_pmpro_level'));
        add_settings_field('richie_member_only_pmpro_level', __('Member only level', $this->plugin_name), array($this, 'pmpro_level_render'), $this->settings_option_name, $paywall_section_name, array('id' => 'member_only_pmpro_level'));

        // create maggio section

        add_settings_section ($maggio_section_name, __('Maggio settings', $this->plugin_name), null, $this->settings_option_name);
        add_settings_field('richie_maggio_organization',   __('Maggio organization', $this->plugin_name),  array($this, 'input_field_render'), $this->settings_option_name, $maggio_section_name, array('id' => 'maggio_organization'));
        add_settings_field('richie_maggio_hostname',       __('Maggio hostname', $this->plugin_name),      array($this, 'input_field_render'), $this->settings_option_name, $maggio_section_name, array('id' => 'maggio_hostname'));
        add_settings_field('richie_maggio_secret',         __('Maggio secret', $this->plugin_name),        array($this, 'input_field_render'), $this->settings_option_name, $maggio_section_name, array('id' => 'maggio_secret'));
        add_settings_field('richie_maggio_required_pmpro_level', __('Required membership level', $this->plugin_name), array($this, 'pmpro_level_render'), $this->settings_option_name, $maggio_section_name, array('id' => 'maggio_required_pmpro_level'));

        // create source section
        add_settings_section ($sources_section_name, __('Add new feed source', $this->plugin_name), null, $this->sources_option_name);
        add_settings_field ('richie_source_name',      __('Name', $this->plugin_name),             array($this, 'source_name_render'),     $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_source_set',       __('Article set', $this->plugin_name),      array($this, 'article_set_render'),     $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_source_category',  __('Categories', $this->plugin_name),       array($this, 'category_list_render'),   $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_source_amount',    __('Number of posts', $this->plugin_name),  array($this, 'number_of_posts_render'), $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_source_order_by',  __('Order by', $this->plugin_name),         array($this, 'order_by_render'),        $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_source_order_dir', __('Order direction', $this->plugin_name),  array($this, 'order_direction_render'), $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_list_layout_style', __('List layout', $this->plugin_name),      array($this, 'list_layout_style_render'),       $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_list_group_title',  __('List group title', $this->plugin_name), array($this, 'list_group_title_render'),        $this->sources_option_name, $sources_section_name);
        add_settings_field ('richie_disable_summary',    __('Disable article summary', $this->plugin_name), array($this, 'checkbox_render'),        $this->sources_option_name, $sources_section_name, array('id' => 'disable_summary', 'description' => 'Do not show summary text in news list', 'namespace' => $this->sources_option_name));

        // create assets section
        add_settings_section ($assets_section_name, __('Asset feed', $this->plugin_name), null, $this->assets_option_name);
        add_settings_field ('richie_news_assets', __('Assets', $this->plugin_name), array($this, 'asset_editor_render'), $this->assets_option_name, $assets_section_name);

    }

    public function asset_editor_render() {
        $assets = get_option( $this->assets_option_name );
        if ( empty($assets) ) {
            // $response = wp_remote_get( str_replace('localhost:8000', 'skynet.local:8000', get_site_url( null, '/wp-json/richie/v1/assets' ) ) );
            // if ( is_array( $response ) && ! is_wp_error( $response ) ) {
            //     $assets = json_decode($response['body']);
            // }
            $assets = [];
        }
        ?>
        <p>
            Accepts valid json. Example:
        </p>
        <pre>
    [
        {
            "remote_url": "https://example.com/path/to/asset.css",
            "local_name": "app-assets/asset.css"
        }
    ]
        </pre>
        <script>
            var assetUrl = "<?php echo get_rest_url(null, '/richie/v1/assets'); ?>";
        </script>
        <button id="generate-assets" type="button">Generate base list (overrides current content)</button>
        <textarea id="code_editor_page_js" rows="10" name="<?php echo $this->assets_option_name; ?>" class="widefat textarea"><?php echo wp_unslash( wp_json_encode($assets, JSON_PRETTY_PRINT) ); ?></textarea>
        <?php
    }

    public function input_field_render( array $args  ) {
        $options = get_option( $this->plugin_name );
        $id = $args['id'];
        $type = isset($args['type']) ? $args['type'] : 'test';
        $name = $this->plugin_name . '[' . $args['id'] . ']';
        $value = isset($options{$id}) ? $options{$id} : '';

        print "<input class='regular-text' type='$type' name='$name' value='$value'>";

        if ( isset( $args['description'] ) ) {
            printf('<span class="description">%s</span>', esc_html__( $args['description'], $this->plugin_name ));
        }
    }

    public function checkbox_render( array $args ) {
        $current = isset( $args['current'] ) ? $args['current'] : '';
        $value = isset ( $args['value'] ) ? $args['value'] : '1';
        $checked = checked( $current, $value, false );
        $name = $args['namespace'] . '[' . $args['id'] . ']';
        print "<input type='checkbox' name='$name' value='$value' $checked>";

        if ( isset( $args['description'] ) ) {
            printf('<span class="description">%s</span>', esc_html__( $args['description'], $this->plugin_name ));
        }
    }

    public function pmpro_level_render( array $args ) {
        $options = get_option( $this->plugin_name );
        $id = $args['id'];
        $current_level = isset($options{$id}) ? $options{$id} : '';
        $pmpro_levels = pmpro_getAllLevels();
        $name = $this->plugin_name . '[' . $args['id'] . ']';

        ?>
        <select name="<?php echo $name ?>" id="<?php echo $this->plugin_name; ?>-<?php echo $id; ?>">
            <option value="0"><?php esc_attr_e('Not used', $this->plugin_name );?></option>
            <?php
                foreach ( $pmpro_levels as $level ) {
                    $selected = selected( $current_level, $level->id, FALSE);
                    echo "<option value='{$level->id}' {$selected}>{$level->name}</option>";
                }
            ?>
        </select>
        <?php
    }


    public function source_name_render() {
        ?>
        <input class="regular-text" type='text' name='<?php echo $this->sources_option_name; ?>[source_name]'>
        <?php
    }

    public function article_set_render() {
        wp_dropdown_categories( array (
            'taxonomy' => 'richie_article_set',
            'hide_empty' => false,
            'id' => $this->sources_option_name . '-article_set',
            'name' => $this->sources_option_name . '[article_set]',
        ) );
        ?>
        <p>
            <a href="edit-tags.php?taxonomy=richie_article_set">Edit Richie Article Sets</a>
        </p>
        <?php
    }

    public function category_list_render() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-category-walker.php';

        $custom_walker = new Richie_Walker_Category_Checklist(null, $this->sources_option_name.'[source_categories][]');
        ?>
        <ul>
        <?php wp_category_checklist( 0, 0, false, false, $custom_walker ); ?>
        </ul>
        <?php
    }

    public function number_of_posts_render() {
        ?>
            <input class="small-text" type='text' name='<?php echo $this->sources_option_name ; ?>[number_of_posts]'>
            <span class="description"><?php esc_attr_e( 'Amount of posts included in the feed', $this->plugin_name ); ?></span>
        <?php
    }

    public function order_by_render() {
        ?>
            <select name='<?php echo $this->sources_option_name ; ?>[order_by]' id='<?php echo $this->sources_option_name ; ?>-order-by'>
                <option selected="selected" value="date">Post date</option>
                <option value="modified">Post modified time</option>
                <option value="title">Post title</option>
                <option value="author">Post author</option>
                <option value="id">Post ID</option>
            </select>
        <?php
    }

    public function order_direction_render() {
        ?>
            <select name='<?php echo $this->sources_option_name ; ?>[order_direction]' id='<?php echo $this->sources_option_name ; ?>-order-direction'>
                <option selected="selected" value="DESC">DESC</option>
                <option value="ASC">ASC</option>
            </select>
        <?php
    }

    public function list_layout_style_render() {
        ?>
        <select name='<?php echo $this->sources_option_name ; ?>[list_layout_style]' id='<?php echo $this->sources_option_name ; ?>-list_layout_style' required>
            <?php foreach( $this->available_layout_names as $layout_name ): ?>
                <option value='<?php echo $layout_name ?>'><?php echo $layout_name ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }


    public function list_group_title_render() {
        ?>
        <input class="regular-text" type='text' name='<?php echo $this->sources_option_name; ?>[list_group_title]'>
        <span class="description"><?php esc_attr_e( 'Header to display before the story, useful on the first
small_group_item of a group', $this->plugin_name ); ?></span>
        <?php
    }

    public function order_source_list() {
        if ( !isset($_POST['source_items']) ) {
            echo 'Missing source list';
            wp_die();
        }
        $option = get_option($this->sources_option_name);
        $current_list = isset($option['sources']) ? $option['sources'] : array();
        $new_order = $_POST['source_items'];
        $new_list = array();

        if ( count($current_list) != count($new_order)) {
            $error = new WP_Error('-1', 'Current list and received list size doesn\'t match');
            wp_send_json_error( $error, 400 );
        }

        $map = null;

        foreach( $new_order as $id ) {
            $item = array_search($id, array_column($current_list, 'id'));
            if ( isset($item) ) {
                array_push($new_list, $current_list[$item]);
            } else {
                $error = new WP_Error('-1', 'Something wrong');
                wp_send_json_error ( $error, 500);
            }
        }

        $option['sources'] = $new_list;
        // skip validate source
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        $updated = update_option($this->sources_option_name, $option);
        add_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));

        wp_send_json_success($updated);
    }

    public function remove_source_item() {
        if ( !isset($_POST['source_id']) ) {
            echo 'Missing source id';
            wp_die();
        }

        $source_id = intval($_POST['source_id']);
        $option = get_option($this->sources_option_name);
        $current_list = isset($option['sources']) ? $option['sources'] : array();
        $deleted = false;
        foreach($current_list as $sid => $source) {
            if ( $source['id'] === $source_id ) {
                unset($current_list[$sid]); //Delete from Array
                $deleted = true;
                break;
            }
        }
        $option['sources'] = $current_list;

        //skip validate source
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        $updated = update_option($this->sources_option_name, $option);
        add_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        wp_send_json(array('deleted' => $updated && $deleted));
    }

    public function set_disable_summary() {
        if ( !isset($_POST['source_id']) ) {
            echo 'Missing source id';
            wp_die();
        }

        $source_id = intval($_POST['source_id']);
        $disable_summary = $_POST['disable_summary'] === "true";
        $option = get_option($this->sources_option_name);
        $current_list = isset($option['sources']) ? $option['sources'] : array();
        $test = null;
        foreach($current_list as &$source) {
            if( $source['id'] === $source_id) {
                $test = array('source' => $source, 'disabled' => $disable_summary);
                if ( $disable_summary ) {
                    $source['disable_summary'] = $disable_summary;
                } else {
                    unset($source['disable_summary']);
                }
                break;
            }
        }
        $option['sources'] = $current_list;
        //skip validate source
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        $updated = update_option($this->sources_option_name, $option);
        add_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        wp_send_json(array('updated' => $updated));
    }


    public function source_list() {
        $options = get_option($this->sources_option_name);
        if ( isset($options['sources']) && ! empty( $options['sources'] ) ): ?>
            <table class="widefat feed-source-list sortable-list">
                <thead>
                    <th style="width: 30px;"></th>
                    <th>Article Set</th>
                    <th>Name</th>
                    <th>Categories</th>
                    <th>Posts</th>
                    <th>Order</th>
                    <th>List layout</th>
                    <th style="text-align: center">Disable summary</th>
                    <th>Actions</th>
                </thead>
                <tbody>
                <?php
                foreach( $options['sources'] as $key => $source) {
                    $category_names = array();
                    if (! empty($source['categories'])) {
                        $categories = get_categories(array(
                            'include' => $source['categories']
                        ));
                        foreach ( $categories as $cat ) {
                            array_push( $category_names, $cat->name );
                        }
                    } else {
                        array_push( $category_names, 'All categories');
                    }

                    $article_set = get_term($source['article_set']);

                    ?>
                    <tr id="source-<?php echo $source['id']; ?>" data-source-id="<?php echo $source['id'] ?>" class="source-item">
                        <td><span class="dashicons dashicons-menu"></span></td>
                        <td><?php echo $article_set->name; ?></td>
                        <td><?php echo $source['name'] ?></td>
                        <td><?php echo implode(', ', $category_names); ?></td>
                        <td><?php echo $source['number_of_posts']; ?></td>
                        <td><?php echo isset($source['order_by']) ? "{$source['order_by']} {$source['order_direction']}" : '' ?> </td>
                        <td><?php echo isset($source['list_layout_style']) ? $source['list_layout_style'] : 'none' ?></td>
                        <td style="text-align: center">
                            <input class="disable-summary" type="checkbox" <?php echo isset($source['disable_summary']) && $source['disable_summary'] === true ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <a href="#" class="remove-source-item"">Remove</a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        <?php
        else:
            echo _e('<em>No sources configured. Add news feed sources with the form bellow.</em>');
        endif;
    }

}
