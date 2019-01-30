<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.richie.fi
 * @since             1.0.0
 * @package           Richie_News
 *
 * @wordpress-plugin
 * Plugin Name:       Richie News
 * Plugin URI:        https://www.richie.fi
 * Description:       Provides content feed in Richie json format
 * Version:           1.0.0
 * Author:            Markku Uusitupa
 * Author URI:        https://www.richie.fi
 * License:           Richie OY
 * License URI:       https://www.richie.fi
 * Text Domain:       richie-news
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'Richie_News_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-richie-news-activator.php
 */
function activate_Richie_News() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-news-activator.php';
	Richie_News_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-richie-news-deactivator.php
 */
function deactivate_Richie_News() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-news-deactivator.php';
	Richie_News_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_Richie_News' );
register_deactivation_hook( __FILE__, 'deactivate_Richie_News' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-richie-news.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_Richie_News() {

	$plugin = new Richie_News();
	$plugin->run();

}
run_Richie_News();
