<?php
/**
 * Oven frontend: enqueue CookieConsent and config.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 */
class Frontend {

	/** Session cookie: "we've checked the DB this session, they have consent" — avoids hitting user meta on every request. */
	private const SESSION_VERIFIED_COOKIE = 'oven_cc_sess';

	/** Dummy script handles for head inline JS (printed early via wp_print_scripts). */
	private const SCRIPT_COOKIE_TRACER         = 'oven-cookie-tracer';
	private const SCRIPT_CLEAR_REJECTED        = 'oven-clear-rejected-cookies';
	private const SCRIPT_LOGGED_IN_CONSENT_BOOT = 'oven-logged-in-consent';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cookie manager instance.
	 *
	 * @var Cookie_Manager
	 */
	private Cookie_Manager $cookie_manager;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings Settings.
	 * @param Cookie_Manager $cookie_manager Cookie manager.
	 */
	public function __construct( Settings $settings, Cookie_Manager $cookie_manager ) {
		$this->settings       = $settings;
		$this->cookie_manager = $cookie_manager;
	}

	/**
	 * Initialize frontend.
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_head_inline_scripts' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_filter( 'script_loader_tag', array( $this, 'block_nonessential_scripts' ), 10, 3 );
		add_action( 'wp_head', array( $this, 'print_head_inline_tracer' ), 0 );
		add_action( 'wp_head', array( $this, 'print_head_inline_clear_rejected' ), 1 );
		add_action( 'wp_head', array( $this, 'print_head_inline_logged_in_consent' ), 5 );
	}

	/**
	 * Register head bootstrap scripts (printed early in wp_head via wp_print_scripts).
	 */
	public function enqueue_head_inline_scripts(): void {
		if ( is_admin() ) {
			return;
		}

		if ( $this->should_enqueue_cookie_tracer() ) {
			$this->register_enqueue_head_script(
				self::SCRIPT_COOKIE_TRACER,
				'assets/js/oven-cookie-tracer.js',
				'ovenCookieTracer',
				array(
					'consentCookieName' => 'oven_cc',
				)
			);
		}

		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$to_clear = $this->cookie_manager->get_cookies_to_clear();
		if ( ! empty( $to_clear ) ) {
			$this->register_enqueue_head_script(
				self::SCRIPT_CLEAR_REJECTED,
				'assets/js/oven-clear-rejected-cookies.js',
				'ovenClearRejected',
				array(
					'names' => array_map( 'sanitize_text_field', array_values( $to_clear ) ),
				)
			);
		}

		if ( is_user_logged_in() ) {
			$config = $this->get_logged_in_consent_config();
			if ( $config !== null ) {
				$this->register_enqueue_head_script(
					self::SCRIPT_LOGGED_IN_CONSENT_BOOT,
					'assets/js/oven-logged-in-consent.js',
					'ovenLoggedInConsent',
					$config
				);
			}
		}
	}

	/**
	 * Print cookie tracer inline script early (before default wp_print_scripts).
	 */
	public function print_head_inline_tracer(): void {
		$this->print_enqueued_inline_script_early( self::SCRIPT_COOKIE_TRACER );
	}

	/**
	 * Print clear-rejected-cookies inline script early.
	 */
	public function print_head_inline_clear_rejected(): void {
		$this->print_enqueued_inline_script_early( self::SCRIPT_CLEAR_REJECTED );
	}

	/**
	 * Print logged-in consent bootstrap inline script early.
	 */
	public function print_head_inline_logged_in_consent(): void {
		$this->print_enqueued_inline_script_early( self::SCRIPT_LOGGED_IN_CONSENT_BOOT );
	}

	/**
	 * @param string $handle Script handle.
	 */
	private function print_enqueued_inline_script_early( string $handle ): void {
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			wp_print_scripts( $handle );
		}
	}

	/**
	 * Register, localize, and enqueue a head bootstrap script file.
	 *
	 * @param string               $handle        Script handle.
	 * @param string               $relative_path Path under plugin directory.
	 * @param string               $object_name   Global JS object name for wp_localize_script.
	 * @param array<string, mixed> $data          Localized data (JSON-encoded by WordPress).
	 */
	private function register_enqueue_head_script( string $handle, string $relative_path, string $object_name, array $data ): void {
		$url = OVEN_PLUGIN_URL . $relative_path;
		wp_register_script( $handle, $url, array(), $this->asset_version( $relative_path ), false );
		wp_localize_script( $handle, $object_name, $data );
		wp_enqueue_script( $handle );
	}

	/**
	 * Whether the cookie detection tracer should load (administrators in detection mode).
	 *
	 * @return bool
	 */
	private function should_enqueue_cookie_tracer(): bool {
		return $this->settings->is_enabled()
			&& $this->settings->is_detection_mode()
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Get asset version from file modified time.
	 *
	 * @param string $path Relative path under plugin dir.
	 * @return string
	 */
	private function asset_version( string $path ): string {
		$full = OVEN_PLUGIN_DIR . $path;
		return (string) ( file_exists( $full ) ? filemtime( $full ) : OVEN_VERSION );
	}

	/**
	 * Enqueue cookie consent assets and config.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		// When "Hide from guests" is on, do not show the consent modal to non-logged-in users.
		if ( $this->settings->is_hide_from_guests() && ! is_user_logged_in() ) {
			return;
		}

		$cookieconsent_dir = 'assets/cookieconsent/';
		$css_path          = $cookieconsent_dir . 'cookieconsent.css';
		$css_overrides     = 'assets/css/oven-consent-overrides.css';
		$js_lib_path       = $cookieconsent_dir . 'script.js';
		$js_init_path      = 'assets/js/oven-consent.js';

		$css_full   = OVEN_PLUGIN_DIR . $css_path;
		$css_over_full = OVEN_PLUGIN_DIR . $css_overrides;
		$js_lib_full = OVEN_PLUGIN_DIR . $js_lib_path;
		$js_init_full = OVEN_PLUGIN_DIR . $js_init_path;

		if ( ! file_exists( $css_full ) || ! file_exists( $js_lib_full ) || ! file_exists( $js_init_full ) ) {
			return;
		}

		// OVEN_PLUGIN_URL uses the actual plugin path (e.g. .../plugins/Oven/) so assets resolve.
		wp_enqueue_style(
			'oven-cookieconsent',
			OVEN_PLUGIN_URL . $css_path,
			array(),
			$this->asset_version( $css_path )
		);
		wp_enqueue_style(
			'oven-consent-overrides',
			OVEN_PLUGIN_URL . $css_overrides,
			array( 'oven-cookieconsent' ),
			file_exists( $css_over_full ) ? (string) filemtime( $css_over_full ) : OVEN_VERSION
		);

		wp_enqueue_script(
			'oven-cookieconsent-lib',
			OVEN_PLUGIN_URL . $js_lib_path,
			array(),
			$this->asset_version( $js_lib_path ),
			true
		);

		wp_enqueue_script(
			'oven-consent',
			OVEN_PLUGIN_URL . $js_init_path,
			array( 'oven-cookieconsent-lib' ),
			$this->asset_version( $js_init_path ),
			true
		);

		$config = $this->get_consent_config_for_js();
		wp_localize_script( 'oven-consent', 'ovenConsentConfig', $config );
	}

	/**
	 * Build consent config for JavaScript (categories, revision, logged-in consent cookie value).
	 *
	 * @return array<string, mixed>
	 */
	private function get_consent_config_for_js(): array {
		$consent_config = $this->cookie_manager->get_consent_config();
		$revision       = $this->settings->get_revision();
		$is_logged_in   = is_user_logged_in();
		$user_consent   = null;

		if ( $is_logged_in ) {
			$stored = get_user_meta( get_current_user_id(), Settings::USER_CONSENT_META, true );
			if ( is_array( $stored ) && isset( $stored['revision'] ) && (int) $stored['revision'] === $revision ) {
				$user_consent = $this->build_cookie_value_from_user_meta( $stored );
			}
		}

		$lang = get_locale();
		if ( strlen( $lang ) > 2 ) {
			$lang = substr( $lang, 0, 2 );
		}

		$nonessential_body = isset( $consent_config['nonessential_table']['body'] ) && is_array( $consent_config['nonessential_table']['body'] )
			? $consent_config['nonessential_table']['body']
			: array();
		$no_nonessential_note = empty( $nonessential_body )
			? __( 'Currently, all cookies we use are essential for the website to operate. We do not use cookies to track you outside of this website. We care about your privacy. ❤️', 'oven-cookie-consent' )
			: '';

		$close_icon_url = '';
		$close_icon_path = 'assets/images/x.svg';
		if ( file_exists( OVEN_PLUGIN_DIR . $close_icon_path ) ) {
			$close_icon_url = OVEN_PLUGIN_URL . $close_icon_path;
		}

		return array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'restUrl'       => rest_url( 'oven/v1/' ),
			'nonceDetect'   => $is_logged_in && current_user_can( 'manage_options' ) ? wp_create_nonce( 'oven_detect_cookies' ) : '',
			'nonceConsent'  => $is_logged_in ? wp_create_nonce( 'oven_save_consent' ) : '',
			'revision'      => $revision,
			'categories'    => $consent_config['categories'],
			'essentialTable'   => $consent_config['essential_table'],
			'nonessentialTable' => $consent_config['nonessential_table'],
			'noNonessentialNote' => $no_nonessential_note,
			'loggedIn'      => $is_logged_in,
			'userConsentCookieValue' => $user_consent,
			'cookieName'    => 'oven_cc',
			'sessionVerifiedCookieName' => self::SESSION_VERIFIED_COOKIE,
			'closeIconUrl'  => $close_icon_url,
			'detectionMode' => $this->settings->is_detection_mode() && current_user_can( 'manage_options' ),
			'locale'        => $lang,
			'translations'  => $this->get_consent_translations(),
		);
	}

	/**
	 * Build consent modal and preferences modal translation strings (with optional privacy policy link).
	 *
	 * @return array<string, string>
	 */
	private function get_consent_translations(): array {
		$desc = __( 'We use cookies to ensure the site works and to improve your experience. You can accept all, only essential, or manage preferences.', 'oven-cookie-consent' );
		$saved_settings = $this->settings->get();
		$privacy_url    = isset( $saved_settings['privacy_policy_url'] ) ? (string) $saved_settings['privacy_policy_url'] : '';
		if ( $privacy_url !== '' ) {
			$desc .= ' ' . sprintf(
				'<a href="%s" target="_blank" rel="noopener" class="cc__link">%s</a>.',
				esc_url( $privacy_url ),
				esc_html__( 'Privacy policy', 'oven-cookie-consent' )
			);
		}
		return array(
			'title'               => __( 'We use cookies', 'oven-cookie-consent' ),
			'description'        => $desc,
			'acceptAll'           => __( 'Accept all', 'oven-cookie-consent' ),
			'rejectAll'           => __( 'Essential only', 'oven-cookie-consent' ),
			'managePreferences'  => __( 'Manage preferences', 'oven-cookie-consent' ),
			'preferencesTitle'    => __( 'Cookie preferences', 'oven-cookie-consent' ),
			'savePreferences'     => __( 'Save preferences', 'oven-cookie-consent' ),
			'close'               => __( 'Close', 'oven-cookie-consent' ),
			'necessaryTitle'      => __( 'Essential cookies', 'oven-cookie-consent' ),
			'necessaryDesc'       => __( 'These cookies are required for the website to function (e.g. login, security, preferences).', 'oven-cookie-consent' ),
			'nonessentialTitle'   => __( 'Non-essential cookies', 'oven-cookie-consent' ),
			'nonessentialDesc'    => __( 'These cookies are used for analytics or third-party features. You can disable them.', 'oven-cookie-consent' ),
			'revisionMessage'     => __( 'We have updated our cookie policy. Please review and accept again.', 'oven-cookie-consent' ),
		);
	}

	/**
	 * Build the same structure the CookieConsent library stores in its cookie, from user meta.
	 *
	 * @param array<string, mixed> $stored User meta consent.
	 * @return string|null JSON string for cookie value or null.
	 */
	private function build_cookie_value_from_user_meta( array $stored ): ?string {
		$revision = $this->settings->get_revision();
		$categories = $stored['categories'] ?? array();
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		$expiration = (int) ( $stored['expirationTime'] ?? 0 );
		if ( $expiration > 0 && $expiration < ( time() * 1000 ) ) {
			return null;
		}
		$cookie_data = array(
			'categories'        => $categories,
			'revision'           => $revision,
			'consentTimestamp'   => $stored['consentTimestamp'] ?? '',
			'consentId'          => $stored['consentId'] ?? '',
			'services'           => $stored['services'] ?? array(),
			'languageCode'       => $stored['languageCode'] ?? '',
			'expirationTime'     => $expiration,
			'lastConsentTimestamp' => $stored['lastConsentTimestamp'] ?? '',
		);
		return wp_json_encode( $cookie_data );
	}

	/**
	 * Config for oven-logged-in-consent.js (wp_localize_script).
	 *
	 * @return array<string, mixed>|null Null if bootstrap should not run.
	 */
	private function get_logged_in_consent_config(): ?array {
		$revision    = $this->settings->get_revision();
		$cookie_name = 'oven_cc';
		$base        = array(
			'cookieName'        => $cookie_name,
			'sessionCookieName' => self::SESSION_VERIFIED_COOKIE,
			'secureSuffix'      => is_ssl() ? '; Secure' : '',
		);

		$from_sess = $this->get_consent_from_session_cookie( $revision, get_current_user_id() );
		if ( $from_sess !== null ) {
			return array_merge(
				$base,
				array(
					'mode'            => 'set',
					'cookieValue'     => $from_sess['value'],
					'expiresSec'      => $from_sess['expires_sec'],
					'sessionPayload'  => '',
				)
			);
		}

		$user_id = get_current_user_id();
		$stored  = get_user_meta( $user_id, Settings::USER_CONSENT_META, true );

		if ( ! is_array( $stored ) || (int) ( $stored['revision'] ?? 0 ) !== $revision ) {
			$from_request = $this->get_consent_from_request_cookie( $cookie_name, $revision );
			if ( $from_request !== null ) {
				return $this->build_logged_in_sync_config( $base, $from_request );
			}
			return array_merge( $base, array( 'mode' => 'clear' ) );
		}

		$cookie_value = $this->build_cookie_value_from_user_meta( $stored );
		if ( $cookie_value === null ) {
			$from_request = $this->get_consent_from_request_cookie( $cookie_name, $revision );
			if ( $from_request !== null ) {
				return $this->build_logged_in_sync_config( $base, $from_request );
			}
			return array_merge( $base, array( 'mode' => 'clear' ) );
		}

		$expires     = (int) ( $stored['expirationTime'] ?? 0 );
		$expires_sec = $expires > 0 ? (int) floor( $expires / 1000 ) : 0;
		$session     = base64_encode(
			(string) wp_json_encode(
				array(
					'r' => $revision,
					'v' => $cookie_value,
					'e' => $expires_sec,
					'u' => $user_id,
				)
			)
		);

		return array_merge(
			$base,
			array(
				'mode'           => 'set',
				'cookieValue'    => $cookie_value,
				'expiresSec'     => $expires_sec,
				'sessionPayload' => $session !== false ? $session : '',
			)
		);
	}

	/**
	 * @param array<string, mixed>                                    $base         Shared config keys.
	 * @param array{value: string, decoded: array<string, mixed>} $from_request Request cookie data.
	 * @return array<string, mixed>
	 */
	private function build_logged_in_sync_config( array $base, array $from_request ): array {
		$decoded     = $from_request['decoded'];
		$expires     = (int) ( $decoded['expirationTime'] ?? 0 );
		$expires_sec = $expires > 0 ? (int) floor( $expires / 1000 ) : 0;

		return array_merge(
			$base,
			array(
				'mode'        => 'sync',
				'cookieValue' => $from_request['value'],
				'expiresSec'  => $expires_sec,
				'syncPayload' => $decoded,
			)
		);
	}

	/**
	 * Filter script tags: block scripts that set non-essential cookies until user opts in.
	 * Adds type="text/plain", data-category="nonessential", and moves src to data-src so CookieConsent can enable them.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script src.
	 * @return string
	 */
	public function block_nonessential_scripts( string $tag, string $handle, string $src ): string {
		if ( ! $this->settings->is_enabled() || is_admin() ) {
			return $tag;
		}
		$block_urls = $this->cookie_manager->get_script_urls_to_block();
		if ( empty( $block_urls ) ) {
			return $tag;
		}
		$normalized = Cookie_Manager::normalize_script_url( $src, home_url( '/' ) );
		if ( $normalized === '' ) {
			return $tag;
		}
		if ( ! in_array( $normalized, $block_urls, true ) ) {
			return $tag;
		}
		// Prevent execution until CookieConsent enables it: type="text/plain", data-category="nonessential", src -> data-src.
		if ( strpos( $tag, 'data-category="nonessential"' ) !== false ) {
			return $tag;
		}
		// Only modify tags that have a src attribute so we can move it to data-src.
		if ( ! preg_match( '#\ssrc=(["\'])([^"\']+)\1#i', $tag, $m ) ) {
			return $tag;
		}
		// Move src to data-src so the browser does not load the script until CookieConsent enables it.
		$tag = str_replace( $m[0], ' data-src="' . esc_attr( $m[2] ) . '"', $tag );
		// Force type="text/plain" so the script does not execute until enabled.
		if ( preg_match( '#\stype=(["\'])([^"\']*)\1#i', $tag ) ) {
			$tag = preg_replace( '#\stype=(["\'])([^"\']*)\1#i', ' type="text/plain"', $tag );
		} else {
			$tag = preg_replace( '#(<script)(\s)#i', '$1 type="text/plain"$2', $tag );
		}
		$tag = str_ireplace( ' type="text/javascript"', ' type="text/plain"', $tag );
		$tag = str_ireplace( ' type=\'text/javascript\'', ' type="text/plain"', $tag );
		if ( strpos( $tag, 'data-category=' ) === false ) {
			$tag = preg_replace( '#(<script\s)#i', '$1data-category="nonessential" ', $tag );
		}
		return $tag;
	}

	/**
	 * Read and validate the session cookie (we've already checked DB this session; consent value is stored in the cookie).
	 * Validates revision and user ID so admin logout → regular user login does not reuse the wrong consent.
	 *
	 * @param int $revision   Current revision.
	 * @param int $user_id   Current user ID (must match the cookie).
	 * @return array{value: string, expires_sec: int}|null
	 */
	private function get_consent_from_session_cookie( int $revision, int $user_id ): ?array {
		$raw = Consent_Sanitizer::get_cookie_value( self::SESSION_VERIFIED_COOKIE );
		if ( $raw === '' ) {
			return null;
		}
		$decoded_b64 = base64_decode( $raw, true );
		if ( $decoded_b64 === false ) {
			return null;
		}
		$decoded = Consent_Sanitizer::json_decode_assoc( $decoded_b64 );
		if ( $decoded === null ) {
			return null;
		}
		$cookie_revision = isset( $decoded['r'] ) ? (int) $decoded['r'] : 0;
		$cookie_user_id  = isset( $decoded['u'] ) ? (int) $decoded['u'] : 0;
		$cookie_value    = isset( $decoded['v'] ) && is_string( $decoded['v'] ) ? $decoded['v'] : '';
		if ( $cookie_revision !== $revision || $cookie_user_id !== $user_id || $cookie_value === '' ) {
			return null;
		}
		$consent_decoded = Consent_Sanitizer::json_decode_assoc( $cookie_value );
		if ( $consent_decoded === null ) {
			return null;
		}
		$sanitized_consent = Consent_Sanitizer::sanitize_stored_consent_payload( $consent_decoded );
		if ( (int) $sanitized_consent['revision'] !== $revision ) {
			return null;
		}
		$expires_sec = isset( $decoded['e'] ) ? max( 0, (int) $decoded['e'] ) : 0;
		$encoded     = wp_json_encode( $sanitized_consent );
		if ( ! is_string( $encoded ) || $encoded === '' ) {
			return null;
		}
		return array( 'value' => $encoded, 'expires_sec' => $expires_sec );
	}

	/**
	 * Read and validate consent from the request cookie (e.g. user accepted but save to user meta failed).
	 *
	 * @param string $cookie_name Cookie name.
	 * @param int    $revision   Current revision.
	 * @return array{value: string, decoded: array<string, mixed>}|null Raw value and decoded data, or null if invalid/missing.
	 */
	private function get_consent_from_request_cookie( string $cookie_name, int $revision ): ?array {
		$raw = Consent_Sanitizer::get_cookie_value( $cookie_name );
		if ( $raw === '' ) {
			return null;
		}
		$decoded = Consent_Sanitizer::json_decode_assoc( $raw );
		if ( $decoded === null ) {
			return null;
		}
		$sanitized = Consent_Sanitizer::sanitize_stored_consent_payload( $decoded );
		if ( (int) $sanitized['revision'] !== $revision ) {
			return null;
		}
		$encoded = wp_json_encode( $sanitized );
		if ( ! is_string( $encoded ) || $encoded === '' ) {
			return null;
		}
		return array( 'value' => $encoded, 'decoded' => $sanitized );
	}
}
