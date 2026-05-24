<?php
/**
 * Main Oven plugin class.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oven
 */
final class Oven {

	/**
	 * Single instance.
	 *
	 * @var Oven|null
	 */
	private static ?Oven $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Cookie manager instance.
	 *
	 * @var Cookie_Manager|null
	 */
	private ?Cookie_Manager $cookie_manager = null;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend|null
	 */
	private ?Frontend $frontend = null;

	/**
	 * REST API instance.
	 *
	 * @var Rest_Controller|null
	 */
	private ?Rest_Controller $rest = null;

	/**
	 * User consent profile UI (admin).
	 *
	 * @var User_Consent_Profile|null
	 */
	private ?User_Consent_Profile $user_consent_profile = null;

	/**
	 * Get instance.
	 *
	 * @return Oven
	 */
	public static function instance(): Oven {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 */
	public function init(): void {
		$this->load_dependencies();
		$this->settings     = new Settings();
		$this->cookie_manager = new Cookie_Manager( $this->settings );
		$this->frontend     = new Frontend( $this->settings, $this->cookie_manager );
		$this->rest         = new Rest_Controller( $this->settings, $this->cookie_manager );

		$this->settings->init();
		$this->cookie_manager->init();
		$this->frontend->init();
		$this->rest->init();
		$this->user_consent_profile = new User_Consent_Profile( $this->settings );
		$this->user_consent_profile->init();
		Blocks::init();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies(): void {
		require_once OVEN_PLUGIN_DIR . 'includes/class-consent-sanitizer.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-settings.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-cookie-manager.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-frontend.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-rest-controller.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-user-consent-profile.php';
		require_once OVEN_PLUGIN_DIR . 'includes/class-blocks.php';
	}

	/**
	 * Get settings.
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}

	/**
	 * Get cookie manager.
	 *
	 * @return Cookie_Manager
	 */
	public function get_cookie_manager(): Cookie_Manager {
		return $this->cookie_manager;
	}
}
