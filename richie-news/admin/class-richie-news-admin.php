<?php

/**
* The admin-specific functionality of the plugin.
*
* @link       https://www.richie.fi
* @since      1.0.0
*
* @package    Richie_News
* @subpackage Richie_News/admin
*/

/**
* The admin-specific functionality of the plugin.
*
* Defines the plugin name, version, and two examples hooks for how to
* enqueue the admin-specific stylesheet and JavaScript.
*
* @package    Richie_News
* @subpackage Richie_News/admin
* @author     Markku Uusitupa <markku@richie.fi>
*/
class Richie_News_Admin {

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
    private $settings_page_slug;

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
        $this->sources_option_name = $plugin_name . '_sources';

        add_action('wp_ajax_list_update_order', array($this, 'order_source_list'));
        add_action('wp_ajax_remove_source_item', array($this, 'remove_source_item'));

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
        * defined in Richie_News_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_News_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/richie-news-admin.css', array(), $this->version, 'all' );

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
        * defined in Richie_News_Loader as all of the hooks are defined
        * in that particular class.
        *
        * The Richie_News_Loader will then create the relationship
        * between the defined hooks and the functions defined in this
        * class.
        */

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/richie-news-admin.js', array( 'jquery' ), $this->version, false );
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );
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
        add_options_page( 'Richie News Settings', 'Richie News', 'manage_options', $this->settings_page_slug, array($this, 'load_admin_page_content') );
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
        require_once plugin_dir_path( __FILE__ ). 'partials/richie-news-admin-display.php';
    }

    public function validate_settings($input) {
        $valid = array();

        //paywall
        $valid['metered_pmpro_level'] = isset($input['metered-level']) ? intval($input['metered-level']) : 0;
        $valid['member_only_pmpro_level'] = isset($input['member-only-level']) ? intval($input['member-only-level']) : 0;
        if (isset( $input['access_token']) && ! empty($input['access_token'])) {
            $valid['access_token'] = sanitize_text_field($input['access_token']);
        }
        return $valid;
    }

    public function validate_source($input) {
        $current_option = get_option($this->sources_option_name, array(
            'sources' => array()
        ));
        add_settings_error(
            $this->sources_option_name,
            esc_attr( 'sources_error' ),
            __('not ready yet'),
            'error'
        );

        $sources = isset($current_option['sources']) ? $current_option['sources'] : array();
        $next_id = 0;

        foreach ( $sources as $source ) {
            if ( $source['id'] >= $next_id ) {
                $next_id = $source['id'] + 1;
            }
        }

        if ( isset( $input['source_name'] ) &&
             isset( $input['source_categories'] ) &&
             isset( $input['number_of_posts'] ) ) {
            array_push($sources, array(
                'id' => $next_id,
                'name' => sanitize_text_field($input['source_name']),
                'categories' => $input['source_categories'],
                'number_of_posts' => intval($input['number_of_posts'])
            ));
        }
        return array(
            'sources' => $sources
        );
    }

    public function options_update() {
        // run on admin_init

        register_setting($this->settings_option_name, $this->settings_option_name, array(
            'sanitize_callback' => array($this, 'validate_settings'))
        );

        register_setting($this->sources_option_name, $this->sources_option_name, array(
            'sanitize_callback' => array($this, 'validate_source'))
        );

        $options = get_option( $this->settings_option_name );
        if ( ! isset($options['access_token'])) {
            $options['access_token'] = bin2hex(random_bytes(16));
            update_option($this->settings_option_name, $options);
        }

        $general_section_name = 'richie_news_general';
        $paywall_section_name = 'richie_news_paywall';
        $sources_section_name = 'richie_news_source';

        // create general section
        add_settings_section ($general_section_name, __('General settings', $this->plugin_name), null, $this->settings_option_name);
        add_settings_field('richie_news_access_token', __('Access token', $this->plugin_name), array($this, 'access_token_render'), $this->settings_option_name, $general_section_name);

        // create paywall section
        add_settings_section ($paywall_section_name, __('Paywall', $this->plugin_name), null, $this->settings_option_name);
        add_settings_field('richie_news_metered_pmpro_level', __('Metered level', $this->plugin_name), array($this, 'metered_level_render'), $this->settings_option_name, $paywall_section_name);
        add_settings_field('richie_news_member_only_pmpro_level', __('Member only level', $this->plugin_name), array($this, 'member_only_level_render'), $this->settings_option_name, $paywall_section_name);

        // create source section
        add_settings_section ($sources_section_name, __('Add new feed source', $this->plugin_name), null, $this->sources_option_name);
        add_settings_field('richie_news_source_name', __('Name', $this->plugin_name), array($this, 'source_name_render'), $this->sources_option_name, $sources_section_name);
        add_settings_field('richie_news_source_category', __('Categories', $this->plugin_name), array($this, 'category_list_render'), $this->sources_option_name, $sources_section_name);
        add_settings_field('richie_news_source_amount', __('Number of posts', $this->plugin_name), array($this, 'number_of_posts_render'), $this->sources_option_name, $sources_section_name);
        //add_settings_field('richie_news_source_amount', __('Post amount', $this->plugin_name), )
    }

    public function access_token_render() {
        $options = get_option( $this->plugin_name );
        ?>
        <input class="regular-text" type='text' name='<?php echo $this->plugin_name; ?>[access_token]' value='<?php echo $options['access_token']; ?>'>
        <?php
    }

    public function metered_level_render() {
        $options = get_option( $this->plugin_name );
        $current_level = $options['metered_pmpro_level'];
        $pmpro_levels = pmpro_getAllLevels();
        ?>
        <select name="<?php echo $this->plugin_name; ?>[metered-level]" id="<?php echo $this->plugin_name; ?>-metered-level">
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

    public function member_only_level_render() {
        $options = get_option( $this->plugin_name );
        $current_level = $options['member_only_pmpro_level'];
        $pmpro_levels = pmpro_getAllLevels();
        ?>
        <select name="<?php echo $this->plugin_name; ?>[member-only-level]" id="<?php echo $this->plugin_name; ?>-member-only-level">
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

    public function category_list_render() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-news-category-walker.php';

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

    public function order_source_list() {
        if ( !isset($_POST['source_items']) ) {
            echo 'Missing source list';
            wp_die();
        }
        $option = get_option($this->sources_option_name);
        $current_list = isset($option['sources']) ? $option['sources'] : array();
        $new_order = $_POST['source_items'];
        $new_list = array();

        if ( count($current_list) == count($sources)) {
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
        foreach($current_list as $k=>$v) {
            foreach ($current_list[$k] as $key=>$value) {
                echo $key . ':' . $value;
              if ($key === "id") {
                  if ($value == $source_id) {
                    unset($current_list[$k]); //Delete from Array
                  }
                  break;
              }
            }
        }
        $option['sources'] = $current_list;

        //skip validate source
        remove_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        $updated = update_option($this->sources_option_name, $option);
        add_filter( 'sanitize_option_' . $this->sources_option_name, array($this, 'validate_source'));
        wp_send_json_success($updated);
    }


    public function source_list() {
        $options = get_option($this->sources_option_name);
        if ( isset($options['sources']) && ! empty( $options['sources'] ) ): ?>
            <table class="widefat feed-source-list sortable-list">
                <thead>
                    <th style="width: 30px;"></th>
                    <th>Name</th>
                    <th>Categories</th>
                    <th>Number of posts</th>
                    <th>Actions</th>
                </thead>
                <tbody>
                <?php
                foreach( $options['sources'] as $key => $source) {
                    $categories = get_categories(array(
                        'include' => $source['categories']
                    ));
                    $category_names = array();
                    foreach ( $categories as $cat ) {
                        array_push( $category_names, $cat->name );
                    }
                    ?>
                    <tr id="source-<?php echo $source['id']; ?>" data-source-id="<?php echo $source['id'] ?>" class="source-item">
                        <td><span class="dashicons dashicons-menu"></span></td>
                        <td><?php echo $source['name'] ?></td>
                        <td><?php echo implode(', ', $category_names) ?></td>
                        <td><?php echo $source['number_of_posts']; ?> posts</td>
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
