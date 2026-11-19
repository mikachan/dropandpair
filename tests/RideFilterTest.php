<?php
use DropAndPair\RideFilter;
use PHPUnit\Framework\TestCase;

final class RideFilterTest extends TestCase {

	private const EMPTY = array(
		'types'     => array(),
		'pairing'   => '',
		'vibe'      => '',
		'country'   => '',
		'intensity' => '',
	);

	private function ride( array $overrides = array() ): array {
		return $overrides + array(
			'id'        => 'a-ride',
			'ride_type' => 'coaster',
			'intensity' => 3,
			'vibes'     => array( 'brutal' ),
			'country'   => 'United Kingdom',
			'moments'   => array( 'watch' ),
		);
	}

	private function rides(): array {
		return array(
			$this->ride( array( 'id' => 'nemesis', 'ride_type' => 'coaster', 'intensity' => 5, 'vibes' => array( 'brutal', 'scenic' ) ) ),
			$this->ride( array( 'id' => 'valhalla', 'ride_type' => 'water-ride', 'intensity' => 3, 'vibes' => array( 'eerie' ), 'moments' => array( 'later' ) ) ),
			$this->ride(
				array(
					'id'        => 'silver-star',
					'ride_type' => 'coaster',
					'intensity' => 4,
					'vibes'     => array( 'stately' ),
					'moments'   => array( 'listen', 'eat-and-drink' ),
					'country'   => 'Germany',
				)
			),
		);
	}

	private function ids( array $rides ): array {
		return array_column( $rides, 'id' );
	}

	public function test_round_trips_a_full_selection(): void {
		$query   = 'type=coaster&type=water-ride&pairing=watch&vibe=brutal&country=Germany&intensity=4';
		$filters = RideFilter::parse( $query );

		$this->assertSame(
			array(
				'types'     => array( 'coaster', 'water-ride' ),
				'pairing'   => 'watch',
				'vibe'      => 'brutal',
				'country'   => 'Germany',
				'intensity' => '4',
			),
			$filters
		);
		$this->assertSame( $query, RideFilter::to_query( $filters ) );
	}

	public function test_parse_reads_repeated_type_params(): void {
		$filters = RideFilter::parse( 'type=coaster&type=dark-ride&intensity=5' );

		$this->assertSame( array( 'coaster', 'dark-ride' ), $filters['types'] );
		$this->assertSame( '5', $filters['intensity'] );
	}

	public function test_parse_decodes_values_and_drops_empty_types(): void {
		$filters = RideFilter::parse( 'type=&type=coaster&country=United+Kingdom' );

		$this->assertSame( array( 'coaster' ), $filters['types'] );
		$this->assertSame( 'United Kingdom', $filters['country'] );
		$this->assertSame( 'country=United+Kingdom', RideFilter::to_query( array( 'country' => 'United Kingdom' ) + self::EMPTY ) );
	}

	public function test_reads_an_empty_query_as_no_filters_and_writes_no_filters_as_an_empty_query(): void {
		$this->assertSame( self::EMPTY, RideFilter::parse( '' ) );
		$this->assertSame( '', RideFilter::to_query( self::EMPTY ) );
		$this->assertFalse( RideFilter::is_filtered( self::EMPTY ) );
	}

	public function test_leaves_empty_values_out_of_the_query_so_a_cleared_filter_leaves_a_clean_url(): void {
		$this->assertSame( 'vibe=brutal', RideFilter::to_query( array( 'vibe' => 'brutal' ) + self::EMPTY ) );
		$this->assertTrue( RideFilter::is_filtered( array( 'vibe' => 'brutal' ) + self::EMPTY ) );
	}

	public function test_returns_everything_when_nothing_is_selected(): void {
		$this->assertCount( 3, RideFilter::apply( $this->rides(), self::EMPTY ) );
	}

	public function test_treats_several_ride_types_as_any_of_these(): void {
		$this->assertCount( 3, RideFilter::apply( $this->rides(), array( 'types' => array( 'coaster', 'water-ride' ) ) + self::EMPTY ) );
		$this->assertCount( 1, RideFilter::apply( $this->rides(), array( 'types' => array( 'water-ride' ) ) + self::EMPTY ) );
	}

	public function test_matches_a_ride_by_the_pairing_types_it_covers(): void {
		$result = RideFilter::apply( $this->rides(), array( 'pairing' => 'listen' ) + self::EMPTY );

		$this->assertSame( array( 'silver-star' ), $this->ids( $result ) );
	}

	public function test_matches_a_vibe_anywhere_in_the_list(): void {
		$result = RideFilter::apply( $this->rides(), array( 'vibe' => 'scenic' ) + self::EMPTY );

		$this->assertSame( array( 'nemesis' ), $this->ids( $result ) );
	}

	public function test_matches_country_through_the_park(): void {
		$result = RideFilter::apply( $this->rides(), array( 'country' => 'Germany' ) + self::EMPTY );

		$this->assertSame( array( 'silver-star' ), $this->ids( $result ) );
	}

	public function test_matches_intensity_as_an_exact_intensity(): void {
		$result = RideFilter::apply( $this->rides(), array( 'intensity' => '5' ) + self::EMPTY );

		$this->assertSame( array( 'nemesis' ), $this->ids( $result ) );
		$this->assertSame( array(), RideFilter::apply( $this->rides(), array( 'intensity' => 'lots' ) + self::EMPTY ) );
	}

	public function test_combines_filters_as_and(): void {
		$result = RideFilter::apply( $this->rides(), array( 'types' => array( 'coaster' ), 'country' => 'United Kingdom' ) + self::EMPTY );

		$this->assertSame( array( 'nemesis' ), $this->ids( $result ) );
		$this->assertSame( array(), RideFilter::apply( $this->rides(), array( 'types' => array( 'water-ride' ), 'country' => 'Germany' ) + self::EMPTY ) );
	}

	public function test_offers_only_values_that_exist_so_no_filter_can_return_nothing_on_its_own(): void {
		$facets = RideFilter::facets( $this->rides() );

		$this->assertSame( array( 'coaster', 'water-ride' ), $facets['ride_types'] );
		$this->assertSame( array( 'Germany', 'United Kingdom' ), $facets['countries'] );
		$this->assertSame( array( 3, 4, 5 ), $facets['intensities'] );
		$this->assertSame( array( 'brutal', 'eerie', 'scenic', 'stately' ), $facets['vibes'] );
	}

	public function test_keeps_controlled_vocabularies_in_canonical_order(): void {
		$facets = RideFilter::facets( $this->rides() );

		// Moment order: eat-and-drink, listen, watch, later.
		$this->assertSame( array( 'eat-and-drink', 'listen', 'watch', 'later' ), $facets['moments'] );
	}

	public function test_every_facet_value_matches_at_least_one_ride(): void {
		$rides  = $this->rides();
		$facets = RideFilter::facets( $rides );

		foreach ( $facets['vibes'] as $vibe ) {
			$this->assertNotEmpty( RideFilter::apply( $rides, array( 'vibe' => $vibe ) + self::EMPTY ) );
		}
		foreach ( $facets['countries'] as $country ) {
			$this->assertNotEmpty( RideFilter::apply( $rides, array( 'country' => $country ) + self::EMPTY ) );
		}
	}

	public function test_leaves_rides_with_no_country_out_of_the_country_facet(): void {
		$rides = array( $this->ride( array( 'country' => '' ) ) );

		$this->assertSame( array(), RideFilter::facets( $rides )['countries'] );
	}

	public function test_parse_ignores_unknown_keys_such_as_types(): void {
		$this->assertSame( array(), RideFilter::parse( 'types=x' )['types'] );
		$this->assertSame( self::EMPTY, RideFilter::parse( 'types=x&foo=bar' ) );
	}

	public function test_treats_zero_as_a_real_value(): void {
		$filters = RideFilter::parse( 'intensity=0' );

		$this->assertSame( 'intensity=0', RideFilter::to_query( $filters ) );
		$this->assertTrue( RideFilter::is_filtered( $filters ) );
		$rides = array( $this->ride( array( 'id' => 'unrated', 'intensity' => 0 ) ), $this->ride() );
		$this->assertSame( array( 'unrated' ), $this->ids( RideFilter::apply( $rides, $filters ) ) );
	}

	public function test_leaves_rides_with_no_type_out_of_the_type_facet(): void {
		$this->assertSame( array(), RideFilter::facets( array( $this->ride( array( 'ride_type' => '' ) ) ) )['ride_types'] );
	}

	public function test_does_not_offer_unrated_intensity_but_still_filters_by_it(): void {
		$rides = array( $this->ride( array( 'id' => 'unrated', 'intensity' => 0 ) ), $this->ride( array( 'intensity' => 2 ) ) );

		$this->assertSame( array( 2 ), RideFilter::facets( $rides )['intensities'] );
		$this->assertSame( array( 'unrated' ), $this->ids( RideFilter::apply( $rides, RideFilter::parse( 'intensity=0' ) ) ) );
	}
}
