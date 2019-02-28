<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie_News
 * @subpackage Richie_News/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Richie_News
 * @subpackage Richie_News/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_News_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/helpers.php';

        richie_create_maggio_rewrite_rules(true);
	}

}
