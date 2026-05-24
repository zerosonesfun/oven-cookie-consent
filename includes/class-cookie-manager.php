<?php
/**
 * Oven cookie detection and classification.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cookie_Manager
 */
class Cookie_Manager {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings|null $settings Optional. Injected for tests.
	 */
	public function __construct( ?Settings $settings = null ) {
		$this->settings = $settings ?? new Settings();
	}

	/**
	 * Initialize.
	 */
	public function init(): void {}

	/**
	 * Get all detected cookies (essential and non-essential).
	 *
	 * @return array<string, array{essential: bool, first_seen?: string}>
	 */
	public function get_detected_cookies(): array {
		$raw = get_option( Settings::COOKIES_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return $raw;
	}

	/**
	 * Get essential cookie names only (respects admin overrides).
	 *
	 * @return string[]
	 */
	public function get_essential_cookie_names(): array {
		$all   = $this->get_detected_cookies();
		$names = array();
		foreach ( array_keys( $all ) as $name ) {
			if ( $this->get_effective_essential( $name ) ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Get non-essential cookie names only (respects admin overrides).
	 *
	 * @return string[]
	 */
	public function get_nonessential_cookie_names(): array {
		$all   = $this->get_detected_cookies();
		$names = array();
		foreach ( array_keys( $all ) as $name ) {
			if ( ! $this->get_effective_essential( $name ) ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Check if a cookie name is essential (admin overrides first, then default patterns).
	 *
	 * @param string $name Cookie name.
	 * @return bool
	 */
	public function is_essential_cookie( string $name ): bool {
		return $this->get_effective_essential( $name );
	}

	/**
	 * Get effective essential classification: admin overrides (exact then pattern) then default patterns.
	 *
	 * @param string $name Cookie name.
	 * @return bool True if essential.
	 */
	public function get_effective_essential( string $name ): bool {
		$overrides = $this->settings->get_cookie_overrides();
		$name      = trim( $name );
		if ( $name === '' ) {
			return true;
		}
		// 1) Exact override.
		if ( isset( $overrides['exact'][ $name ] ) ) {
			return $overrides['exact'][ $name ] === 'essential';
		}
		// 2) Pattern override (cookie name contains pattern, case-insensitive).
		foreach ( $overrides['patterns'] as $pattern => $value ) {
			if ( $pattern !== '' && stripos( $name, $pattern ) !== false ) {
				return $value === 'essential';
			}
		}
		// 3) Default essential patterns (prefix or exact match).
		$patterns = $this->settings->get_default_essential_patterns();
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
	 * Merge newly detected cookie names into storage and bump revision if any new.
	 *
	 * @param string[] $cookie_names List of cookie names currently present (e.g. from document.cookie).
	 * @return array{added: string[], revision_bumped: bool}
	 */
	public function merge_detected_cookies( array $cookie_names ): array {
		$current = $this->get_detected_cookies();
		$added   = array();
		foreach ( $cookie_names as $name ) {
			$name = trim( $name );
			if ( $name === '' ) {
				continue;
			}
			if ( ! isset( $current[ $name ] ) ) {
				$current[ $name ] = array(
					'essential'  => $this->is_essential_cookie( $name ),
					'first_seen' => current_time( 'c' ),
				);
				$added[] = $name;
			}
		}
		$revision_bumped = false;
		if ( ! empty( $added ) ) {
			update_option( Settings::COOKIES_OPTION, $current );
			$rev = (int) get_option( Settings::REVISION_OPTION, 1 );
			update_option( Settings::REVISION_OPTION, $rev + 1 );
			$revision_bumped = true;
		}
		return array( 'added' => $added, 'revision_bumped' => $revision_bumped );
	}

	/**
	 * Manually add a cookie to the detected list (e.g. when auto-detection missed it).
	 * If the cookie already exists, only the optional script URL and description are applied; revision is not bumped.
	 *
	 * @param string $name        Cookie name.
	 * @param bool   $essential   True for essential, false for non-essential.
	 * @param string $script_url  Optional. Script URL to block until user opts in (only used when non-essential).
	 * @param string $description Optional. Visitor-facing description of what the cookie does.
	 */
	public function add_cookie_manually( string $name, bool $essential, string $script_url = '', string $description = '' ): void {
		$name = trim( $name );
		if ( $name === '' ) {
			return;
		}
		$current = $this->get_detected_cookies();
		$is_new  = ! isset( $current[ $name ] );

		if ( $is_new ) {
			$current[ $name ] = array(
				'essential'   => $essential,
				'first_seen'  => current_time( 'c' ),
				'description' => trim( $description ),
			);
			update_option( Settings::COOKIES_OPTION, $current );
			$rev = (int) get_option( Settings::REVISION_OPTION, 1 );
			update_option( Settings::REVISION_OPTION, $rev + 1 );
		} else {
			$desc = trim( $description );
			if ( $desc !== '' ) {
				$current[ $name ]['description'] = $desc;
				update_option( Settings::COOKIES_OPTION, $current );
			}
		}

		$script_url = trim( $script_url );
		if ( $script_url !== '' && ! $essential ) {
			$this->add_script_cookie_mappings( array( $name => $script_url ) );
		}
	}

	/**
	 * Get the visitor-facing description for a cookie (stored custom or default).
	 *
	 * @param string $name      Cookie name.
	 * @param bool   $essential True if essential category.
	 * @return string
	 */
	public function get_cookie_description( string $name, bool $essential ): string {
		$descriptions = get_option( Settings::COOKIE_DESCRIPTIONS_OPTION, array() );
		if ( is_array( $descriptions ) && isset( $descriptions[ $name ] ) && trim( (string) $descriptions[ $name ] ) !== '' ) {
			return trim( (string) $descriptions[ $name ] );
		}
		$current = $this->get_detected_cookies();
		$stored  = isset( $current[ $name ] ) && is_array( $current[ $name ] ) && isset( $current[ $name ]['description'] )
			? trim( (string) $current[ $name ]['description'] )
			: '';
		if ( $stored !== '' ) {
			return $stored;
		}
		return $essential
			? __( 'Required for site functionality (e.g. login, preferences).', 'oven-cookie-consent' )
			: __( 'Optional; used for analytics or third-party features.', 'oven-cookie-consent' );
	}

	/**
	 * Build cookie list for CookieConsent: necessary (readOnly) and nonessential categories with cookie tables.
	 *
	 * @return array{categories: array, essential_table: array, nonessential_table: array}
	 */
	public function get_consent_config(): array {
		$essential    = $this->get_essential_cookie_names();
		$nonessential = $this->get_nonessential_cookie_names();

		$essential_table = array(
			'headers' => array(
				'name'        => __( 'Name', 'oven-cookie-consent' ),
				'description' => __( 'Description', 'oven-cookie-consent' ),
			),
			'body'    => array(),
		);
		foreach ( $essential as $name ) {
			$essential_table['body'][] = array(
				'name'        => $name,
				'description' => $this->get_cookie_description( $name, true ),
			);
		}

		$nonessential_table = array(
			'headers' => array(
				'name'        => __( 'Name', 'oven-cookie-consent' ),
				'description' => __( 'Description', 'oven-cookie-consent' ),
			),
			'body'    => array(),
		);
		foreach ( $nonessential as $name ) {
			$nonessential_table['body'][] = array(
				'name'        => $name,
				'description' => $this->get_cookie_description( $name, false ),
			);
		}

		$categories = array(
			'necessary' => array(
				'readOnly'   => true,
				'enabled'   => true,
				'services'  => array(),
			),
			'nonessential' => array(
				'readOnly'   => false,
				'enabled'   => false,
				'autoClear'  => array(
					'cookies' => array_map( fn( $n ) => array( 'name' => $n ), $nonessential ),
				),
				'services'  => array(),
			),
		);

		// One "service" per non-essential cookie for toggles, or group as single category.
		foreach ( $nonessential as $cookie_name ) {
			$safe_key = sanitize_key( $cookie_name );
			$categories['nonessential']['services'][ $safe_key ] = array(
				'label'  => $cookie_name,
				'cookies' => array( array( 'name' => $cookie_name ) ),
			);
		}

		return array(
			'categories'        => $categories,
			'essential_table'   => $essential_table,
			'nonessential_table' => $nonessential_table,
		);
	}

	/**
	 * Get consent data for the current visitor (from cookie if logged out, user meta if logged in).
	 *
	 * @return array{categories: string[], services: array<string, string[]>}|null Parsed consent or null if none/invalid.
	 */
	public function get_current_consent(): ?array {
		if ( is_user_logged_in() ) {
			$stored = get_user_meta( get_current_user_id(), Settings::USER_CONSENT_META, true );
			if ( ! is_array( $stored ) || (int) ( $stored['revision'] ?? 0 ) !== $this->settings->get_revision() ) {
				return null;
			}
			$categories = $stored['categories'] ?? array();
			$services   = $stored['services'] ?? array();
			return array(
				'categories' => is_array( $categories ) ? $categories : array(),
				'services'   => is_array( $services ) ? $services : array(),
			);
		}

		$cookie_name = 'oven_cc';
		if ( empty( $_COOKIE[ $cookie_name ] ) || ! is_string( $_COOKIE[ $cookie_name ] ) ) {
			return null;
		}
		return Consent_Sanitizer::parse_guest_consent_cookie(
			Consent_Sanitizer::get_cookie_value( $cookie_name ),
			$this->settings->get_revision()
		);
	}

	/**
	 * Get list of non-essential cookie names that should be cleared this request because the user has not accepted them.
	 * Used to run an early script that deletes these cookies on each page load.
	 *
	 * @return string[] Cookie names to clear (may be empty).
	 */
	public function get_cookies_to_clear(): array {
		$nonessential = $this->get_nonessential_cookie_names();
		if ( empty( $nonessential ) ) {
			return array();
		}

		$consent = $this->get_current_consent();
		// No valid consent: do not clear (let banner show; once they choose "essential only" we will clear).
		if ( $consent === null ) {
			return array();
		}

		$accepted_categories = $consent['categories'];
		$accepted_services  = $consent['services'];

		// They accepted only necessary (or rejected nonessential).
		if ( ! in_array( 'nonessential', $accepted_categories, true ) ) {
			return $nonessential;
		}

		// They accepted nonessential category; check which services (individual cookies) they accepted.
		$accepted_nonessential = isset( $accepted_services['nonessential'] ) && is_array( $accepted_services['nonessential'] )
			? $accepted_services['nonessential']
			: array();

		$to_clear = array();
		foreach ( $nonessential as $cookie_name ) {
			$service_key = sanitize_key( $cookie_name );
			if ( ! in_array( $service_key, $accepted_nonessential, true ) ) {
				$to_clear[] = $cookie_name;
			}
		}
		return $to_clear;
	}

	/**
	 * Normalize a script URL for consistent matching (origin + path, no query/fragment).
	 *
	 * @param string $url Script URL (absolute or relative).
	 * @param string $home_url Optional. Site home URL for resolving relative URLs.
	 * @return string Normalized URL or empty string.
	 */
	public static function normalize_script_url( string $url, string $home_url = '' ): string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}
		if ( substr( $url, 0, 2 ) === '//' ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		} elseif ( $url !== '' && $url[0] === '/' && $home_url !== '' ) {
			$parsed = wp_parse_url( $home_url );
			$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] . ':' : '';
			$host  = isset( $parsed['host'] ) ? $parsed['host'] : '';
			$url   = $scheme . '//' . $host . $url;
		}
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || ( empty( $parsed['host'] ) && empty( $parsed['path'] ) ) ) {
			return '';
		}
		$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : '';
		$host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
		$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		return $scheme . '//' . $host . $path;
	}

	/**
	 * Get the stored script-to-cookie map (script URL => list of non-essential cookie names).
	 *
	 * @return array<string, string[]> Map of normalized script URL to cookie names.
	 */
	public function get_script_cookie_map(): array {
		$raw = get_option( Settings::SCRIPT_COOKIE_MAP_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return $raw;
	}

	/**
	 * Add script-to-cookie mappings from detection (only for non-essential cookies).
	 *
	 * @param array<string, string> $mapping Map of cookie name => script URL (as reported by client).
	 */
	public function add_script_cookie_mappings( array $mapping ): void {
		$nonessential = array_flip( $this->get_nonessential_cookie_names() );
		$current     = $this->get_script_cookie_map();
		$home        = home_url( '/' );

		foreach ( $mapping as $cookie_name => $script_url ) {
			$cookie_name = is_string( $cookie_name ) ? trim( $cookie_name ) : '';
			$script_url  = is_string( $script_url ) ? trim( $script_url ) : '';
			if ( $cookie_name === '' || $script_url === '' ) {
				continue;
			}
			if ( ! isset( $nonessential[ $cookie_name ] ) ) {
				continue;
			}
			// Stack trace URLs may include :line:column; remove for normalization.
			$script_url = preg_replace( '/:\d+:\d+$/', '', $script_url );
			$normalized = self::normalize_script_url( $script_url, $home );
			if ( $normalized === '' ) {
				continue;
			}
			if ( ! isset( $current[ $normalized ] ) ) {
				$current[ $normalized ] = array();
			}
			if ( ! in_array( $cookie_name, $current[ $normalized ], true ) ) {
				$current[ $normalized ][] = $cookie_name;
			}
		}

		update_option( Settings::SCRIPT_COOKIE_MAP_OPTION, $current );
	}

	/**
	 * Get list of normalized script URLs that set non-essential cookies (for blocking until consent).
	 * Only includes scripts that have at least one cookie still classified as non-essential (respects overrides).
	 *
	 * @return string[]
	 */
	public function get_script_urls_to_block(): array {
		$map     = $this->get_script_cookie_map();
		$blocked = array();
		foreach ( $map as $script_url => $cookie_names ) {
			if ( ! is_array( $cookie_names ) ) {
				continue;
			}
			foreach ( $cookie_names as $cookie_name ) {
				if ( is_string( $cookie_name ) && ! $this->get_effective_essential( $cookie_name ) ) {
					$blocked[] = $script_url;
					break;
				}
			}
		}
		return $blocked;
	}
}
