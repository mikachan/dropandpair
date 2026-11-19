<?php
use DropAndPair\Analytics;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase {

	public function test_counts_only_logged_out_visits_on_the_live_domain(): void {
		$this->assertTrue( Analytics::counts( false, 'dropandpair.com' ) );
		$this->assertFalse( Analytics::counts( true, 'dropandpair.com' ) );
		$this->assertFalse( Analytics::counts( false, 'drop-pair.mystagingwebsite.com' ) );
		$this->assertFalse( Analytics::counts( false, 'localhost' ) );
		$this->assertFalse( Analytics::counts( false, '' ) );
	}

	public function test_script_points_at_goatcounter(): void {
		$this->assertSame(
			'<script data-goatcounter="https://dropandpair.goatcounter.com/count" async src="https://gc.zgo.at/count.js"></script>' . "\n",
			Analytics::script()
		);
	}
}
