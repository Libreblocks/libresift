<?php
/**
 * POST libreblocks/v1/sift/render — re-renders listings with the visitor's filters.
 *
 * Body: { listings: [ { payload, signature } ], params: { f_city: "sp", lfp_abc: 2 } }
 * Only listings signed by the server are rendered; only f_* and lfp_* params are read.
 *
 * @package LibreBlocks\Sift
 */

namespace LibreBlocks\Sift;

use LibreBlocks\Core\Rest\Controller;
use LibreBlocks\Forge\Render\Renderer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class RenderController extends Controller {

	private const MAX_LISTINGS = 10;

	public function register_routes(): void {
		$this->route( '/sift/filters', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'filters' ],
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		] );

		$this->route( '/sift/render', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'render' ],
			// Public on purpose: visitors filter public listings. The signature protects the query.
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Saved filters, for the block and widget selectors.
	 */
	public function filters(): WP_REST_Response {
		return new WP_REST_Response( array_map(
			fn( $filter ) => [ 'value' => $filter->id, 'label' => $filter->title(), 'control' => $filter->get( 'control' ), 'target' => $filter->get( 'target' ) ],
			Plugin::instance()->forge()->repository()->active( FilterKind::ID )
		) );
	}

	public function render( WP_REST_Request $request ): WP_REST_Response {
		$params   = $this->params( (array) $request->get_param( 'params' ) );
		$listings = array_slice( (array) $request->get_param( 'listings' ), 0, self::MAX_LISTINGS );
		$state    = Plugin::instance()->state();
		$renderer = Plugin::instance()->forge()->renderer();
		$output   = [];
		$previous = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$state->use_params( $params );
		$_GET = $params; // Pagination reads lfp_* from $_GET.

		try {
			foreach ( $listings as $listing ) {
				if ( ! is_array( $listing ) ) {
					continue;
				}

				$settings = Renderer::verify( (string) ( $listing['payload'] ?? '' ), (string) ( $listing['signature'] ?? '' ) );

				if ( null === $settings ) {
					continue;
				}

				$output[ (string) $settings['instance'] ] = $renderer->loop( $settings );
			}
		} finally {
			$_GET = $previous;
			$state->use_params( null );
		}

		return new WP_REST_Response( [ 'listings' => $output ] );
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private function params( array $raw ): array {
		$clean = [];

		foreach ( $raw as $key => $value ) {
			$key = (string) $key;

			if ( 0 === strpos( $key, State::PREFIX ) ) {
				$clean[ sanitize_key( $key ) ] = is_array( $value )
					? array_map( fn( $item ) => sanitize_text_field( (string) $item ), array_filter( $value, 'is_scalar' ) )
					: sanitize_text_field( (string) $value );
			} elseif ( 0 === strpos( $key, 'lfp_' ) ) {
				$clean[ sanitize_key( $key ) ] = absint( $value );
			}
		}

		return $clean;
	}
}
