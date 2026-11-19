<?php
use DropAndPair\Queries;
use PHPUnit\Framework\TestCase;

final class HomeRidesTest extends TestCase {

	private function rows(): array {
		return array(
			array( 'id' => 1, 'title' => 'Alpha', 'featured' => true, 'added' => '2026-01-01' ),
			array( 'id' => 2, 'title' => 'Beta', 'featured' => false, 'added' => '2026-03-01' ),
			array( 'id' => 3, 'title' => 'Gamma', 'featured' => true, 'added' => '2026-02-01' ),
			array( 'id' => 4, 'title' => 'Delta', 'featured' => false, 'added' => '' ),
			array( 'id' => 5, 'title' => 'Aardvark', 'featured' => false, 'added' => '2026-03-01' ),
		);
	}

	public function test_featured_rides_come_first_newest_added_first(): void {
		$this->assertSame( array( 3, 1 ), Queries::pick_rides( $this->rows(), 'featured', 6 ) );
	}

	public function test_featured_respects_count(): void {
		$this->assertSame( array( 3 ), Queries::pick_rides( $this->rows(), 'featured', 1 ) );
	}

	public function test_featured_falls_back_to_newest_when_none_flagged(): void {
		$rows = array_map( static fn( $r ) => array_merge( $r, array( 'featured' => false ) ), $this->rows() );
		$this->assertSame( array( 5, 2, 3 ), Queries::pick_rides( $rows, 'featured', 3 ) );
	}

	public function test_newest_orders_by_added_then_title_and_puts_undated_last(): void {
		$this->assertSame( array( 5, 2, 3, 1, 4 ), Queries::pick_rides( $this->rows(), 'newest', 10 ) );
	}

	public function test_unknown_source_or_zero_count_gives_nothing(): void {
		$this->assertSame( array(), Queries::pick_rides( $this->rows(), 'nonsense', 3 ) );
		$this->assertSame( array(), Queries::pick_rides( $this->rows(), 'newest', 0 ) );
	}
}
