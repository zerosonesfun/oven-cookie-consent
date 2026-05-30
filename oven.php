<?php
/**
 * Plugin Name: Oven Cookie Consent
 * Plugin URI: https://wilcosky.com/oven
 * Description: Cookie consent with detection, essential/non-essential classification, and re-consent on policy changes. Uses CookieConsent library.
 * Version: 1.0.9
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Billy Wilcosky
 * Author URI: https://wilcosky.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oven-cookie-consent
 * Domain Path: /languages
 *
 * @package Oven
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OVEN_VERSION', '1.0.9' );
define( 'OVEN_PLUGIN_FILE', __FILE__ );
define( 'OVEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OVEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OVEN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once OVEN_PLUGIN_DIR . 'includes/class-oven.php';

/**
 * Returns the main Oven plugin instance.
 *
 * @return \Oven\Oven
 */
function oven(): \Oven\Oven {
	return \Oven\Oven::instance();
}

add_action( 'plugins_loaded', array( oven(), 'init' ), 0 );
