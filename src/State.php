<?php
/**
 * Values chosen by the visitor, read from the address (?f_cidade=sao-paulo,rio&f_preco=100-500)
 * or, during an AJAX refresh, from the request body.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

defined( 'ABSPATH' ) || exit;

final class State {

	public const PREFIX = 'f_';

	/** @var array<string, mixed>|null Overrides $_GET while an AJAX refresh renders. */
	private ?array $params = null;

	/**
	 * @param array<string, mixed>|null $params Null goes back to reading $_GET.
	 */
	public function use_params( ?array $params ): void {
		$this->params = $params;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function source(): array {
		return $this->params ?? $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
	}

	/**
	 * Raw value of a filter: a string, or [ 'min' => , 'max' => ] for ranges.
	 *
	 * @return mixed|null
	 */
	public function raw( string $slug ) {
		$source = $this->source();
		$key    = self::PREFIX . $slug;

		if ( ! isset( $source[ $key ] ) || '' === $source[ $key ] || [] === $source[ $key ] ) {
			return null;
		}

		return wp_unslash( $source[ $key ] );
	}

	/**
	 * List of chosen values ("a,b" or [ "a", "b" ]).
	 *
	 * @return string[]
	 */
	public function values( string $slug ): array {
		$raw = $this->raw( $slug );

		if ( null === $raw ) {
			return [];
		}

		$items = is_array( $raw ) ? $raw : explode( ',', (string) $raw );

		return array_values( array_filter( array_map( fn( $item ) => sanitize_text_field( (string) $item ), array_filter( $items, 'is_scalar' ) ), 'strlen' ) );
	}

	/**
	 * Range "100-500", "100-" or [ 'min' => 100, 'max' => 500 ].
	 *
	 * @return array{0: float|null, 1: float|null}
	 */
	public function range( string $slug ): array {
		$raw = $this->raw( $slug );

		if ( is_array( $raw ) ) {
			$min = $raw['min'] ?? null;
			$max = $raw['max'] ?? null;
		} else {
			[ $min, $max ] = array_pad( explode( '-', (string) $raw, 2 ), 2, null );
		}

		return [
			is_numeric( $min ) ? (float) $min : null,
			is_numeric( $max ) ? (float) $max : null,
		];
	}

	public function text( string $slug ): string {
		$raw = $this->raw( $slug );

		return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
	}

	/**
	 * Every filter parameter currently set (to keep them in non-JS forms).
	 *
	 * @return array<string, string>
	 */
	public function all(): array {
		$found = [];

		foreach ( $this->source() as $key => $value ) {
			if ( 0 === strpos( (string) $key, self::PREFIX ) ) {
				$found[ (string) $key ] = is_array( $value ) ? implode( '-', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) : sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}

		return array_filter( $found, 'strlen' );
	}

	public function has_any(): bool {
		return (bool) $this->all();
	}
}
