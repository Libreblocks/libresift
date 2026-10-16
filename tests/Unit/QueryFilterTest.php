<?php

use LibreBlocks\Forge\Model\Definition;
use LibreBlocks\Forge\Model\DefinitionRepository;
use LibreBlocks\Sift\FilterKind;
use LibreBlocks\Sift\QueryFilter;
use LibreBlocks\Sift\State;
use PHPUnit\Framework\TestCase;

final class QueryFilterTest extends TestCase {

	private DefinitionRepository $repository;
	private State $state;
	private QueryFilter $filters;

	protected function setUp(): void {
		global $wpdb;

		WPStub::reset();
		WPStub::$registered['taxonomies'] = [ 'cidade' ];
		$wpdb             = new FakeWpdb();
		$this->repository = new DefinitionRepository( libreblocks()->cache() );
		$this->state      = new State();
		$this->filters    = new QueryFilter( $this->repository, $this->state );
	}

	private function filter( string $slug, array $config ): void {
		$this->repository->save( new Definition( 'filter', $slug, ( new FilterKind() )->sanitize( $config + [ 'title' => $slug ] ) ) );
		usleep( 5 );
	}

	public function test_taxonomy_meta_range_search_and_sort(): void {
		$this->filter( 'cidade', [ 'source' => 'taxonomy', 'taxonomy' => 'cidade', 'control' => 'checkboxes' ] );
		$this->filter( 'preco', [ 'source' => 'meta', 'meta_key' => 'preco', 'control' => 'range', 'value_type' => 'NUMERIC' ] );
		$this->filter( 'quartos', [ 'source' => 'meta', 'meta_key' => 'quartos', 'control' => 'select', 'value_type' => 'NUMERIC' ] );
		$this->filter( 'busca', [ 'source' => 'search', 'control' => 'search' ] );
		$this->filter( 'ordem', [
			'source'       => 'sort',
			'control'      => 'select',
			'sort_options' => [ [ 'label' => 'Menor preço', 'orderby' => 'meta_value_num', 'order' => 'asc', 'meta_key' => 'preco' ] ],
		] );

		$this->state->use_params( [ 'f_cidade' => 'sao-paulo,rio', 'f_preco' => '100000-500000', 'f_quartos' => '3', 'f_busca' => 'piscina', 'f_ordem' => '0', 'other' => 'x' ] );

		$args = $this->filters->apply( [ 'post_type' => [ 'imovel' ], 'meta_query' => [ [ 'key' => 'ativo', 'value' => '1' ] ] ], [ 'type' => 'posts' ], 'home' );

		$this->assertSame( [ [ 'taxonomy' => 'cidade', 'field' => 'slug', 'terms' => [ 'sao-paulo', 'rio' ], 'operator' => 'IN' ] ], $args['tax_query'] );
		$this->assertSame( 'AND', $args['meta_query']['relation'] );
		$this->assertSame( [ [ 'key' => 'ativo', 'value' => '1' ] ], $args['meta_query'][0], 'The saved query rules are kept.' );
		$this->assertSame( [ 'key' => 'preco', 'value' => [ 100000.0, 500000.0 ], 'compare' => 'BETWEEN', 'type' => 'DECIMAL(20,4)' ], $args['meta_query'][1] );
		$this->assertSame( [ 'key' => 'quartos', 'value' => [ 3.0 ], 'compare' => 'IN', 'type' => 'NUMERIC' ], $args['meta_query'][2] );
		$this->assertSame( 'piscina', $args['s'] );
		$this->assertSame( 'meta_value_num', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertSame( 'preco', $args['meta_key'] );
	}

	public function test_open_range_and_empty_values(): void {
		$this->filter( 'preco', [ 'source' => 'meta', 'meta_key' => 'preco', 'control' => 'range' ] );
		$this->filter( 'cidade', [ 'source' => 'taxonomy', 'taxonomy' => 'cidade' ] );

		$this->state->use_params( [ 'f_preco' => [ 'min' => '', 'max' => '300000' ], 'f_cidade' => '' ] );
		$args = $this->filters->apply( [], [ 'type' => 'posts' ], 'home' );

		$this->assertSame( [ [ 'key' => 'preco', 'value' => 300000.0, 'compare' => '<=', 'type' => 'DECIMAL(20,4)' ] ], $args['meta_query'] );
		$this->assertArrayNotHasKey( 'tax_query', $args, 'An empty choice does not filter.' );
	}

	public function test_multiple_choice_fields_match_serialized_values(): void {
		$this->filter( 'extras', [ 'source' => 'meta', 'meta_key' => 'extras', 'control' => 'checkboxes' ] );
		$this->state->use_params( [ 'f_extras' => 'piscina' ] );

		$clause = $this->filters->apply( [], [ 'type' => 'posts' ], 'x' )['meta_query'][0];

		$this->assertSame( 'OR', $clause['relation'] );
		$this->assertSame( [ 'key' => 'extras', 'value' => '"piscina"', 'compare' => 'LIKE' ], $clause[1] );
	}

	public function test_targets_and_unknown_taxonomies(): void {
		$this->filter( 'cidade', [ 'source' => 'taxonomy', 'taxonomy' => 'cidade', 'target' => 'imoveis' ] );
		$this->filter( 'nope', [ 'source' => 'taxonomy', 'taxonomy' => 'inexistente' ] );
		$this->state->use_params( [ 'f_cidade' => 'rio', 'f_nope' => 'x' ] );

		$this->assertArrayNotHasKey( 'tax_query', $this->filters->apply( [], [ 'type' => 'posts' ], 'outra_lista' ) );
		$this->assertCount( 1, $this->filters->apply( [], [ 'type' => 'posts' ], 'imoveis' )['tax_query'] );
	}

	public function test_filter_kind_rules(): void {
		$kind   = new FilterKind();
		$config = $kind->sanitize( [ 'source' => 'search', 'control' => 'checkboxes' ] );

		$this->assertSame( 'search', $config['control'], 'Controls that do not fit the source fall back to the first allowed one.' );
		$this->assertSame( 'taxonomy', $kind->sanitize( [ 'source' => 'evil' ] )['source'] );

		$errors = $kind->validate( new Definition( 'filter', 'o', $kind->sanitize( [ 'title' => 'O', 'source' => 'sort' ] ) ), $this->repository );
		$this->assertSame( [ 'sort_options' ], array_keys( $errors ) );
	}

	public function test_state_parsing(): void {
		$state = new State();
		$state->use_params( [ 'f_a' => 'x, ,y', 'f_r' => '10-', 'f_b' => [ 'min' => '1', 'max' => 'abc' ], 'lfp_1' => 2, 'f_empty' => '' ] );

		$this->assertSame( [ 'x', 'y' ], $state->values( 'a' ) );
		$this->assertSame( [ 10.0, null ], $state->range( 'r' ) );
		$this->assertSame( [ 1.0, null ], $state->range( 'b' ) );
		$this->assertSame( [ 'f_a' => 'x, ,y', 'f_r' => '10-', 'f_b' => '1-abc' ], $state->all() );
		$this->assertTrue( $state->has_any() );
	}
}
