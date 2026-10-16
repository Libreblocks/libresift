<?php
/**
 * Elementor widget: Clear filters.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift\Elementor;

use Elementor\Controls_Manager;
use LibreBlocks\Sift\Plugin;

defined( 'ABSPATH' ) || exit;

final class ResetWidget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'libresift-reset';
	}

	public function get_title(): string {
		return __( 'Clear filters', 'libresift' );
	}

	public function get_icon(): string {
		return 'eicon-close-circle';
	}

	public function get_categories(): array {
		return [ 'libreforge' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Clear filters', 'libresift' ) ] );

		$this->add_control( 'text', [
			'label'       => __( 'Text', 'libresift' ),
			'type'        => Controls_Manager::TEXT,
			'placeholder' => __( 'Clear filters', 'libresift' ),
		] );

		$this->add_control( 'color', [
			'label'     => __( 'Color', 'libresift' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ls-reset' => 'color: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		echo Plugin::instance()->view()->reset( [ 'text' => (string) ( $this->get_settings_for_display()['text'] ?? '' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the View.
	}
}
