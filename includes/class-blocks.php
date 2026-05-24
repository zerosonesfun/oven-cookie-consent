<?php
/**
 * Gutenberg block registration.
 *
 * @package Oven
 */

declare(strict_types=1);

namespace Oven;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks
 */
final class Blocks {

	/**
	 * Run on init.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Register blocks that use block.json.
	 */
	public static function register_blocks(): void {
		$block_dir = OVEN_PLUGIN_DIR . 'blocks/cookie-settings';
		if ( ! is_file( $block_dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $block_dir );
	}
}
