<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Oven
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'oven_settings' );
delete_option( 'oven_detected_cookies' );
delete_option( 'oven_cookie_descriptions' );
delete_option( 'oven_cookie_revision' );
delete_option( 'oven_script_cookie_map' );
delete_option( 'oven_cookie_overrides' );

delete_metadata( 'user', 0, 'oven_cookie_consent', '', true );
delete_metadata( 'user', 0, 'oven_cookie_consent_history', '', true );
