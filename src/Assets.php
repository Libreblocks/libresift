<?php
/**
 * Front-end script and styles, loaded only on pages that show a filter.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public const HANDLE = 'libresift';

	public static function register(): void {
		wp_register_script( self::HANDLE, LIBRESIFT_URL . 'assets/front/libresift.js', [], LIBRESIFT_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
		wp_register_style( self::HANDLE, LIBRESIFT_URL . 'assets/front/libresift.css', [], LIBRESIFT_VERSION );

		wp_localize_script( self::HANDLE, 'libresiftData', [
			'endpoint' => esc_url_raw( rest_url( 'libreblocks/v1/sift/render' ) ),
			'prefix'   => State::PREFIX,
			'i18n'     => [
				'loading' => __( 'Loading…', 'libresift' ),
				'error'   => __( 'Could not update the results. Reload the page to try again.', 'libresift' ),
			],
		] );
	}

	public static function enqueue(): void {
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			self::register();
		}

		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
	}
}
