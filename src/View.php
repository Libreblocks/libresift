<?php
/**
 * HTML of a filter. It is a real GET form, so it works without JavaScript
 * (the page reloads with ?f_slug=...); the front-end script turns it into
 * instant AJAX filtering.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Forge\Model\Definition;

defined( 'ABSPATH' ) || exit;

final class View {

	public function __construct( private QueryFilter $filters, private State $state, private Options $options ) {}

	/**
	 * @param array<string, mixed> $s filter_id, show_label, apply (instant|button), button_text, class.
	 */
	public function filter( array $s ): string {
		$filter = $this->filters->find( (int) ( $s['filter_id'] ?? 0 ) );

		if ( ! $filter ) {
			return current_user_can( 'edit_posts' ) ? '<div class="ls-notice">' . esc_html__( 'Choose a filter (LibreSift → Filters).', 'libresift' ) . '</div>' : '';
		}

		Assets::enqueue();

		$apply   = 'button' === ( $s['apply'] ?? 'instant' ) ? 'button' : 'instant';
		$label   = (string) $filter->get( 'label' ) ?: $filter->title();
		$name    = State::PREFIX . $filter->slug;
		$control = (string) $filter->get( 'control' );
		$id      = 'ls-' . $filter->slug . '-' . wp_unique_id();

		$html  = sprintf(
			'<form class="%1$s" method="get" action="" data-libresift-filter="%2$s" data-control="%3$s" data-apply="%4$s" data-target="%5$s">',
			esc_attr( trim( 'ls-filter ls-filter--' . $control . ' ' . ( $s['class'] ?? '' ) ) ),
			esc_attr( $filter->slug ),
			esc_attr( $control ),
			esc_attr( $apply ),
			esc_attr( (string) $filter->get( 'target', '' ) )
		);

		if ( ! empty( $s['show_label'] ) || ! isset( $s['show_label'] ) ) {
			$html .= sprintf( '<%1$s class="ls-filter__label"%2$s>%3$s</%1$s>', in_array( $control, [ 'checkboxes', 'radio', 'range' ], true ) ? 'div' : 'label', in_array( $control, [ 'checkboxes', 'radio', 'range' ], true ) ? '' : ' for="' . esc_attr( $id ) . '"', esc_html( $label ) );
		}

		$html .= '<div class="ls-filter__control">' . $this->control( $filter, $control, $name, $id ) . '</div>';

		// Without JavaScript the form only sends its own fields: keep the other active filters.
		foreach ( $this->state->all() as $key => $value ) {
			if ( $key !== $name ) {
				$html .= sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		$button = (string) ( $s['button_text'] ?? '' ) ?: __( 'Filter', 'libresift' );
		$html  .= 'button' === $apply
			? '<button type="submit" class="ls-filter__apply wp-element-button">' . esc_html( $button ) . '</button>'
			: '<noscript><button type="submit" class="ls-filter__apply">' . esc_html( $button ) . '</button></noscript>';

		return $html . '</form>';
	}

	/**
	 * @param array<string, mixed> $s text, class.
	 */
	public function reset( array $s ): string {
		Assets::enqueue();

		$url = remove_query_arg( array_keys( $this->state->all() ) );
		$url = remove_query_arg( array_filter( array_keys( $_GET ), fn( $key ) => 0 === strpos( (string) $key, 'lfp_' ) ), $url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return sprintf(
			'<a class="%1$s" href="%2$s" data-libresift-reset%3$s>%4$s</a>',
			esc_attr( trim( 'ls-reset ' . ( $s['class'] ?? '' ) ) ),
			esc_url( $url ),
			$this->state->has_any() ? '' : ' hidden',
			esc_html( (string) ( $s['text'] ?? '' ) ?: __( 'Clear filters', 'libresift' ) )
		);
	}

	private function control( Definition $filter, string $control, string $name, string $id ): string {
		switch ( $control ) {
			case 'search':
			case 'text':
				return sprintf(
					'<input type="search" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="ls-filter__input" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $this->state->text( $filter->slug ) ),
					esc_attr( (string) $filter->get( 'placeholder' ) ?: __( 'Search…', 'libresift' ) )
				);

			case 'range':
				return $this->range( $filter, $name, $id );

			case 'select':
				$current = $this->state->values( $filter->slug )[0] ?? '';
				$html    = sprintf( '<select id="%1$s" name="%2$s" class="ls-filter__select">', esc_attr( $id ), esc_attr( $name ) );
				$html   .= '<option value="">' . esc_html( (string) $filter->get( 'placeholder' ) ?: ( 'sort' === $filter->get( 'source' ) ? __( 'Default order', 'libresift' ) : __( 'All', 'libresift' ) ) ) . '</option>';

				foreach ( $this->options->for_filter( $filter ) as $option ) {
					$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option['value'] ), selected( $current, $option['value'], false ), esc_html( $option['label'] ) );
				}

				return $html . '</select>';

			case 'radio':
				$current = $this->state->values( $filter->slug )[0] ?? '';
				$html    = '<div class="ls-filter__options" role="radiogroup">';
				$html   .= sprintf( '<label class="ls-filter__option"><input type="radio" name="%1$s" value=""%2$s /> <span>%3$s</span></label>', esc_attr( $name ), checked( $current, '', false ), esc_html( (string) $filter->get( 'all_label' ) ?: __( 'All', 'libresift' ) ) );

				foreach ( $this->options->for_filter( $filter ) as $option ) {
					$html .= sprintf( '<label class="ls-filter__option"><input type="radio" name="%1$s" value="%2$s"%3$s /> <span>%4$s</span></label>', esc_attr( $name ), esc_attr( $option['value'] ), checked( $current, $option['value'], false ), esc_html( $option['label'] ) );
				}

				return $html . '</div>';

			default: // checkboxes
				$current = $this->state->values( $filter->slug );
				$html    = '<div class="ls-filter__options">';

				foreach ( $this->options->for_filter( $filter ) as $option ) {
					$html .= sprintf(
						'<label class="ls-filter__option"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> <span>%4$s</span></label>',
						esc_attr( $name ),
						esc_attr( $option['value'] ),
						in_array( $option['value'], $current, true ) ? ' checked' : '',
						esc_html( $option['label'] )
					);
				}

				return $html . '</div>';
		}
	}

	private function range( Definition $filter, string $name, string $id ): string {
		$min  = $filter->get( 'min' );
		$max  = $filter->get( 'max' );
		$step = (float) $filter->get( 'step', 1 );

		if ( null === $min || null === $max ) {
			[ $low, $high ] = $this->options->bounds( (string) $filter->get( 'meta_key' ) );
			$min            = $min ?? floor( $low );
			$max            = $max ?? ceil( $high );
		}

		[ $from, $to ] = $this->state->range( $filter->slug );
		$prefix        = (string) $filter->get( 'prefix' );
		$suffix        = (string) $filter->get( 'suffix' );
		$number        = fn( $value ) => rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );

		return sprintf(
			'<div class="ls-range" data-min="%1$s" data-max="%2$s">'
				. '<label class="ls-range__field"><span>%3$s</span>%5$s<input type="number" id="%6$s" name="%7$s[min]" value="%8$s" min="%1$s" max="%2$s" step="%9$s" placeholder="%1$s" />%10$s</label>'
				. '<label class="ls-range__field"><span>%4$s</span>%5$s<input type="number" name="%7$s[max]" value="%11$s" min="%1$s" max="%2$s" step="%9$s" placeholder="%2$s" />%10$s</label>'
				. '</div>',
			esc_attr( $number( $min ) ),
			esc_attr( $number( $max ) ),
			esc_html__( 'From', 'libresift' ),
			esc_html__( 'To', 'libresift' ),
			'' === $prefix ? '' : '<em class="ls-range__affix">' . esc_html( $prefix ) . '</em>',
			esc_attr( $id ),
			esc_attr( $name ),
			null === $from ? '' : esc_attr( $number( $from ) ),
			esc_attr( $number( $step ) ),
			'' === $suffix ? '' : '<em class="ls-range__affix">' . esc_html( $suffix ) . '</em>',
			null === $to ? '' : esc_attr( $number( $to ) )
		);
	}
}
