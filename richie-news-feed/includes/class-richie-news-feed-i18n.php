<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.richie.fi
 * @since      1.0.0
 *
 * @package    Richie_News_Feed
 * @subpackage Richie_News_Feed/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Richie_News_Feed
 * @subpackage Richie_News_Feed/includes
 * @author     Markku Uusitupa <markku@richie.fi>
 */
class Richie_News_Feed_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'richie-news-feed',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
