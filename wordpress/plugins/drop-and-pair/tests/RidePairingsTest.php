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
		$out      = RidePairings::scrub_published_emails( $links, static fn( int $id ) => $statuses[ $id ], 'publish' );
		$this->assertNull( $out[0]['submitter_email'] );
		$this->assertSame( 'sam@example.com', $out[1]['submitter_email'] );
		// Published but unrated: not public yet, so the email stays.
		$this->assertSame( 'sam@example.com', $out[2]['submitter_email'] );
		$this->assertSame( array( 1, 2, 3 ), array_column( $out, 'pairing_id' ) );
	}

	public function test_scrub_keeps_every_email_while_the_ride_is_unpublished(): void {
		$links = array( $this->link( 1 ), $this->link( 2, 5 ) );
		foreach ( array( 'draft', 'pending', 'future', 'private', 'trash', '' ) as $ride_status ) {
			$out = RidePairings::scrub_published_emails( $links, static fn() => 'publish', $ride_status );
			$this->assertSame( $links, $out, $ride_status );
		}
		$out = RidePairings::scrub_published_emails( $links, static fn() => 'publish', 'publish' );
		$this->assertSame( array( null, null ), array_column( $out, 'submitter_email' ) );
	}

	public function test_keep_new_arrivals_adds_a_link_the_editor_never_loaded(): void {
		$stored = array( $this->link( 1 ), $this->link( 2 ), $this->link( 9 ) );
		$out    = RidePairings::keep_new_arrivals( array( $this->link( 1 ), $this->link( 2 ) ), $stored, array( 1, 2 ) );
		$this->assertSame( array( 1, 2, 9 ), array_column( $out, 'pairing_id' ) );
		// The arrival comes from the stored meta whole, email included.
		$this->assertSame( $stored[2], $out[2] );
	}

	public function test_keep_new_arrivals_honours_a_deliberate_removal(): void {
		$stored = array( $this->link( 1 ), $this->link( 2 ) );
		$out    = RidePairings::keep_new_arrivals( array( $this->link( 1 ) ), $stored, array( 1, 2 ) );
		$this->assertSame( array( 1 ), array_column( $out, 'pairing_id' ) );
	}

	public function test_keep_new_arrivals_lets_the_editors_version_of_a_link_win(): void {
		$edited          = $this->link( 1, 5 );
		$edited['blurb'] = 'Rewritten';
		$out             = RidePairings::keep_new_arrivals( array( $edited ), array( $this->link( 1, 2 ), $this->link( 7 ) ), array( 1 ) );
		$this->assertSame( array( $edited, $this->link( 7 ) ), $out );
	}

	public function test_keep_new_arrivals_with_an_empty_base_keeps_every_stored_link(): void {
		$out = RidePairings::keep_new_arrivals( array( $this->link( 4 ) ), array( $this->link( 1 ), $this->link( 2 ) ), array() );
		$this->assertSame( array( 4, 1, 2 ), array_column( $out, 'pairing_id' ) );
		$this->assertSame( array(), RidePairings::keep_new_arrivals( array(), array(), array() ) );
	}

	public function test_keep_new_arrivals_puts_the_editors_list_first_in_its_order(): void {
		$stored = array( $this->link( 8 ), $this->link( 1 ), $this->link( 2 ), $this->link( 9 ) );
		$out    = RidePairings::keep_new_arrivals( array( $this->link( 2 ), $this->link( 1 ) ), $stored, array( 1, 2 ) );
		$this->assertSame( array( 2, 1, 8, 9 ), array_column( $out, 'pairing_id' ) );
	}

	public function test_keep_new_arrivals_matches_ids_sent_as_strings(): void {
		$incoming = array( array( 'pairing_id' => '1', 'blurb' => 'b' ), 'junk' );
		$out      = RidePairings::keep_new_arrivals( $incoming, array( $this->link( 1 ), $this->link( 3 ), 'junk' ), array( '1', '2' ) );
		$this->assertSame( array( '1', 3 ), array_column( $out, 'pairing_id' ) );
	}
}
