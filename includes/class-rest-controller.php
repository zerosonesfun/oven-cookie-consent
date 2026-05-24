<?php
/**
 * Oven REST and AJAX handlers.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Controller
 */
class Rest_Controller {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'oven/v1';

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
	 * @param Settings      $settings Settings.
	 * @param Cookie_Manager $cookie_manager Cookie manager.
	 */
	public function __construct( Settings $settings, Cookie_Manager $cookie_manager ) {
		$this->settings       = $settings;
		$this->cookie_manager = $cookie_manager;
	}

	/**
	 * Initialize REST and AJAX.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
		add_action( 'wp_ajax_oven_detect_cookies', array( $this, 'ajax_detect_cookies' ), 10 );
		add_action( 'wp_ajax_nopriv_oven_detect_cookies', array( $this, 'ajax_detect_cookies_nopriv' ), 10 );
		add_action( 'wp_ajax_oven_save_consent', array( $this, 'ajax_save_consent' ), 10 );
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/detect-cookies',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_detect_cookies' ),
				'permission_callback' => array( $this, 'can_detect_cookies' ),
				'args'                => array(
					'cookies'        => array(
						'required'          => true,
						'type'              => 'array',
						'items'             => array( 'type' => 'string' ),
						'sanitize_callback' => function ( $arr ) {
							return array_map( 'sanitize_text_field', array_filter( (array) $arr ) );
						},
					),
					'script_mapping' => array(
						'required'          => false,
						'type'              => 'object',
						'description'      => 'Cookie name => script URL that set it (from detection).',
						'sanitize_callback' => function ( $obj ) {
							if ( ! is_array( $obj ) || count( $obj ) > 200 ) {
								return array();
							}
							$out = array();
							foreach ( $obj as $k => $v ) {
								if ( is_string( $k ) && is_string( $v ) && count( $out ) < 200 ) {
									$out[ sanitize_text_field( $k ) ] = esc_url_raw( $v );
								}
							}
							return $out;
						},
					),
				),
			)
		);
	}

	/**
	 * Permission: only admins when detection mode is on.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_detect_cookies( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) && $this->settings->is_detection_mode();
	}

	/**
	 * REST: merge detected cookies.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_detect_cookies( \WP_REST_Request $request ): \WP_REST_Response {
		$cookie_names = $request->get_param( 'cookies' );
		if ( ! is_array( $cookie_names ) ) {
			return new \WP_REST_Response( array( 'success' => false, 'message' => 'Invalid cookies' ), 400 );
		}
		$result = $this->cookie_manager->merge_detected_cookies( $cookie_names );
		$script_mapping = $request->get_param( 'script_mapping' );
		if ( is_array( $script_mapping ) && ! empty( $script_mapping ) ) {
			$this->cookie_manager->add_script_cookie_mappings( $script_mapping );
		}
		return new \WP_REST_Response( array(
			'success'         => true,
			'added'           => $result['added'],
			'revision_bumped' => $result['revision_bumped'],
		), 200 );
	}

	/**
	 * AJAX detect cookies: no nonce for nopriv; only allow when logged-in admin.
	 */
	public function ajax_detect_cookies_nopriv(): void {
		wp_send_json_error( array( 'message' => __( 'Login required.', 'oven-cookie-consent' ) ), 403 );
	}

	/**
	 * AJAX detect cookies: accept cookie list, merge, return result.
	 */
	public function ajax_detect_cookies(): void {
		$nonce = isset( $_REQUEST['oven_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['oven_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oven_detect_cookies' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'oven-cookie-consent' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) || ! $this->settings->is_detection_mode() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'oven-cookie-consent' ) ), 403 );
		}
		$cookies = isset( $_POST['cookies'] ) && is_array( $_POST['cookies'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['cookies'] ) )
			: array();
		$cookies = array_filter( $cookies );
		$result  = $this->cookie_manager->merge_detected_cookies( $cookies );

		$script_mapping = array();
		$script_mapping_json = Consent_Sanitizer::get_post_json_string( 'script_mapping' );
		if ( $script_mapping_json !== '' ) {
			$script_mapping = Consent_Sanitizer::sanitize_script_mapping_json( $script_mapping_json );
		}
		if ( ! empty( $script_mapping ) ) {
			$this->cookie_manager->add_script_cookie_mappings( $script_mapping );
		}

		wp_send_json_success( array(
			'added'           => $result['added'],
			'revision_bumped' => $result['revision_bumped'],
		) );
	}

	/**
	 * AJAX save consent for logged-in user (store in user meta).
	 */
	public function ajax_save_consent(): void {
		$nonce = isset( $_REQUEST['oven_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['oven_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oven_save_consent' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'oven-cookie-consent' ) ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'oven-cookie-consent' ) ), 403 );
		}
		$consent_json = Consent_Sanitizer::get_post_json_string( 'consent' );
		if ( $consent_json === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid consent data.', 'oven-cookie-consent' ) ), 400 );
		}
		$decoded = Consent_Sanitizer::json_decode_assoc( $consent_json );
		if ( $decoded === null ) {
			wp_send_json_error( array( 'message' => __( 'Invalid consent data.', 'oven-cookie-consent' ) ), 400 );
		}
		$data             = Consent_Sanitizer::sanitize_stored_consent_payload( $decoded );
		$revision         = (int) $data['revision'];
		$current_revision = $this->settings->get_revision();
		$categories       = $data['categories'];
		$services         = $data['services'];
		$allowed_categories = array( 'necessary', 'nonessential' );
		foreach ( $allowed_categories as $cat ) {
			if ( isset( $services[ $cat ] ) && is_array( $services[ $cat ] ) && ! empty( $services[ $cat ] ) ) {
				continue;
			}
			if ( in_array( $cat, $categories, true ) ) {
				// Library often omits services when user clicks "Accept all"; infer from accepted categories.
				if ( $cat === 'necessary' ) {
					$services[ $cat ] = array_map( 'sanitize_text_field', $this->cookie_manager->get_essential_cookie_names() );
				} elseif ( $cat === 'nonessential' ) {
					$services[ $cat ] = array_map( 'sanitize_text_field', $this->cookie_manager->get_nonessential_cookie_names() );
				}
			}
		}
		$data['services'] = $services;
		$user_id = get_current_user_id();
		update_user_meta( $user_id, Settings::USER_CONSENT_META, $data );

		// Append to consent history for profile display (admins/editors can view).
		// Deduplicate: the library can fire onFirstConsent, onConsent, and onChange for one user action; only add one history entry per distinct consent (same consentId or same timestamp + categories).
		$history = get_user_meta( $user_id, Settings::USER_CONSENT_HISTORY_META, true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$consent_id = $data['consentId'];
		$consent_ts = $data['consentTimestamp'];
		$is_duplicate = false;
		if ( ! empty( $history ) ) {
			$last = end( $history );
			if ( $consent_id !== '' && isset( $last['consent_id'] ) && $last['consent_id'] === $consent_id ) {
				$is_duplicate = true;
			} elseif ( $consent_ts !== '' && isset( $last['consent_timestamp'] ) && $last['consent_timestamp'] === $consent_ts
				&& isset( $last['categories'] ) && $last['categories'] === $categories ) {
				$is_duplicate = true;
			}
		}
		if ( ! $is_duplicate ) {
			$history[] = array(
				'recorded_at'       => current_time( 'c' ),
				'consent_timestamp' => $consent_ts,
				'consent_id'        => $consent_id,
				'revision'          => $revision,
				'categories'        => $categories,
				'services'          => $services,
			);
		}
		$max_history = 50;
		if ( count( $history ) > $max_history ) {
			$history = array_slice( $history, -$max_history, null, true );
		}
		update_user_meta( $user_id, Settings::USER_CONSENT_HISTORY_META, $history );

		wp_send_json_success( array( 'revision' => $current_revision ) );
	}
}
