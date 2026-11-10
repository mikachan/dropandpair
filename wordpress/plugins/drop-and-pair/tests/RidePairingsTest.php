<?php
use DropAndPair\RidePairings;
use PHPUnit\Framework\TestCase;

final class RidePairingsTest extends TestCase {

	private function link( int $id, ?int $strength = 3, ?string $email = 'sam@example.com' ): array {
		return array(
			'pairing_id'      => $id,
			'blurb'           => "Blurb $id",
			'strength'        => $strength,
			'suggested_by'    => 'Sam',
			'submitter_email' => $email,
		);
	}

	public function test_normalise_drops_incomplete_and_duplicate_links(): void {
		$out = RidePairings::normalise(
			array(
				array( 'pairing_id' => 5, 'blurb' => '  First  ', 'strength' => 2 ),
				array( 'pairing_id' => 0, 'blurb' => 'No id' ),
				array( 'pairing_id' => -3, 'blurb' => 'Negative id' ),
				array( 'blurb' => 'Missing id' ),
				array( 'pairing_id' => 6, 'blurb' => '   ' ),
				array( 'pairing_id' => '7', 'blurb' => 'String id', 'suggested_by' => ' Alex ', 'submitter_email' => ' a@b.co ' ),
				array( 'pairing_id' => 5, 'blurb' => 'Duplicate', 'strength' => 5 ),
				'not an array',
			)
		);
		$this->assertSame(
			array(
				array( 'pairing_id' => 5, 'blurb' => 'First', 'strength' => 2, 'suggested_by' => null, 'submitter_email' => null ),
				array( 'pairing_id' => 7, 'blurb' => 'String id', 'strength' => null, 'suggested_by' => 'Alex', 'submitter_email' => 'a@b.co' ),
			),
			$out
		);
	}

	public function test_normalise_turns_bad_strength_into_null(): void {
		foreach ( array( 0, 6, 'x', null, 2.5 ) as $bad ) {
			$out = RidePairings::normalise( array( array( 'pairing_id' => 1, 'blurb' => 'b', 'strength' => $bad ) ) );
			$this->assertNull( $out[0]['strength'], var_export( $bad, true ) );
		}
		$out = RidePairings::normalise( array( array( 'pairing_id' => 1, 'blurb' => 'b', 'strength' => '4' ) ) );
		$this->assertSame( 4, $out[0]['strength'] );
	}

	public function test_public_links_drops_unpublished_missing_and_unrated(): void {
		$statuses = array(
			1 => 'draft',
			2 => 'pending',
			3 => 'trash',
			4 => null,
			5 => 'publish',
			6 => 'publish',
			7 => 'publish',
		);
		$links    = array(
			$this->link( 1 ),
			$this->link( 2 ),
			$this->link( 3 ),
			$this->link( 4 ),
			$this->link( 5, null ),
			$this->link( 6, 3 ),
			$this->link( 7, 5 ),
		);
		$out      = RidePairings::public_links( $links, static fn( int $id ) => $statuses[ $id ] );
		$this->assertSame( array( 6, 7 ), array_column( $out, 'pairing_id' ) );
	}

	public function test_public_links_never_exposes_email(): void {
		$out = RidePairings::public_links( array( $this->link( 1 ) ), static fn() => 'publish' );
		$this->assertCount( 1, $out );
		$this->assertArrayNotHasKey( 'submitter_email', $out[0] );
	}

	public function test_scrub_published_emails_keeps_email_on_pending_links(): void {
		$statuses = array( 1 => 'publish', 2 => 'draft', 3 => 'publish' );
		$links    = array( $this->link( 1 ), $this->link( 2 ), $this->link( 3, null ) );
		$out      = RidePairings::scrub_published_emails( $links, static fn( int $id ) => $statuses[ $id ] );
		$this->assertNull( $out[0]['submitter_email'] );
		$this->assertSame( 'sam@example.com', $out[1]['submitter_email'] );
		// Published but unrated: not public yet, so the email stays.
		$this->assertSame( 'sam@example.com', $out[2]['submitter_email'] );
		$this->assertSame( array( 1, 2, 3 ), array_column( $out, 'pairing_id' ) );
	}
}
