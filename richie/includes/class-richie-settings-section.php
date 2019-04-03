<?php
/**
 * Wrapper for WordPress settings api
 *
 * @link       https://www.richie.fi
 * @since 1.0.3
 * @package    Richie
 * @subpackage Richie/includes
 */

/**
 * Wrapper for WordPress settings api
 *
 * @since 1.0.3
 * @package    Richie
 * @subpackage Richie/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_Settings_Section {
    private $section_slug;
    private $section_title;
    private $option_name;

    public function __construct( $section_slug, $section_title, $option_name ) {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-richie-admin-components.php';
        $this->admin_components = new Richie_Admin_Components();

        $this->section_slug  = $section_slug;
        $this->section_title = $section_title;
        $this->option_name   = $option_name;

        add_settings_section( $section_slug, $section_title, null, $option_name );
    }

    public function add_field( $id, $title, $component, $component_args = [] ) {
        $default_args = array(
            'option_name' => $this->option_name,
            'id'          => $id,
        );

        $args = array(
            $id,
            $title,
            array( $this->admin_components, $component ),
            $this->option_name,
            $this->section_slug,
            array_merge( $default_args, $component_args ),
        );

        call_user_func_array( 'add_settings_field', $args );
    }
}
