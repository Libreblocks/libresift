<?php
/**
 * Applies the visitor's filter values to LibreForge listings (hook libreforge/query/args).
 *
 * A filter with a target only changes the listing with that "Listing ID";
 * a filter without a target changes every listing on the page.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;

defined( 'ABSPATH' ) || exit;

final class QueryFilter {

	public function __construct( private DefinitionRepository $repository, private State $state ) {}

	public function hook(): void {
		add_filter( 'libreforge/query/args', [ $this, 'apply' ], 10, 3 );
		add_filter( 'libreforge/loop/empty_text', [ $this, 'empty_text' ], 10, 2 );
	}

	/**
	 * @return Definition[]
	 */
	public function filters(): array {
		return $this->repository->active( FilterKind::ID );
	}

	public function find( int $id ): ?Definition {
		foreach ( $this->filters() as $filter ) {
			if ( $filter->id === $id ) {
				return $filter;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	public function apply( array $args, array $config, string $listing ): array {
		$type = (string) ( $config['type'] ?? 'posts' );
		$tax  = [];
		$meta = [];

		foreach ( $this->filters() as $filter ) {
			$target = (string) $filter->get( 'target', '' );

			if ( '' !== $target && $target !== $listing ) {
				continue;
			}

			switch ( $filter->get( 'source' ) ) {
				case 'taxonomy':
					$terms = $this->state->values( $filter->slug );

					if ( $terms && 'posts' === $type && taxonomy_exists( (string) $filter->get( 'taxonomy' ) ) ) {
						$tax[] = [ 'taxonomy' => (string) $filter->get( 'taxonomy' ), 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN' ];
					}
					break;

				case 'meta':
					$clause = $this->meta_clause( $filter );

					if ( $clause ) {
						$meta[] = $clause;
					}
					break;

				case 'search':
					$text = $this->state->text( $filter->slug );

					if ( '' !== $text ) {
						if ( 'posts' === $type ) {
							$args['s'] = $text;
						} else {
							$args['search'] = 'users' === $type ? '*' . $text . '*' : $text;
						}
					}
					break;

				case 'sort':
					$args = $this->sort( $filter, $args );
					break;
			}
		}

		if ( $tax ) {
			$args['tax_query'] = $this->merge( $args['tax_query'] ?? [], $tax ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( $meta ) {
			$args['meta_query'] = $this->merge( $args['meta_query'] ?? [], $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $args;
	}

	/**
	 * A clearer message than an empty block when filters match nothing.
	 *
	 * @param array<string, mixed> $settings
	 */
	public function empty_text( string $message, array $settings ): string {
		if ( '' === $message && $this->state->has_any() ) {
			return __( 'Nothing matches these filters. Try removing one.', 'libresift' );
		}

		return $message;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function meta_clause( Definition $filter ): ?array {
		$key     = (string) $filter->get( 'meta_key' );
		$numeric = 'NUMERIC' === $filter->get( 'value_type' );

		if ( '' === $key ) {
			return null;
		}

		switch ( $filter->get( 'control' ) ) {
			case 'range':
				[ $min, $max ] = $this->state->range( $filter->slug );

				if ( null === $min && null === $max ) {
					return null;
				}

				if ( null !== $min && null !== $max ) {
					return [ 'key' => $key, 'value' => [ $min, $max ], 'compare' => 'BETWEEN', 'type' => 'DECIMAL(20,4)' ];
				}

				return [ 'key' => $key, 'value' => $min ?? $max, 'compare' => null !== $min ? '>=' : '<=', 'type' => 'DECIMAL(20,4)' ];

			case 'text':
				$text = $this->state->text( $filter->slug );

				return '' === $text ? null : [ 'key' => $key, 'value' => $text, 'compare' => 'LIKE' ];

			default:
				$values = $this->state->values( $filter->slug );

				if ( ! $values ) {
					return null;
				}

				if ( $numeric ) {
					$values = array_map( 'floatval', $values );
				}

				// Multiple choice fields are saved as serialized arrays: match each value inside them.
				if ( 'checkboxes' === $filter->get( 'control' ) && ! $numeric ) {
					$any = [ 'relation' => 'OR' ];

					foreach ( $values as $value ) {
						$any[] = [ 'key' => $key, 'value' => $value, 'compare' => '=' ];
						$any[] = [ 'key' => $key, 'value' => '"' . $value . '"', 'compare' => 'LIKE' ];
					}

					return $any;
				}

				return [ 'key' => $key, 'value' => $values, 'compare' => 'IN', 'type' => $numeric ? 'NUMERIC' : 'CHAR' ];
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function sort( Definition $filter, array $args ): array {
		$chosen  = $this->state->text( $filter->slug );
		$options = (array) $filter->get( 'sort_options', [] );

		if ( '' === $chosen || ! isset( $options[ (int) $chosen ] ) ) {
			return $args;
		}

		$option          = $options[ (int) $chosen ];
		$args['orderby'] = $option['orderby'];
		$args['order']   = $option['order'];

		if ( in_array( $option['orderby'], [ 'meta_value', 'meta_value_num' ], true ) && '' !== $option['meta_key'] ) {
			$args['meta_key'] = $option['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		return $args;
	}

	/**
	 * Adds clauses to an existing tax_query/meta_query, keeping its own relation intact.
	 *
	 * @param array<int|string, mixed> $existing
	 * @param array<int, mixed>        $clauses
	 * @return array<int|string, mixed>
	 */
	private function merge( array $existing, array $clauses ): array {
		if ( $existing ) {
			array_unshift( $clauses, $existing );
		}

		return count( $clauses ) > 1 ? [ 'relation' => 'AND' ] + $clauses : $clauses;
	}
}
