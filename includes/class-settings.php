<?php
/**
 * Oven settings.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Option name for plugin settings.
	 */
	public const OPTION_NAME = 'oven_settings';

	/**
	 * Option name for detected cookies.
	 */
	public const COOKIES_OPTION = 'oven_detected_cookies';

	/**
	 * Option name for revision.
	 */
	public const REVISION_OPTION = 'oven_cookie_revision';

	/**
	 * Option name for script-to-cookie mapping (script URL => non-essential cookie names).
	 */
	public const SCRIPT_COOKIE_MAP_OPTION = 'oven_script_cookie_map';

	/**
	 * Option name for admin overrides (exact cookie name or pattern => essential|nonessential).
	 */
	public const COOKIE_OVERRIDES_OPTION = 'oven_cookie_overrides';

	/**
	 * Option name for cookie descriptions (cookie name => description). Stored separately so detection/merge never overwrites them.
	 */
	public const COOKIE_DESCRIPTIONS_OPTION = 'oven_cookie_descriptions';

	/**
	 * User meta key for consent (logged-in users).
	 */
	public const USER_CONSENT_META = 'oven_cookie_consent';

	/**
	 * User meta key for consent history (logged-in users; admins can view on profile).
	 */
	public const USER_CONSENT_HISTORY_META = 'oven_cookie_consent_history';

	/**
	 * Default essential cookie name patterns (WordPress core and common auth/session).
	 *
	 * @var string[]
	 */
	private const DEFAULT_ESSENTIAL_PATTERNS = array(
		'wordpress_',
		'wp-settings-',
		'wp-settings-time-',
		'comment_author_',
		'comment_author_email_',
		'comment_author_url_',
		'PHPSESSID',
		'wp_postpass_',
		'wp-resetpass-',
		'wordpress_logged_in_',
		'wordpress_test_cookie',
	);

	/**
	 * Initialize settings.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ), 10 );
		add_action( 'admin_init', array( $this, 'register_settings' ), 10 );
		add_action( 'admin_init', array( $this, 'handle_cookie_override_actions' ), 1 );
		add_action( 'admin_notices', array( $this, 'settings_admin_notices' ), 10 );
		add_filter( 'plugin_action_links_' . OVEN_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ), 10, 1 );
	}

	/**
	 * Register settings under Settings menu.
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Oven Cookie Consent', 'oven-cookie-consent' ),
			__( 'Oven', 'oven-cookie-consent' ),
			'manage_options',
			'oven',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings and sections.
	 */
	public function register_settings(): void {
		register_setting(
			'oven_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'oven_main',
			__( 'Cookie Consent', 'oven-cookie-consent' ),
			array( $this, 'section_main_callback' ),
			'oven'
		);

		add_settings_field(
			'oven_enabled',
			__( 'Enable cookie consent', 'oven-cookie-consent' ),
			array( $this, 'field_checkbox' ),
			'oven',
			'oven_main',
			array(
				'label_for' => 'oven_enabled',
				'option_key' => 'enabled',
				'description' => __( 'Show the cookie consent modal to visitors.', 'oven-cookie-consent' ),
			)
		);

		add_settings_field(
			'oven_detection_mode',
			__( 'Cookie detection mode', 'oven-cookie-consent' ),
			array( $this, 'field_checkbox' ),
			'oven',
			'oven_main',
			array(
				'label_for' => 'oven_detection_mode',
				'option_key' => 'detection_mode',
				'description' => __( 'When enabled (and you are logged in as an administrator), browsing the site will detect cookies and add them to the list. New cookies trigger a revision so all visitors must re-accept.', 'oven-cookie-consent' ),
			)
		);

		add_settings_field(
			'oven_hide_from_guests',
			__( 'Hide from guests', 'oven-cookie-consent' ),
			array( $this, 'field_checkbox' ),
			'oven',
			'oven_main',
			array(
				'label_for'   => 'oven_hide_from_guests',
				'option_key'  => 'hide_from_guests',
				'description' => __( 'When checked, the cookie consent modal is only shown to logged-in users. Guests will not see the banner.', 'oven-cookie-consent' ),
			)
		);

		add_settings_field(
			'oven_privacy_policy_url',
			__( 'Privacy policy URL', 'oven-cookie-consent' ),
			array( $this, 'field_privacy_policy_url' ),
			'oven',
			'oven_main',
			array( 'label_for' => 'oven_privacy_policy_url' )
		);

		add_settings_section(
			'oven_detected',
			'', // Title output in callback above the table as "Cookies".
			array( $this, 'section_detected_callback' ),
			'oven'
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return $this->get_defaults();
		}

		$out = array(
			'enabled'            => ! empty( $input['enabled'] ),
			'detection_mode'     => ! empty( $input['detection_mode'] ),
			'hide_from_guests'   => ! empty( $input['hide_from_guests'] ),
			'privacy_policy_url' => isset( $input['privacy_policy_url'] ) ? esc_url_raw( trim( (string) $input['privacy_policy_url'] ) ) : '',
		);

		return $out;
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return array(
			'enabled'            => false,
			'detection_mode'     => false,
			'hide_from_guests'   => false,
			'privacy_policy_url' => '',
		);
	}

	/**
	 * Get current settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->get_defaults() );
	}

	/**
	 * Check if consent is enabled.
	 */
	public function is_enabled(): bool {
		$s = $this->get();
		return ! empty( $s['enabled'] );
	}

	/**
	 * Check if detection mode is enabled.
	 */
	public function is_detection_mode(): bool {
		$s = $this->get();
		return ! empty( $s['detection_mode'] );
	}

	/**
	 * Check if cookie consent is hidden from guests (only shown to logged-in users).
	 */
	public function is_hide_from_guests(): bool {
		$s = $this->get();
		return ! empty( $s['hide_from_guests'] );
	}

	/**
	 * Get current revision number.
	 */
	public function get_revision(): int {
		return (int) get_option( self::REVISION_OPTION, 1 );
	}

	/**
	 * Get default essential cookie patterns.
	 *
	 * @return string[]
	 */
	public function get_default_essential_patterns(): array {
		return self::DEFAULT_ESSENTIAL_PATTERNS;
	}

	/**
	 * Get admin cookie overrides: exact names and patterns (substring match) => essential|nonessential.
	 *
	 * @return array{exact: array<string, string>, patterns: array<string, string>}
	 */
	public function get_cookie_overrides(): array {
		$raw = get_option( self::COOKIE_OVERRIDES_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array( 'exact' => array(), 'patterns' => array() );
		}
		$exact    = isset( $raw['exact'] ) && is_array( $raw['exact'] ) ? $raw['exact'] : array();
		$patterns = isset( $raw['patterns'] ) && is_array( $raw['patterns'] ) ? $raw['patterns'] : array();
		$allowed  = array( 'essential', 'nonessential' );
		$exact    = array_filter( $exact, fn( $v ) => is_string( $v ) && in_array( $v, $allowed, true ) );
		$patterns = array_filter( $patterns, fn( $v ) => is_string( $v ) && in_array( $v, $allowed, true ) );
		return array( 'exact' => $exact, 'patterns' => $patterns );
	}

	/**
	 * Save cookie overrides (exact and patterns).
	 *
	 * @param array{exact?: array<string, string>, patterns?: array<string, string>} $overrides New overrides to save.
	 */
	public function save_cookie_overrides( array $overrides ): void {
		$current = $this->get_cookie_overrides();
		if ( isset( $overrides['exact'] ) && is_array( $overrides['exact'] ) ) {
			$current['exact'] = $overrides['exact'];
		}
		if ( isset( $overrides['patterns'] ) && is_array( $overrides['patterns'] ) ) {
			$current['patterns'] = $overrides['patterns'];
		}
		update_option( self::COOKIE_OVERRIDES_OPTION, $current );
	}

	/**
	 * Whether the cookie has an exact (per-name) override.
	 *
	 * @param string $name Cookie name.
	 * @return bool
	 */
	public function has_exact_override( string $name ): bool {
		$overrides = $this->get_cookie_overrides();
		return isset( $overrides['exact'][ $name ] );
	}

	/**
	 * Get effective essential classification for display (same logic as Cookie_Manager).
	 *
	 * @param string $name Cookie name.
	 * @return bool True if essential.
	 */
	public function get_effective_essential_for_display( string $name ): bool {
		$overrides = $this->get_cookie_overrides();
		$name      = trim( $name );
		if ( $name === '' ) {
			return true;
		}
		if ( isset( $overrides['exact'][ $name ] ) ) {
			return $overrides['exact'][ $name ] === 'essential';
		}
		foreach ( $overrides['patterns'] as $pattern => $value ) {
			if ( $pattern !== '' && stripos( $name, $pattern ) !== false ) {
				return $value === 'essential';
			}
		}
		$patterns = $this->get_default_essential_patterns();
		foreach ( $patterns as $pattern ) {
			if ( $pattern !== '' && substr( $name, 0, strlen( $pattern ) ) === $pattern ) {
				return true;
			}
			if ( $name === $pattern ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle override actions from settings page (set/remove exact or pattern).
	 * Add-pattern is handled via form POST to this page (no AJAX) so it works reliably.
	 */
	public function handle_cookie_override_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page === '' && isset( $_POST['page'] ) ) {
			$page = sanitize_text_field( wp_unslash( $_POST['page'] ) );
		}
		if ( $page !== 'oven' ) {
			return;
		}

		// Handle save cookie descriptions form POST. Store in separate option so detection/merge never overwrites.
		if ( $this->is_post_request() && isset( $_POST['oven_save_cookie_descriptions'] ) ) {
			$redirect_url = admin_url( 'options-general.php?page=oven' );
			$nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'oven_cookie_override' ) ) {
				wp_safe_redirect( add_query_arg( 'oven_error', 'nonce', $redirect_url ) );
				exit;
			}
			$cookies = get_option( self::COOKIES_OPTION, array() );
			if ( ! is_array( $cookies ) ) {
				$cookies = array();
			}
			$descriptions = get_option( self::COOKIE_DESCRIPTIONS_OPTION, array() );
			if ( ! is_array( $descriptions ) ) {
				$descriptions = array();
			}
			// Use parallel arrays (key[] and val[]) so cookie names like __stripe_mid are not lost — PHP can strip $_POST keys starting with __.
			$keys = array();
			$vals = array();
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified above; each element sanitized below.
			$desc_keys = isset( $_POST['oven_cookie_desc_key'] ) && is_array( $_POST['oven_cookie_desc_key'] )
				? wp_unslash( $_POST['oven_cookie_desc_key'] )
				: array();
			$desc_vals = isset( $_POST['oven_cookie_desc_val'] ) && is_array( $_POST['oven_cookie_desc_val'] )
				? wp_unslash( $_POST['oven_cookie_desc_val'] )
				: array();
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			foreach ( array_values( $desc_keys ) as $key ) {
				$keys[] = sanitize_text_field( (string) $key );
			}
			foreach ( array_values( $desc_vals ) as $val ) {
				$vals[] = sanitize_textarea_field( (string) $val );
			}
			$len  = min( count( $keys ), count( $vals ) );
			for ( $i = 0; $i < $len; $i++ ) {
				$cookie_name = sanitize_text_field( (string) $keys[ $i ] );
				if ( $cookie_name !== '' && isset( $cookies[ $cookie_name ] ) && is_array( $cookies[ $cookie_name ] ) ) {
					$descriptions[ $cookie_name ] = sanitize_textarea_field( (string) $vals[ $i ] );
				}
			}
			update_option( self::COOKIE_DESCRIPTIONS_OPTION, $descriptions );
			wp_safe_redirect( add_query_arg( 'oven_updated', 'descriptions', $redirect_url ) );
			exit;
		}

		// Handle manual add-cookie form POST.
		if ( $this->is_post_request() && isset( $_POST['oven_manual_add_cookie'] ) ) {
			$redirect_url = admin_url( 'options-general.php?page=oven' );
			$nonce        = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'oven_cookie_override' ) ) {
				wp_safe_redirect( add_query_arg( 'oven_error', 'nonce', $redirect_url ) );
				exit;
			}
			$cookie_name = isset( $_POST['oven_manual_cookie_name'] ) ? sanitize_text_field( wp_unslash( $_POST['oven_manual_cookie_name'] ) ) : '';
			$classification = isset( $_POST['oven_manual_cookie_type'] ) ? sanitize_text_field( wp_unslash( $_POST['oven_manual_cookie_type'] ) ) : '';
			$script_url = isset( $_POST['oven_manual_script_url'] ) ? esc_url_raw( wp_unslash( $_POST['oven_manual_script_url'] ) ) : '';
			$description = isset( $_POST['oven_manual_cookie_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oven_manual_cookie_desc'] ) ) : '';
			if ( $cookie_name !== '' && ( $classification === 'essential' || $classification === 'nonessential' ) ) {
				$essential = $classification === 'essential';
				\oven()->get_cookie_manager()->add_cookie_manually( $cookie_name, $essential, $script_url, $description );
				if ( trim( $description ) !== '' ) {
					$descriptions = get_option( self::COOKIE_DESCRIPTIONS_OPTION, array() );
					if ( ! is_array( $descriptions ) ) {
						$descriptions = array();
					}
					$descriptions[ $cookie_name ] = trim( $description );
					update_option( self::COOKIE_DESCRIPTIONS_OPTION, $descriptions );
				}
			}
			wp_safe_redirect( add_query_arg( 'oven_updated', 'cookie_added', $redirect_url ) );
			exit;
		}

		// Handle add-pattern form POST (form posts to this same page; no AJAX).
		if ( $this->is_post_request() && isset( $_POST['oven_add_pattern'] ) ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( wp_verify_nonce( $nonce, 'oven_cookie_override' ) ) {
				$pattern = isset( $_POST['oven_pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['oven_pattern'] ) ) : '';
				$value   = isset( $_POST['oven_pattern_value'] ) ? sanitize_text_field( wp_unslash( $_POST['oven_pattern_value'] ) ) : '';
				if ( $pattern !== '' && ( $value === 'essential' || $value === 'nonessential' ) ) {
					$overrides = $this->get_cookie_overrides();
					if ( ! isset( $overrides['patterns'] ) || ! is_array( $overrides['patterns'] ) ) {
						$overrides['patterns'] = array();
					}
					$overrides['patterns'][ $pattern ] = $value;
					$this->save_cookie_overrides( $overrides );
				}
			}
			wp_safe_redirect( admin_url( 'options-general.php?page=oven' ) );
			exit;
		}

		$action = isset( $_REQUEST['oven_override_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['oven_override_action'] ) ) : '';
		if ( $action === '' ) {
			return;
		}
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oven_cookie_override' ) ) {
			return;
		}

		$overrides = $this->get_cookie_overrides();

		if ( $action === 'set_exact' ) {
			$cookie = isset( $_REQUEST['cookie'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cookie'] ) ) : '';
			$value  = isset( $_REQUEST['value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['value'] ) ) : '';
			if ( $cookie !== '' && ( $value === 'essential' || $value === 'nonessential' ) ) {
				$overrides['exact'][ $cookie ] = $value;
				$this->save_cookie_overrides( $overrides );
			}
		} elseif ( $action === 'remove_exact' ) {
			$cookie = isset( $_REQUEST['cookie'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cookie'] ) ) : '';
			if ( $cookie !== '' && isset( $overrides['exact'][ $cookie ] ) ) {
				unset( $overrides['exact'][ $cookie ] );
				$this->save_cookie_overrides( $overrides );
			}
		} elseif ( $action === 'remove_pattern' ) {
			$pattern = isset( $_REQUEST['pattern'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pattern'] ) ) : '';
			if ( $pattern !== '' && isset( $overrides['patterns'][ $pattern ] ) ) {
				unset( $overrides['patterns'][ $pattern ] );
				$this->save_cookie_overrides( $overrides );
			}
		}

		$redirect = remove_query_arg( array( 'oven_override_action', 'cookie', 'value', 'pattern', '_wpnonce' ), wp_get_referer() ?: admin_url( 'options-general.php?page=oven' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Section callback for main.
	 */
	public function section_main_callback(): void {
		echo '<p class="description">' . esc_html__( 'Configure how Oven displays the cookie consent banner and detects cookies.', 'oven-cookie-consent' ) . '</p>';
	}

	/**
	 * Section callback for detected cookies.
	 */
	public function section_detected_callback(): void {
		$cookies = get_option( self::COOKIES_OPTION, array() );
		if ( ! is_array( $cookies ) ) {
			$cookies = array();
		}
		$descriptions = get_option( self::COOKIE_DESCRIPTIONS_OPTION, array() );
		if ( ! is_array( $descriptions ) ) {
			$descriptions = array();
		}
		$override_nonce = wp_create_nonce( 'oven_cookie_override' );
		$base_url       = admin_url( 'options-general.php?page=oven' );

		// Manual add-cookie form (above detected table).
		echo '<form method="post" action="' . esc_url( $base_url ) . '" style="max-width: 800px; margin-bottom: 1.5em;">';
		echo '<input type="hidden" name="page" value="oven" />';
		echo '<input type="hidden" name="oven_manual_add_cookie" value="1" />';
		wp_nonce_field( 'oven_cookie_override', '_wpnonce', true, true );
		echo '<p><label for="oven_manual_cookie_name"><strong>' . esc_html__( 'Add cookie manually', 'oven-cookie-consent' ) . '</strong></label></p>';
		echo '<p style="margin-bottom: 0.5em;"><input type="text" id="oven_manual_cookie_name" name="oven_manual_cookie_name" placeholder="' . esc_attr__( 'Cookie name', 'oven-cookie-consent' ) . '" value="" required style="width: 240px;" /> ';
		echo '<select name="oven_manual_cookie_type" id="oven_manual_cookie_type">';
		echo '<option value="essential">' . esc_html__( 'Essential', 'oven-cookie-consent' ) . '</option>';
		echo '<option value="nonessential" selected>' . esc_html__( 'Non-essential', 'oven-cookie-consent' ) . '</option>';
		echo '</select></p>';
		echo '<p class="description" style="margin-top: 0.25em;"><label for="oven_manual_script_url">' . esc_html__( 'Optional: script URL to block until consent (non-essential only)', 'oven-cookie-consent' ) . '</label><br />';
		echo '<input type="url" id="oven_manual_script_url" name="oven_manual_script_url" placeholder="https://example.com/script.js" value="" style="width: 100%; max-width: 480px; margin-top: 4px;" /></p>';
		echo '<p class="description" style="margin-top: 0.25em;"><label for="oven_manual_cookie_desc">' . esc_html__( 'Optional: description (what this cookie does, shown to visitors)', 'oven-cookie-consent' ) . '</label><br />';
		echo '<input type="text" id="oven_manual_cookie_desc" name="oven_manual_cookie_desc" placeholder="' . esc_attr__( 'e.g. Remembers your language preference', 'oven-cookie-consent' ) . '" value="" style="width: 100%; max-width: 480px; margin-top: 4px;" /></p>';
		echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Add cookie', 'oven-cookie-consent' ) . '" /></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $base_url ) . '" style="max-width: 960px;">';
		echo '<input type="hidden" name="page" value="oven" />';
		echo '<input type="hidden" name="oven_save_cookie_descriptions" value="1" />';
		wp_nonce_field( 'oven_cookie_override', '_wpnonce', true, true );
		echo '<h2 class="title">' . esc_html__( 'Cookies', 'oven-cookie-consent' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Cookies detected on your site or that you manually added. You can override classification and set a description for each cookie so visitors see what it does in the consent modal.', 'oven-cookie-consent' ) . '</p>';
		echo '<table class="widefat striped" style="max-width: 960px;" aria-label="' . esc_attr__( 'Detected cookies list', 'oven-cookie-consent' ) . '">';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Cookie name', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Category', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Description (shown to visitors)', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'oven-cookie-consent' ) . '</th></tr></thead><tbody>';
		if ( empty( $cookies ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No cookies detected yet. Enable cookie detection mode and browse your site.', 'oven-cookie-consent' ) . '</td></tr>';
		} else {
			foreach ( $cookies as $name => $data ) {
				$is_essential = $this->get_effective_essential_for_display( $name );
				$category     = $is_essential ? __( 'Essential', 'oven-cookie-consent' ) : __( 'Non-essential', 'oven-cookie-consent' );
				$has_override = $this->has_exact_override( $name );
				$desc_value   = isset( $descriptions[ $name ] ) ? $descriptions[ $name ] : ( isset( $data['description'] ) ? $data['description'] : '' );
				$actions      = array();
				if ( $has_override ) {
					$revert_url = add_query_arg(
						array(
							'oven_override_action' => 'remove_exact',
							'cookie'               => $name,
							'_wpnonce'             => $override_nonce,
						),
						$base_url
					);
					$actions[] = '<a href="' . esc_url( $revert_url ) . '">' . esc_html__( 'Revert to auto', 'oven-cookie-consent' ) . '</a>';
				} else {
					$set_essential_url = add_query_arg(
						array(
							'oven_override_action' => 'set_exact',
							'cookie'               => $name,
							'value'                => 'essential',
							'_wpnonce'             => $override_nonce,
						),
						$base_url
					);
					$set_nonessential_url = add_query_arg(
						array(
							'oven_override_action' => 'set_exact',
							'cookie'               => $name,
							'value'                => 'nonessential',
							'_wpnonce'             => $override_nonce,
						),
						$base_url
					);
					$actions[] = '<a href="' . esc_url( $set_essential_url ) . '">' . esc_html__( 'Mark as essential', 'oven-cookie-consent' ) . '</a>';
					$actions[] = ' | ';
					$actions[] = '<a href="' . esc_url( $set_nonessential_url ) . '">' . esc_html__( 'Mark as non-essential', 'oven-cookie-consent' ) . '</a>';
				}
				echo '<tr><td><code>' . esc_html( $name ) . '</code></td><td>' . esc_html( $category );
				if ( $has_override ) {
					echo ' <span class="description">(' . esc_html__( 'overridden', 'oven-cookie-consent' ) . ')</span>';
				}
				echo '</td><td><input type="hidden" name="oven_cookie_desc_key[]" value="' . esc_attr( $name ) . '" /><input type="text" name="oven_cookie_desc_val[]" value="' . esc_attr( $desc_value ) . '" class="large-text" placeholder="' . esc_attr__( 'What this cookie does (optional)', 'oven-cookie-consent' ) . '" aria-label="' . esc_attr__( 'Description for', 'oven-cookie-consent' ) . ' ' . esc_attr( $name ) . '" /></td><td>' . wp_kses( implode( '', $actions ), array( 'a' => array( 'href' => true ) ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		if ( ! empty( $cookies ) ) {
			echo '<p style="margin-top: 8px;"><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save descriptions', 'oven-cookie-consent' ) . '" /></p>';
		}
		echo '</form>';

		// Pattern overrides: add form and list.
		$overrides = $this->get_cookie_overrides();
		echo '<div class="oven-pattern-overrides" style="margin-top: 1.5em;">';
		echo '<h3 class="title">' . esc_html__( 'Classification by pattern', 'oven-cookie-consent' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Cookies whose name contains the text below will be classified as essential or non-essential. For example, any cookie with "stripe" in the name can be marked essential.', 'oven-cookie-consent' ) . '</p>';
		echo '<form id="oven-add-pattern-form" method="post" action="' . esc_url( $base_url ) . '" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 1em;">';
		echo '<input type="hidden" name="page" value="oven" />';
		echo '<input type="hidden" name="oven_add_pattern" value="1" />';
		echo '<label for="oven_pattern" class="screen-reader-text">' . esc_html__( 'Pattern (substring in cookie name)', 'oven-cookie-consent' ) . '</label>';
		echo '<input type="text" id="oven_pattern" name="oven_pattern" placeholder="' . esc_attr__( 'e.g. stripe', 'oven-cookie-consent' ) . '" size="20" />';
		echo '<select name="oven_pattern_value" id="oven_pattern_value" aria-label="' . esc_attr__( 'Classification', 'oven-cookie-consent' ) . '">';
		echo '<option value="essential">' . esc_html__( 'Essential', 'oven-cookie-consent' ) . '</option>';
		echo '<option value="nonessential">' . esc_html__( 'Non-essential', 'oven-cookie-consent' ) . '</option>';
		echo '</select>';
		wp_nonce_field( 'oven_cookie_override', '_wpnonce', true, true );
		echo '<input type="submit" class="button button-secondary" value="' . esc_attr__( 'Add pattern rule', 'oven-cookie-consent' ) . '" />';
		echo '</form>';
		if ( ! empty( $overrides['patterns'] ) ) {
			echo '<table class="widefat striped" style="max-width: 500px;" aria-label="' . esc_attr__( 'Pattern overrides', 'oven-cookie-consent' ) . '"><thead><tr><th scope="col">' . esc_html__( 'Pattern (contains)', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Classification', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Action', 'oven-cookie-consent' ) . '</th></tr></thead><tbody>';
			foreach ( $overrides['patterns'] as $pattern => $value ) {
				$remove_url = add_query_arg(
					array(
						'oven_override_action' => 'remove_pattern',
						'pattern'             => $pattern,
						'_wpnonce'             => $override_nonce,
					),
					$base_url
				);
				echo '<tr><td><code>' . esc_html( $pattern ) . '</code></td><td>' . esc_html( $value === 'essential' ? __( 'Essential', 'oven-cookie-consent' ) : __( 'Non-essential', 'oven-cookie-consent' ) ) . '</td><td><a href="' . esc_url( $remove_url ) . '">' . esc_html__( 'Remove', 'oven-cookie-consent' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">' . esc_html__( 'No pattern rules yet.', 'oven-cookie-consent' ) . '</p>';
		}
		echo '</div>';

		$script_map = get_option( self::SCRIPT_COOKIE_MAP_OPTION, array() );
		if ( is_array( $script_map ) && ! empty( $script_map ) ) {
			echo '<h3 class="title" style="margin-top: 1.5em;">' . esc_html__( 'Detected Cookie Scripts', 'oven-cookie-consent' ) . '</h3>';
			echo '<p class="description" style="margin-top: 1em;">' . esc_html__( 'Scripts that set non-essential cookies are blocked until the user opts in. If you mark a cookie as essential above, its script is no longer blocked.', 'oven-cookie-consent' ) . '</p>';
			echo '<table class="widefat striped" style="max-width: 600px; margin-top: 0.5em;" aria-label="' . esc_attr__( 'Blocked scripts list', 'oven-cookie-consent' ) . '">';
			echo '<thead><tr><th scope="col">' . esc_html__( 'Script URL', 'oven-cookie-consent' ) . '</th><th scope="col">' . esc_html__( 'Sets cookies', 'oven-cookie-consent' ) . '</th></tr></thead><tbody>';
			foreach ( $script_map as $script_url => $cookie_list ) {
				$cookies_str = is_array( $cookie_list ) ? implode( ', ', $cookie_list ) : '';
				echo '<tr><td><code>' . esc_html( $script_url ) . '</code></td><td>' . esc_html( $cookies_str ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, mixed> $args Field args.
	 */
	public function field_checkbox( array $args ): void {
		$key         = $args['option_key'] ?? '';
		$description = $args['description'] ?? '';
		$settings    = $this->get();
		$checked     = ! empty( $settings[ $key ] );
		$id          = 'oven_' . $key;
		$name        = self::OPTION_NAME . '[' . $key . ']';
		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php if ( $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Render privacy policy URL field (optional link shown in consent modal).
	 *
	 * @param array<string, mixed> $args Field args.
	 */
	public function field_privacy_policy_url( array $args ): void {
		$id       = $args['label_for'] ?? 'oven_privacy_policy_url';
		$settings = $this->get();
		$value    = isset( $settings['privacy_policy_url'] ) ? $settings['privacy_policy_url'] : '';
		$name     = self::OPTION_NAME . '[privacy_policy_url]';
		?>
		<input type="url" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://" />
		<p class="description"><?php echo esc_html__( 'Optional. If set, a link to your privacy policy is shown in the consent modal (recommended for GDPR).', 'oven-cookie-consent' ); ?></p>
		<?php
	}

	/**
	 * Render a single settings section (used to avoid nesting the detected-cookies forms inside the main form).
	 *
	 * @param string $page       Option page slug.
	 * @param string $section_id Section id.
	 */
	private function render_single_section( string $page, string $section_id ): void {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[ $page ][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections[ $page ][ $section_id ];

		if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
			call_user_func( $section['callback'], $section );
		}

		if ( ! empty( $section['title'] ) ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>' . "\n";
		}

		if ( ! empty( $section['callback'] ) && ! empty( $section['description'] ) ) {
			echo '<p class="description">' . esc_html( $section['description'] ) . '</p>' . "\n";
		}

		if ( ! isset( $wp_settings_fields[ $page ][ $section_id ] ) ) {
			return;
		}

		echo '<table class="form-table" role="presentation">';
		do_settings_fields( $page, $section_id );
		echo '</table>';
	}

	/**
	 * Whether the current request is an HTTP POST.
	 *
	 * @return bool
	 */
	private function is_post_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';
		return $method === 'POST';
	}

	/**
	 * Show success or error notice on settings page.
	 */
	public function settings_admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'settings_page_oven' ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only redirect flags after verified admin POST actions.
		$updated = isset( $_GET['oven_updated'] ) ? sanitize_text_field( wp_unslash( $_GET['oven_updated'] ) ) : '';
		$error   = isset( $_GET['oven_error'] ) ? sanitize_text_field( wp_unslash( $_GET['oven_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $updated === 'descriptions' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cookie descriptions saved.', 'oven-cookie-consent' ) . '</p></div>';
		}
		if ( $updated === 'cookie_added' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cookie added.', 'oven-cookie-consent' ) . '</p></div>';
		}
		if ( $error === 'nonce' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Security check failed. Please try again.', 'oven-cookie-consent' ) . '</p></div>';
		}
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oven-cookie-consent' ) );
		}
		?>
		<div class="wrap oven-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post" id="oven-settings-form">
				<?php
				settings_fields( 'oven_settings_group' );
				echo '<div id="oven-main-section" class="oven-settings-section">';
				$this->render_single_section( 'oven', 'oven_main' );
				echo '</div>';
				submit_button( __( 'Save settings', 'oven-cookie-consent' ) );
				?>
			</form>
			<?php
			echo '<div id="oven-detected-section" class="oven-settings-section">';
			$this->render_single_section( 'oven', 'oven_detected' );
			echo '</div>';
			?>
		</div>
		<?php
	}

	/**
	 * Add plugin action links.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$url = admin_url( 'options-general.php?page=oven' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'oven-cookie-consent' ) . '</a>' );
		return $links;
	}
}
