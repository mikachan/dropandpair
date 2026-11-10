<?php
use DropAndPair\Vocab;
use PHPUnit\Framework\TestCase;

final class VocabTest extends TestCase {

	public function test_moments_match_labels_ts_order(): void {
		$this->assertSame(
			array( 'before', 'right-after', 'wind-down', 'eat-and-drink', 'do-next', 'listen', 'read', 'watch', 'later' ),
			array_keys( Vocab::MOMENTS )
		);
	}

	public function test_ride_types_in_order(): void {
		$this->assertSame( array( 'coaster', 'dark-ride', 'flat-ride', 'water-ride', 'drop-tower' ), array_keys( Vocab::RIDE_TYPES ) );
		$this->assertSame( 'Dark ride', Vocab::RIDE_TYPES['dark-ride'] );
	}

	public function test_known_colours(): void {
		$this->assertSame( '#ffd23f', Vocab::MOMENTS['before']['colour'] );
		$this->assertSame( '#7fd1c7', Vocab::MOMENTS['eat-and-drink']['colour'] );
		$this->assertSame( '#f49cbb', Vocab::MOMENTS['read']['colour'] );
	}

	public function test_scale_words(): void {
		$this->assertSame( array( 'Gentle', 'Mild', 'Moderate', 'Strong', 'Maximum' ), array_map( array( Vocab::class, 'intensity_label' ), range( 1, 5 ) ) );
		$this->assertSame( array( 'Silky', 'Smooth', 'Bumpy', 'Rough', 'Brutal' ), array_map( array( Vocab::class, 'comfort_label' ), range( 1, 5 ) ) );
		$this->assertSame(
			array( 'Worth a try', 'Gentle match', 'Good match', 'Strong match', 'Perfect marriage' ),
			array_map( array( Vocab::class, 'strength_label' ), range( 1, 5 ) )
		);
		foreach ( array( 0, 6 ) as $out ) {
			$this->assertSame( 'Unrated intensity', Vocab::intensity_label( $out ) );
			$this->assertSame( 'Unrated comfort', Vocab::comfort_label( $out ) );
			$this->assertSame( 'Unrated match', Vocab::strength_label( $out ) );
		}
	}

	public function test_moment_descriptions_copied(): void {
		$this->assertSame(
			'For the hotel, the flight, or the long wait over the winter closed season.',
			Vocab::MOMENTS['read']['description']
		);
		$this->assertSame( "Something to eat or drink once you're off the ride.", Vocab::MOMENTS['eat-and-drink']['description'] );
	}

	public function test_every_moment_has_distinct_colour(): void {
		$colours = array_column( Vocab::MOMENTS, 'colour' );
		$this->assertCount( 9, array_unique( $colours ) );
	}

	public function test_colours_readable_with_ink_text(): void {
		$lum = static function ( string $hex ): float {
			$c = array_map(
				static function ( $v ) {
					$v = hexdec( $v ) / 255;
					return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
				},
				str_split( ltrim( $hex, '#' ), 2 )
			);
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$ink = $lum( '#1a1a1a' );
		foreach ( Vocab::MOMENTS as $slug => $m ) {
			$ratio = ( $lum( $m['colour'] ) + 0.05 ) / ( $ink + 0.05 );
			$this->assertGreaterThanOrEqual( 4.5, $ratio, $slug );
		}
	}
}
