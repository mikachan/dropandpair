<?php
use DropAndPair\ContentTypes;
use PHPUnit\Framework\TestCase;

final class UnsetRideMetaTest extends TestCase {

	public function test_unset_numbers_read_as_null_and_stored_ones_keep_their_value(): void {
		$stored = array( 'dp_inversions' => true, 'dp_intensity' => true );
		$meta   = array(
			'dp_park_id'      => 0,
			'dp_opened'       => 0,
			'dp_inversions'   => 0,
			'dp_height'       => 0.0,
			'dp_intensity'    => 4,
			'dp_roughness'    => null,
			'dp_manufacturer' => '',
			'dp_featured'     => false,
		);
		$out    = ContentTypes::null_unset_numbers( $meta, static fn( string $key ) => isset( $stored[ $key ] ) );
		$this->assertSame(
			array(
				'dp_park_id'      => null,
				'dp_opened'       => null,
				'dp_inversions'   => 0,
				'dp_height'       => null,
				'dp_intensity'    => 4,
				'dp_roughness'    => null,
				'dp_manufacturer' => '',
				'dp_featured'     => false,
			),
			$out
		);
	}

	public function test_unset_numbers_leaves_keys_it_was_not_given_out(): void {
		$this->assertSame( array( 'dp_opened' => null ), ContentTypes::null_unset_numbers( array( 'dp_opened' => 0 ), static fn() => false ) );
	}

	public function test_null_for_a_key_with_nothing_stored_is_dropped(): void {
		$stored = array( 'dp_height' => true );
		$meta   = array(
			'dp_intensity' => null,
			'dp_featured'  => null,
			'dp_height'    => null,
			'dp_opened'    => 1998,
			'dp_pairings'  => array(),
		);
		$out    = ContentTypes::without_null_for_unset( $meta, static fn( string $key ) => isset( $stored[ $key ] ) );
		// A stored value can still be cleared; every non-null value goes through as sent.
		$this->assertSame( array( 'dp_height' => null, 'dp_opened' => 1998, 'dp_pairings' => array() ), $out );
	}
}
