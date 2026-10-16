<?php
/**
 * Gutenberg blocks: Filter and Clear filters.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public function __construct( private View $view ) {}

	public function hook(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {
		$asset_file = LIBRESIFT_DIR . 'build/blocks.asset.php';

		if ( ! is_file( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script( 'libresift-blocks', LIBRESIFT_URL . 'build/blocks.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'libresift-blocks', 'libresift', LIBRESIFT_DIR . 'languages' );
		Assets::register();

		register_block_type( LIBRESIFT_DIR . 'blocks/filter', [
			'title'           => __( 'Filter', 'libresift' ),
			'description'     => __( 'Lets visitors filter a LibreForge listing.', 'libresift' ),
			'render_callback' => fn( $a ) => $this->wrap( $this->view->filter( [
				'filter_id'   => (int) ( $a['filterId'] ?? 0 ),
				'show_label'  => ! empty( $a['showLabel'] ),
				'apply'       => (string) ( $a['apply'] ?? 'instant' ),
				'button_text' => (string) ( $a['buttonText'] ?? '' ),
			] ) ),
		] );

		register_block_type( LIBRESIFT_DIR . 'blocks/reset', [
			'title'           => __( 'Clear filters', 'libresift' ),
			'description'     => __( 'A link that removes every active filter.', 'libresift' ),
			'render_callback' => fn( $a ) => $this->wrap( $this->view->reset( [ 'text' => (string) ( $a['text'] ?? '' ) ] ) ),
		] );
	}

	private function wrap( string $html ): string {
		return '' === $html ? '' : sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}
}
