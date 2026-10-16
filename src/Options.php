<?php
/**
 * The choices a filter offers, found automatically: the terms of a taxonomy,
 * the options of a LibreForge choice field, or the values already saved.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Forge\Model\Definition;

defined( 'ABSPATH' ) || exit;

final class Options {

	private const MAX_VALUES = 200;

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	public function for_filter( Definition $filter ): array {
		if ( 'manual' === $filter->get( 'options_source' ) && $filter->get( 'options' ) ) {
			return (array) $filter->get( 'options' );
		}

		if ( 'taxonomy' === $filter->get( 'source' ) ) {
			return $this->terms( (string) $filter->get( 'taxonomy' ), (bool) $filter->get( 'hide_empty', true ) );
		}

		if ( 'sort' === $filter->get( 'source' ) ) {
			$options = [];

			foreach ( (array) $filter->get( 'sort_options', [] ) as $index => $option ) {
				$options[] = [ 'value' => (string) $index, 'label' => (string) $option['label'] ];
			}

			return $options;
		}

		$key = (string) $filter->get( 'meta_key' );

		return $this->field_options( $key ) ?? $this->saved_values( $key );
	}

	/**
	 * Lowest and highest saved number of a field, for ranges without fixed limits.
	 *
	 * @return array{0: float, 1: float}
	 */
	public function bounds( string $meta_key ): array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT MIN(CAST(meta_value AS DECIMAL(20,4))) AS low, MAX(CAST(meta_value AS DECIMAL(20,4))) AS high FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
			$meta_key
		), ARRAY_A );

		return [ (float) ( $row['low'] ?? 0 ), (float) ( $row['high'] ?? 0 ) ];
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private function terms( string $taxonomy, bool $hide_empty ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => $hide_empty ] );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return array_map( fn( \WP_Term $term ) => [ 'value' => $term->slug, 'label' => $term->name ], $terms );
	}

	/**
	 * Options of the LibreForge field with this name (glossaries included).
	 *
	 * @return array<int, array{value: string, label: string}>|null Null when no choice field has this name.
	 */
	private function field_options( string $name ): ?array {
		$groups = libreblocks()->get( 'forge.fields' );

		foreach ( $groups->all_groups() as $group ) {
			foreach ( $groups->fields_of( $group ) as $field ) {
				if ( $field->name === $name && $field->options ) {
					return $field->options;
				}
			}
		}

		return null;
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private function saved_values( string $meta_key ): array {
		global $wpdb;

		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value ASC LIMIT %d",
			$meta_key,
			self::MAX_VALUES
		) );

		$options = [];

		foreach ( $values as $value ) {
			// Serialized arrays (multiple choice) are not offered as raw text.
			if ( ! is_serialized( $value ) ) {
				$options[] = [ 'value' => (string) $value, 'label' => (string) $value ];
			}
		}

		return $options;
	}
}
