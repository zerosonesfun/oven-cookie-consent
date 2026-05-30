<?php
/**
 * Sanitize consent and detection payloads after JSON decode.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Consent_Sanitizer
 */
final class Consent_Sanitizer {

	/** @var string[] */
	private const ALLOWED_CATEGORIES = array( 'necessary', 'nonessential' );

	/**
	 * Read a request cookie value for JSON decoding (sanitized after decode).
	 *
	 * @param string $name Cookie name.
	 * @return string Sanitized cookie value or empty string.
	 */
	public static function get_cookie_value( string $name ): string {
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return '';
		}
		return sanitize_textarea_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	/**
	 * Sanitize a raw JSON string (caller must verify nonce before reading $_POST).
	 *
	 * @param string $raw Unslashed POST value.
	 * @return string Sanitized JSON string.
	 */
	public static function sanitize_json_string( string $raw ): string {
		return sanitize_textarea_field( $raw );
	}

	/**
	 * Decode a JSON string to an associative array.
	 *
	 * @param string $json Raw JSON.
	 * @return array<string, mixed>|null
	 */
	public static function json_decode_assoc( string $json ): ?array {
		if ( $json === '' ) {
			return null;
		}
		$decoded = json_decode( $json, true, 512 );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		return $decoded;
	}

	/**
	 * Sanitize script-mapping data (cookie name => script URL).
	 *
	 * @param array<mixed, mixed> $decoded Decoded JSON object.
	 * @param int                 $max_items Maximum entries.
	 * @return array<string, string>
	 */
	public static function sanitize_script_mapping_array( array $decoded, int $max_items = 200 ): array {
		$out = array();
		foreach ( $decoded as $cookie => $url ) {
			if ( count( $out ) >= $max_items ) {
				break;
			}
			if ( ! is_string( $cookie ) || ! is_string( $url ) ) {
				continue;
			}
			$cookie = sanitize_text_field( $cookie );
			$url    = esc_url_raw( $url );
			if ( $cookie !== '' && $url !== '' ) {
				$out[ $cookie ] = $url;
			}
		}
		return $out;
	}

	/**
	 * Decode and sanitize script-mapping JSON from POST.
	 *
	 * @param string $json Raw JSON string.
	 * @param int    $max_items Maximum entries.
	 * @return array<string, string>
	 */
	public static function sanitize_script_mapping_json( string $json, int $max_items = 200 ): array {
		$decoded = self::json_decode_assoc( $json );
		if ( $decoded === null ) {
			return array();
		}
		return self::sanitize_script_mapping_array( $decoded, $max_items );
	}

	/**
	 * Sanitize a decoded consent payload for storage in user meta.
	 *
	 * @param array<string, mixed> $payload Decoded consent object.
	 * @return array<string, mixed>
	 */
	public static function sanitize_stored_consent_payload( array $payload ): array {
		$categories = array();
		if ( isset( $payload['categories'] ) && is_array( $payload['categories'] ) ) {
			foreach ( $payload['categories'] as $category ) {
				if ( is_string( $category ) && in_array( $category, self::ALLOWED_CATEGORIES, true ) ) {
					$categories[] = $category;
				}
			}
		}
		$categories = array_values( array_unique( $categories ) );

		$services_raw = isset( $payload['services'] ) && is_array( $payload['services'] ) ? $payload['services'] : array();

		return array(
			'categories'           => $categories,
			'revision'             => isset( $payload['revision'] ) ? (int) $payload['revision'] : 0,
			'consentTimestamp'     => isset( $payload['consentTimestamp'] ) ? sanitize_text_field( (string) $payload['consentTimestamp'] ) : '',
			'consentId'            => isset( $payload['consentId'] ) ? sanitize_text_field( (string) $payload['consentId'] ) : '',
			'services'             => self::sanitize_services( $services_raw ),
			'languageCode'         => isset( $payload['languageCode'] ) ? sanitize_text_field( (string) $payload['languageCode'] ) : '',
			'expirationTime'       => isset( $payload['expirationTime'] ) ? max( 0, (int) $payload['expirationTime'] ) : 0,
			'lastConsentTimestamp' => isset( $payload['lastConsentTimestamp'] ) ? sanitize_text_field( (string) $payload['lastConsentTimestamp'] ) : '',
		);
	}

	/**
	 * Parse and sanitize guest consent cookie JSON for runtime use.
	 *
	 * @param string $json              Raw cookie value.
	 * @param int    $expected_revision Current policy revision.
	 * @return array{categories: string[], services: array<string, string[]>}|null
	 */
	public static function parse_guest_consent_cookie( string $json, int $expected_revision ): ?array {
		$decoded = self::json_decode_assoc( $json );
		if ( $decoded === null ) {
			return null;
		}
		$sanitized = self::sanitize_stored_consent_payload( $decoded );
		if ( (int) $sanitized['revision'] !== $expected_revision ) {
			return null;
		}
		$services = isset( $sanitized['services'] ) && is_array( $sanitized['services'] ) ? $sanitized['services'] : array();
		return array(
			'categories' => $sanitized['categories'],
			'services'   => $services,
		);
	}

	/**
	 * @param array<string, mixed> $services_raw Services from decoded consent.
	 * @return array<string, string[]>
	 */
	private static function sanitize_services( array $services_raw ): array {
		$out = array();
		foreach ( self::ALLOWED_CATEGORIES as $cat ) {
			if ( ! isset( $services_raw[ $cat ] ) || ! is_array( $services_raw[ $cat ] ) ) {
				continue;
			}
			$list = array();
			foreach ( $services_raw[ $cat ] as $service ) {
				if ( ! is_string( $service ) || $service === '' ) {
					continue;
				}
				$list[] = sanitize_text_field( $service );
			}
			if ( ! empty( $list ) ) {
				$out[ $cat ] = array_values( array_unique( $list ) );
			}
		}
		return $out;
	}
}
