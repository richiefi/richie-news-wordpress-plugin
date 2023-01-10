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
 * @package           Richie_Editions_Wp
 *
 * @wordpress-plugin
 * Plugin Name:       Richie Editions WP
 * Plugin URI:        https://github.com/richiefi/richie-editions-wordpress-plugin
 * Description:       This plugin aims to make it easier to integrate Richie Editions e-paper content onto WordPress sites.

 * Version:           1.0.0
 * Author:            Richie OY
 * Author URI:        https://www.richie.fi
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       richie-editions-wp
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
define( 'RICHIE_EDITIONS_WP_VERSION', '1.0.0' );
define( 'RICHIE_EDITIONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-richie-editions-wp-activator.php
 */
function activate_richie_editions_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-editions-wp-activator.php';
	Richie_Editions_Wp_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-richie-editions-wp-deactivator.php
 */
function deactivate_richie_editions_wp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-editions-wp-deactivator.php';
	Richie_Editions_Wp_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_richie_editions_wp' );
register_deactivation_hook( __FILE__, 'deactivate_richie_editions_wp' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-richie-editions-wp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_richie_editions_wp() {

	$plugin = new Richie_Editions_Wp();
	$plugin->run();

}
run_richie_editions_wp();
