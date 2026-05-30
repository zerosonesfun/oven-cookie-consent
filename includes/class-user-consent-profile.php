<?php
/**
 * Renders cookie consent history on user profile (for admins/editors).
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class User_Consent_Profile
 */
class User_Consent_Profile {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'show_user_profile', array( $this, 'render_consent_section' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'render_consent_section' ), 10, 1 );
	}

	/**
	 * Output consent history section on profile (only for users who can edit this user).
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public function render_consent_section( \WP_User $user ): void {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$current = get_user_meta( $user->ID, Settings::USER_CONSENT_META, true );
		$history = get_user_meta( $user->ID, Settings::USER_CONSENT_HISTORY_META, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$has_current = is_array( $current ) && ! empty( $current );
		$has_history = ! empty( $history );

		if ( ! $has_current && ! $has_history ) {
			echo '<h3>' . esc_html__( 'Cookie consent', 'oven-cookie-consent' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'No consent recorded for this user. Consent is only stored when the user is logged in and accepts or changes cookie preferences on the site.', 'oven-cookie-consent' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Cookie consent', 'oven-cookie-consent' ) . '</h3>';

		if ( $has_current ) {
			echo '<p><strong>' . esc_html__( 'Current consent', 'oven-cookie-consent' ) . '</strong></p>';
			echo wp_kses_post( $this->format_consent_summary( $current ) );
		}

		if ( $has_history ) {
			echo '<p style="margin-top: 1em;"><strong>' . esc_html__( 'Consent history', 'oven-cookie-consent' ) . '</strong></p>';
			echo '<table class="widefat striped" style="max-width: 720px;" aria-label="' . esc_attr__( 'Cookie consent history', 'oven-cookie-consent' ) . '">';
			echo '<thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Date', 'oven-cookie-consent' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Categories accepted', 'oven-cookie-consent' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Cookies / services accepted', 'oven-cookie-consent' ) . '</th>';
			echo '</tr></thead><tbody>';
			$rows = array_reverse( $history );
			foreach ( $rows as $entry ) {
				$date = isset( $entry['recorded_at'] ) ? $this->format_date( $entry['recorded_at'] ) : ( isset( $entry['consent_timestamp'] ) ? $this->format_consent_timestamp( $entry['consent_timestamp'] ) : '—' );
				$categories = isset( $entry['categories'] ) && is_array( $entry['categories'] ) ? $entry['categories'] : array();
				$services   = isset( $entry['services'] ) && is_array( $entry['services'] ) ? $entry['services'] : array();
				echo '<tr>';
				echo '<td>' . esc_html( $date ) . '</td>';
				echo '<td>' . esc_html( $this->format_categories( $categories ) ) . '</td>';
				echo '<td>' . esc_html( $this->format_services_for_display( $categories, $services ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Format stored consent as a short summary (current consent block).
	 *
	 * @param array<string, mixed> $data Stored consent (USER_CONSENT_META).
	 * @return string HTML.
	 */
	private function format_consent_summary( array $data ): string {
		$categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
		$services   = isset( $data['services'] ) && is_array( $data['services'] ) ? $data['services'] : array();
		$ts         = isset( $data['consentTimestamp'] ) ? $this->format_consent_timestamp( (string) $data['consentTimestamp'] ) : ( isset( $data['lastConsentTimestamp'] ) ? $this->format_consent_timestamp( (string) $data['lastConsentTimestamp'] ) : '' );
		$out        = '<table class="widefat striped" style="max-width: 720px;"><tbody>';
		$out       .= '<tr><th scope="row" style="width: 140px;">' . esc_html__( 'Last updated', 'oven-cookie-consent' ) . '</th><td>' . esc_html( $ts ?: '—' ) . '</td></tr>';
		$out       .= '<tr><th scope="row">' . esc_html__( 'Categories', 'oven-cookie-consent' ) . '</th><td>' . esc_html( $this->format_categories( $categories ) ) . '</td></tr>';
		$out       .= '<tr><th scope="row">' . esc_html__( 'Cookies / services', 'oven-cookie-consent' ) . '</th><td>' . esc_html( $this->format_services_for_display( $categories, $services ) ) . '</td></tr>';
		$out       .= '</tbody></table>';
		return $out;
	}

	/**
	 * Format ISO date for display.
	 *
	 * @param string $iso ISO 8601 date string.
	 * @return string
	 */
	private function format_date( string $iso ): string {
		if ( $iso === '' ) {
			return '—';
		}
		$t = strtotime( $iso );
		return $t ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) : esc_html( $iso );
	}

	/**
	 * Format consent library timestamp (ms or string) for display.
	 *
	 * @param string $ts Consent timestamp from payload.
	 * @return string
	 */
	private function format_consent_timestamp( string $ts ): string {
		if ( $ts === '' ) {
			return '—';
		}
		if ( is_numeric( $ts ) ) {
			$sec = (int) ( (float) $ts / 1000 );
			return $sec > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sec ) : '—';
		}
		return $this->format_date( $ts );
	}

	/**
	 * Human-readable categories list.
	 *
	 * @param string[] $categories Category keys.
	 * @return string
	 */
	private function format_categories( array $categories ): string {
		$labels = array(
			'necessary'   => __( 'Necessary', 'oven-cookie-consent' ),
			'nonessential' => __( 'Non-essential', 'oven-cookie-consent' ),
		);
		$out = array();
		foreach ( $categories as $c ) {
			$out[] = isset( $labels[ $c ] ) ? $labels[ $c ] : $c;
		}
		return implode( ', ', $out ) ?: '—';
	}

	/**
	 * Human-readable services/cookies list. Uses stored services when present; otherwise infers from accepted categories (e.g. "Accept all" often omits services).
	 *
	 * @param string[]                $categories Accepted category keys.
	 * @param array<string, string[]> $services   Map of category => service/cookie keys (may be empty).
	 * @return string
	 */
	private function format_services_for_display( array $categories, array $services ): string {
		$from_services = $this->format_services( $services );
		if ( $from_services !== '—' ) {
			return $from_services;
		}
		// Library often omits services when user clicks "Accept all"; infer from categories using current cookie list.
		if ( empty( $categories ) || ! function_exists( 'oven' ) ) {
			return '—';
		}
		$manager = \oven()->get_cookie_manager();
		$names   = array();
		if ( in_array( 'necessary', $categories, true ) ) {
			$names = array_merge( $names, $manager->get_essential_cookie_names() );
		}
		if ( in_array( 'nonessential', $categories, true ) ) {
			$names = array_merge( $names, $manager->get_nonessential_cookie_names() );
		}
		$names = array_unique( $names );
		return implode( ', ', $names ) ?: '—';
	}

	/**
	 * Human-readable services/cookies list (flatten category => list).
	 *
	 * @param array<string, string[]> $services Map of category => service keys.
	 * @return string
	 */
	private function format_services( array $services ): string {
		$all = array();
		foreach ( $services as $list ) {
			if ( is_array( $list ) ) {
				$all = array_merge( $all, $list );
			}
		}
		$all = array_unique( $all );
		return implode( ', ', $all ) ?: '—';
	}
}
