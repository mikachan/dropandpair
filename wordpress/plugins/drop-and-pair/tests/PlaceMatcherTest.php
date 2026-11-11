<?php
use DropAndPair\Submission\PlaceMatcher;
use PHPUnit\Framework\TestCase;

final class PlaceMatcherTest extends TestCase {

	private function matcher(): PlaceMatcher {
		return new PlaceMatcher(
			array(
				array( 'Wilkołak', 1 ),
				array( 'Wilkołak (Energylandia)', 1 ),
				array( 'Big One', 2 ),
				array( 'Big One (Pleasure Beach)', 2 ),
				array( 'Big One', 3 ),
				array( 'Big One (Fantasy Island)', 3 ),
			),
			// Stands in for WordPress's remove_accents(), for the letters used here.
			static fn( string $text ): string => strtr( $text, array( 'ł' => 'l', 'Ł' => 'L' ) )
		);
	}

	public function test_matches_ignoring_accents_case_and_spacing(): void {
		$m = $this->matcher();
		foreach ( array( 'Wilkołak', 'wilkolak', '  WILKOLAK ', 'wilkołak  (energylandia)' ) as $typed ) {
			$this->assertSame( 1, $m->find( $typed ), $typed );
		}
	}

	public function test_bare_title_shared_by_two_rides_is_ambiguous(): void {
		$m = $this->matcher();
		$this->assertNull( $m->find( 'Big One' ) );
		$this->assertSame( 2, $m->find( 'big one (pleasure beach)' ) );
		$this->assertSame( 3, $m->find( 'Big One (Fantasy Island)' ) );
	}

	public function test_unknown_and_empty_find_nothing(): void {
		$m = $this->matcher();
		$this->assertNull( $m->find( 'Zadra' ) );
		$this->assertNull( $m->find( '' ) );
		$this->assertNull( $m->find( '   ' ) );
	}
}
