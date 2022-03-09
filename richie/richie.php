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
 * @package           Richie
 *
 * @wordpress-plugin
 * Plugin Name:       Richie
 * Plugin URI:        https://www.richie.fi
 * Description:       Richie platform plugin
 * Version:           1.7.2
 * Author:            Richie Oy
 * License:           Richie Oy
 * License URI:       https://www.richie.fi
 * Text Domain:       richie
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
define( 'Richie_VERSION', '1.7.2' );
define( 'Richie_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Define plugin constants
 */

defined( 'RICHIE_ASSET_CACHE_KEY' ) or define( 'RICHIE_ASSET_CACHE_KEY', 'richie_assets_cache' );


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-richie-activator.php
 */
function activate_Richie() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-activator.php';
	Richie_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-richie-deactivator.php
 */
function deactivate_Richie() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-richie-deactivator.php';
	Richie_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_Richie' );
register_deactivation_hook( __FILE__, 'deactivate_Richie' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-richie.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_Richie() {

	$plugin = new Richie();
	$plugin->run();

}
run_Richie();
