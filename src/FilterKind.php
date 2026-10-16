<?php
/**
 * Saved filters. Stored with the other LibreForge definitions (kind "filter").
 *
 * The slug is the name used in the address: a filter "cidade" reads ?f_cidade=sao-paulo.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Core\Fields\Field;
use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Forge\Model\Kind;

defined( 'ABSPATH' ) || exit;

final class FilterKind extends Kind {

	public const ID = 'filter';

	public const SOURCES = [ 'taxonomy', 'meta', 'search', 'sort' ];

	/** Which controls make sense for each source. */
	public const CONTROLS = [
		'taxonomy' => [ 'checkboxes', 'radio', 'select' ],
		'meta'     => [ 'checkboxes', 'radio', 'select', 'range', 'text' ],
		'search'   => [ 'search' ],
		'sort'     => [ 'select', 'radio' ],
	];

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Filters', 'libresift' );
	}

	public function suggest_slug( array $config ): string {
		return substr( sanitize_key( str_replace( '-', '_', sanitize_title( (string) ( $config['title'] ?? '' ) ) ) ), 0, 40 );
	}

	public function sanitize( array $input ): array {
		$source  = in_array( $input['source'] ?? '', self::SOURCES, true ) ? $input['source'] : 'taxonomy';
		$control = (string) ( $input['control'] ?? '' );

		if ( ! in_array( $control, self::CONTROLS[ $source ], true ) ) {
			$control = self::CONTROLS[ $source ][0];
		}

		return [
			'title'          => self::text( $input, 'title' ),
			'label'          => self::text( $input, 'label' ),
			'source'         => $source,
			'control'        => $control,
			'taxonomy'       => sanitize_key( self::text( $input, 'taxonomy' ) ),
			'meta_key'       => sanitize_key( self::text( $input, 'meta_key' ) ),
			'value_type'     => 'NUMERIC' === ( $input['value_type'] ?? '' ) ? 'NUMERIC' : 'CHAR',
			'options_source' => 'manual' === ( $input['options_source'] ?? '' ) ? 'manual' : 'auto',
			'options'        => array_map(
				fn( array $option ) => [ 'value' => sanitize_text_field( $option['value'] ), 'label' => sanitize_text_field( $option['label'] ) ],
				Field::normalize_options( $input['options'] ?? [] )
			),
			'hide_empty'     => self::flag( $input, 'hide_empty', true ),
			'all_label'      => self::text( $input, 'all_label' ),
			'placeholder'    => self::text( $input, 'placeholder' ),
			'min'            => is_numeric( $input['min'] ?? null ) ? (float) $input['min'] : null,
			'max'            => is_numeric( $input['max'] ?? null ) ? (float) $input['max'] : null,
			'step'           => is_numeric( $input['step'] ?? null ) && (float) $input['step'] > 0 ? (float) $input['step'] : 1.0,
			'prefix'         => self::text( $input, 'prefix' ),
			'suffix'         => self::text( $input, 'suffix' ),
			'sort_options'   => $this->sort_options( $input['sort_options'] ?? [] ),
			'target'         => sanitize_key( self::text( $input, 'target' ) ),
		];
	}

	public function validate( Definition $definition, DefinitionRepository $repository ): array {
		$errors = $this->validate_slug( $definition, $repository, 40 );

		if ( '' === $definition->get( 'title', '' ) ) {
			$errors['title'] = __( 'The title is required.', 'libresift' );
		}

		if ( 'taxonomy' === $definition->get( 'source' ) && '' === $definition->get( 'taxonomy', '' ) ) {
			$errors['taxonomy'] = __( 'Choose the taxonomy.', 'libresift' );
		}

		if ( 'meta' === $definition->get( 'source' ) && '' === $definition->get( 'meta_key', '' ) ) {
			$errors['meta_key'] = __( 'Choose the custom field.', 'libresift' );
		}

		if ( 'sort' === $definition->get( 'source' ) && ! $definition->get( 'sort_options' ) ) {
			$errors['sort_options'] = __( 'Add at least one sorting option.', 'libresift' );
		}

		return $errors;
	}

	/**
	 * @param mixed $input
	 * @return array<int, array{label: string, orderby: string, order: string, meta_key: string}>
	 */
	private function sort_options( $input ): array {
		$allowed = [ 'date', 'title', 'menu_order', 'rand', 'modified', 'comment_count', 'meta_value', 'meta_value_num' ];
		$clean   = [];

		foreach ( is_array( $input ) ? $input : [] as $option ) {
			if ( ! is_array( $option ) || empty( $option['label'] ) ) {
				continue;
			}

			$orderby = (string) ( $option['orderby'] ?? 'date' );

			$clean[] = [
				'label'    => sanitize_text_field( (string) $option['label'] ),
				'orderby'  => in_array( $orderby, $allowed, true ) ? $orderby : 'date',
				'order'    => 'ASC' === strtoupper( (string) ( $option['order'] ?? '' ) ) ? 'ASC' : 'DESC',
				'meta_key' => sanitize_key( (string) ( $option['meta_key'] ?? '' ) ),
			];
		}

		return $clean;
	}
}
