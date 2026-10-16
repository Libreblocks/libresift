<?php
/**
 * Plugin Name:       LibreSift
 * Plugin URI:        https://libreblocks.com/libresift
 * Description:       Filters for LibreForge listings: categories, custom fields, price ranges, search and sorting, with instant (AJAX) results.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Requires Plugins:  libreblocks-core, libreforge
 * Author:            LibreBlocks
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       libresift
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIBRESIFT_VERSION', '0.1.0' );
define( 'LIBRESIFT_FILE', __FILE__ );
define( 'LIBRESIFT_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIBRESIFT_URL', plugin_dir_url( __FILE__ ) );
define( 'LIBRESIFT_REQUIRES_CORE', '0.3.0' );
define( 'LIBRESIFT_REQUIRES_FORGE', '0.7.0' );

/**
 * Boots once both LibreBlocks Core and LibreForge are ready.
 *
 * @param LibreBlocks\Forge\Plugin $forge
 */
function libresift_boot( $forge ) {
	$core = libreblocks();

	if ( ! defined( 'LIBREFORGE_VERSION' ) || version_compare( LIBREFORGE_VERSION, LIBRESIFT_REQUIRES_FORGE, '<' ) ) {
		add_action( 'admin_notices', function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				/* translators: %s: required LibreForge version */
				esc_html( sprintf( __( 'LibreSift needs LibreForge %s or newer. Please update LibreForge.', 'libresift' ), LIBRESIFT_REQUIRES_FORGE ) )
			);
		} );
		return;
	}

	$compatible = $core->products()->register( 'libresift', array(
		'name'          => 'LibreSift',
		'version'       => LIBRESIFT_VERSION,
		'file'          => LIBRESIFT_FILE,
		'requires_core' => LIBRESIFT_REQUIRES_CORE,
		'menu'          => 'libresift',
	) );

	if ( ! $compatible ) {
		return;
	}

	LibreBlocks\Core\Autoloader::register( 'LibreBlocks\\Sift\\', LIBRESIFT_DIR . 'src/' );
	LibreBlocks\Sift\Plugin::instance()->boot( $core, $forge );
}

if ( did_action( 'libreforge/loaded' ) && class_exists( 'LibreBlocks\\Forge\\Plugin' ) ) {
	libresift_boot( LibreBlocks\Forge\Plugin::instance() );
} else {
	add_action( 'libreforge/loaded', 'libresift_boot' );
}

add_action( 'admin_notices', function () {
	if ( ! did_action( 'libreforge/loaded' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'LibreSift needs the LibreBlocks Core and LibreForge plugins. Please install and activate them.', 'libresift' )
		);
	}
} );
