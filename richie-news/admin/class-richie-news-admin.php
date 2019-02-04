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
        add_options_page( 'Richie News Settings', 'Richie News', 'manage_options', $this->plugin_name, array($this, 'load_admin_page_content') );
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
            '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_name ) . '">' . __('Settings', $this->plugin_name) . '</a>',
        );
        return array_merge(  $settings_link, $links );

    }

    // Load the plugin admin page partial.
    public function load_admin_page_content() {
        require_once plugin_dir_path( __FILE__ ). 'partials/richie-news-admin-display.php';
    }

    public function validate($input) {
        // All checkboxes inputs
        $valid = array();

        //paywall
        $valid['metered_pmpro_level'] = isset($input['metered-level']) ? $input['metered-level'] : 0;
        $valid['member_only_pmpro_level'] = isset($input['member-only-level']) ? $input['member-only-level'] : 0;
        if (isset( $input['access_token']) && ! empty($input['access_token'])) {
            $valid['access_token'] = $input['access_token'];
        }
        return $valid;
     }

    public function options_update() {
        $options = get_option( $this->plugin_name );
        if ( ! isset($options['access_token'])) {
            $options['access_token'] = bin2hex(random_bytes(16));
            update_option($this->plugin_name, $options);
        }

        register_setting($this->plugin_name, $this->plugin_name, array($this, 'validate'));

        add_settings_section ('richie_news_general', __('General settings', $this->plugin_name), null, $this->plugin_name);
        add_settings_field('richie_news_access_token', __('Access token', $this->plugin_name), array($this, 'access_token_render'), $this->plugin_name, 'richie_news_general');

        add_settings_section ('richie_news_paywall', __('Paywall', $this->plugin_name), null, $this->plugin_name);
        add_settings_field('richie_news_metered_pmpro_level', __('Metered level', $this->plugin_name), array($this, 'metered_level_render'), $this->plugin_name, 'richie_news_paywall');
        add_settings_field('richie_news_member_only_pmpro_level', __('Member only level', $this->plugin_name), array($this, 'member_only_level_render'), $this->plugin_name, 'richie_news_paywall');
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

}
