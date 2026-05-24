<?php
/**
 * Cookie Settings block render.
 *
 * @package Oven
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// When "Hide from guests" is on, the consent script is not loaded for guests; hide this block so the button is not shown.
if ( function_exists( 'oven' ) && oven()->get_settings()->is_hide_from_guests() && ! is_user_logged_in() ) {
	return '';
}

$oven_text               = isset( $attributes['text'] ) && is_string( $attributes['text'] ) ? $attributes['text'] : __( 'Cookie Settings', 'oven-cookie-consent' );
$oven_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wp-block-oven-cookie-settings' ) );
?>
<div <?php echo $oven_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped output. ?>>
	<button type="button" class="cookie-settings wp-block-button__link" data-cc="show-preferencesModal" aria-label="<?php echo esc_attr( $oven_text ); ?>">
		<?php echo esc_html( $oven_text ); ?>
	</button>
</div>
