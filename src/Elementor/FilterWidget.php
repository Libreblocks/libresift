<?php
/**
 * Elementor widget: LibreSift filter.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift\Elementor;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use LibreBlocks\Sift\Plugin;

defined( 'ABSPATH' ) || exit;

final class FilterWidget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'libresift-filter';
	}

	public function get_title(): string {
		return __( 'Filter', 'libresift' );
	}

	public function get_icon(): string {
		return 'eicon-filter';
	}

	public function get_categories(): array {
		return [ 'libreforge' ];
	}

	public function get_keywords(): array {
		return [ 'filter', 'search', 'facet', 'libresift' ];
	}

	/**
	 * @return array<string, string>
	 */
	private function filter_options(): array {
		$options = [ '' => __( '— Choose —', 'libresift' ) ];

		foreach ( Plugin::instance()->forge()->repository()->active( 'filter' ) as $filter ) {
			$options[ (string) $filter->id ] = $filter->title();
		}

		return $options;
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'content_section', [ 'label' => __( 'Filter', 'libresift' ) ] );

		$this->add_control( 'filter_id', [
			'label'       => __( 'Filter', 'libresift' ),
			'type'        => Controls_Manager::SELECT,
			'options'     => $this->filter_options(),
			'default'     => '',
			'description' => __( 'Create filters in LibreSift → Filters.', 'libresift' ),
		] );

		$this->add_control( 'show_label', [
			'label'   => __( 'Show the title', 'libresift' ),
			'type'    => Controls_Manager::SWITCHER,
			'default' => 'yes',
		] );

		$this->add_control( 'apply', [
			'label'   => __( 'When to update the results', 'libresift' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'instant',
			'options' => [
				'instant' => __( 'Right away', 'libresift' ),
				'button'  => __( 'When clicking a button', 'libresift' ),
			],
		] );

		$this->add_control( 'button_text', [
			'label'     => __( 'Button text', 'libresift' ),
			'type'      => Controls_Manager::TEXT,
			'placeholder' => __( 'Filter', 'libresift' ),
			'condition' => [ 'apply' => 'button' ],
		] );

		$this->end_controls_section();

		$this->start_controls_section( 'style_section', [ 'label' => __( 'Filter', 'libresift' ), 'tab' => Controls_Manager::TAB_STYLE ] );

		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typography', 'label' => __( 'Title', 'libresift' ), 'selector' => '{{WRAPPER}} .ls-filter__label' ] );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'options_typography', 'label' => __( 'Options', 'libresift' ), 'selector' => '{{WRAPPER}} .ls-filter__control' ] );

		$this->add_control( 'accent', [
			'label'     => __( 'Accent color', 'libresift' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .ls-filter' => '--ls-accent: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$s = $this->get_settings_for_display();

		echo Plugin::instance()->view()->filter( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the View.
			'filter_id'   => (int) ( $s['filter_id'] ?? 0 ),
			'show_label'  => 'yes' === ( $s['show_label'] ?? '' ),
			'apply'       => (string) ( $s['apply'] ?? 'instant' ),
			'button_text' => (string) ( $s['button_text'] ?? '' ),
		] );
	}
}
