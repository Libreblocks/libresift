<?php
/**
 * LibreSift entry point.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Core\Admin\Icons;
use LibreBlocks\Core\Plugin as Core;
use LibreBlocks\Forge\Plugin as Forge;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private ?Core $core = null;

	private ?Forge $forge = null;

	private State $state;

	private QueryFilter $filters;

	private View $view;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot( Core $core, Forge $forge ): void {
		if ( $this->core ) {
			return;
		}

		$this->core  = $core;
		$this->forge = $forge;

		$forge->register_kind( new FilterKind() );

		$this->state   = new State();
		$this->filters = new QueryFilter( $forge->repository(), $this->state );
		$this->view    = new View( $this->filters, $this->state, new Options() );

		$this->filters->hook();
		$core->rest()->add( RenderController::class );

		( new Blocks( $this->view ) )->hook();
		add_shortcode( 'libresift_filter', fn( $atts ) => $this->view->filter( [
			'filter_id'   => absint( ( (array) $atts )['id'] ?? 0 ),
			'apply'       => sanitize_key( ( (array) $atts )['apply'] ?? 'instant' ),
			'show_label'  => ! in_array( ( (array) $atts )['label'] ?? 'yes', [ 'no', '0', 'false' ], true ),
			'button_text' => sanitize_text_field( ( (array) $atts )['button'] ?? '' ),
		] ) );
		add_shortcode( 'libresift_reset', fn( $atts ) => $this->view->reset( [ 'text' => sanitize_text_field( ( (array) $atts )['text'] ?? '' ) ] ) );

		add_action( 'elementor/widgets/register', [ $this, 'elementor_widgets' ] );
		add_action( 'init', [ $this, 'admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
		add_action( 'init', fn() => load_plugin_textdomain( 'libresift', false, dirname( plugin_basename( LIBRESIFT_FILE ) ) . '/languages' ), 0 );

		do_action( 'libresift/loaded', $this );
	}

	public function state(): State {
		return $this->state;
	}

	public function forge(): Forge {
		return $this->forge;
	}

	public function view(): View {
		return $this->view;
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public function elementor_widgets( $manager ): void {
		$manager->register( new Elementor\FilterWidget() );
		$manager->register( new Elementor\ResetWidget() );
	}

	public function admin_menu(): void {
		if ( ! is_admin() ) {
			return;
		}

		$this->core->get( 'admin' )->add_menu( [
			'slug'  => 'libresift',
			'title' => 'LibreSift',
			'icon'  => Icons::data_uri( 'libresift' ),
			'order' => 20,
			'pages' => [
				[
					'slug'   => 'libresift',
					'title'  => __( 'Filters', 'libresift' ),
					'render' => function () {
						echo '<div id="libresift-app"></div>';
					},
				],
			],
		] );
	}

	public function admin_assets( string $hook_suffix ): void {
		$admin      = $this->core->get( 'admin' );
		$asset_file = LIBRESIFT_DIR . 'build/admin.asset.php';

		if ( $admin->page_hook( 'libresift' ) !== $hook_suffix || ! is_file( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script( 'libresift-admin', LIBRESIFT_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'libresift-admin', 'libresift', LIBRESIFT_DIR . 'languages' );
		$admin->share_ui_translations( 'libresift-admin' );
		wp_add_inline_script( 'libresift-admin', 'window.libresiftAdmin = ' . wp_json_encode( [
			'icon' => Icons::data_uri( 'libresift' ),
		] ) . ';', 'before' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'dashicons' );

		if ( is_file( LIBRESIFT_DIR . 'build/admin.css' ) ) {
			wp_enqueue_style( 'libresift-admin', LIBRESIFT_URL . 'build/admin.css', [ 'wp-components' ], $asset['version'] );
		}
	}

	private function __construct() {}
}
