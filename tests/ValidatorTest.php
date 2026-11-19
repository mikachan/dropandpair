<?php
use DropAndPair\Submission\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	/**
	 * Fake finders standing in for Handler's: published rides are Hyperion (10) and
	 * Zadra (11), both at Energylandia (20), which is the only published park.
	 */
	private function validate( array $input ): array {
		$rides = static fn( string $t ): ?int => array(
			'hyperion'                => 10,
			'hyperion (energylandia)' => 10,
			'zadra'                   => 11,
			'zadra (energylandia)'    => 11,
		)[ strtolower( $t ) ] ?? null;
		$parks = static fn( string $t ): ?int => array( 'energylandia' => 20 )[ strtolower( $t ) ] ?? null;
		return Validator::validate( $input, $rides, $parks );
	}

	private function existing_ride( array $overrides = array() ): array {
		return $overrides + array(
			'ride'   => 'Hyperion (Energylandia)',
			'title'  => 'Fish and chips',
			'moment' => 'eat-and-drink',
			'blurb'  => 'You come off soaked, so you want something hot.',
			'link'   => '',
			'name'   => 'Sam',
			'email'  => '',
		);
	}

	public function test_valid_existing_ride_submission(): void {
		$result = $this->validate( $this->existing_ride( array( 'link' => 'https://example.com/chips', 'email' => 'sam@example.com' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame(
			array(
				'ride_id'          => 10,
				'new_ride_name'    => null,
				'park_id'          => null,
				'new_park_name'    => null,
				'new_park_country' => null,
				'title'            => 'Fish and chips',
				'moment'           => 'eat-and-drink',
				'blurb'            => 'You come off soaked, so you want something hot.',
				'link'             => 'https://example.com/chips',
				'name'             => 'Sam',
				'email'            => 'sam@example.com',
			),
			$result['data']
		);
	}

	public function test_known_ride_by_bare_title(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride' => '  Zadra ' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 11, $result['data']['ride_id'] );
		$this->assertNull( $result['data']['new_ride_name'] );
	}

	public function test_known_ride_ignores_park_and_country(): void {
		// With JS off the park and country fields are visible, so stray values must not leak through.
		$result = $this->validate( $this->existing_ride( array( 'park' => 'Stray park', 'new_park_country' => 'Strayland' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 10, $result['data']['ride_id'] );
		$this->assertNull( $result['data']['new_ride_name'] );
		$this->assertNull( $result['data']['park_id'] );
		$this->assertNull( $result['data']['new_park_name'] );
		$this->assertNull( $result['data']['new_park_country'] );
	}

	public function test_new_ride_at_known_park(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride' => '  Big One ', 'park' => 'Energylandia', 'new_park_country' => 'Stray' ) ) );

		$this->assertTrue( $result['ok'], print_r( $result['errors'], true ) );
		$this->assertSame( 0, $result['data']['ride_id'] );
		$this->assertSame( 'Big One', $result['data']['new_ride_name'] );
		$this->assertSame( 20, $result['data']['park_id'] );
		$this->assertNull( $result['data']['new_park_name'] );
		$this->assertNull( $result['data']['new_park_country'] );
	}

	public function test_new_ride_at_new_park_needs_a_country(): void {
		$input  = $this->existing_ride( array( 'ride' => 'Big One', 'park' => ' Pleasure  Beach ' ) );
		$result = $this->validate( $input );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array( 'new_park_country' ), array_keys( $result['errors'] ) );
		$this->assertSame( 'Which country is the park in?', $result['errors']['new_park_country'] );

		$result = $this->validate( array( 'new_park_country' => 'United Kingdom' ) + $input );
		$this->assertTrue( $result['ok'], print_r( $result['errors'], true ) );
		$this->assertSame( 0, $result['data']['ride_id'] );
		$this->assertSame( 'Big One', $result['data']['new_ride_name'] );
		$this->assertSame( 0, $result['data']['park_id'] );
		$this->assertSame( 'Pleasure Beach', $result['data']['new_park_name'] );
		$this->assertSame( 'United Kingdom', $result['data']['new_park_country'] );
	}

	public function test_empty_ride(): void {
		foreach ( array( '', '   ', '<b></b>' ) as $ride ) {
			$result = $this->validate( $this->existing_ride( array( 'ride' => $ride ) ) );
			$this->assertFalse( $result['ok'], "ride '$ride'" );
			$this->assertSame( 'Tell me which ride this goes with.', $result['errors']['ride'], "ride '$ride'" );
			$this->assertArrayNotHasKey( 'park', $result['errors'], 'no park question until we know the ride is new' );
		}
		$missing = $this->existing_ride();
		unset( $missing['ride'] );
		$this->assertSame( 'Tell me which ride this goes with.', $this->validate( $missing )['errors']['ride'] );
	}

	public function test_new_ride_needs_a_park(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride' => 'Big One', 'park' => ' ' ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array( 'park' ), array_keys( $result['errors'] ) );
		$this->assertSame( 'Which park is it at?', $result['errors']['park'] );
	}

	public function test_overlong_new_ride_and_park_names(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride' => str_repeat( 'a', 121 ), 'park' => 'Energylandia' ) ) );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( "That's a bit long. Could you keep it under 120 characters?", $result['errors']['ride'] );

		$this->assertTrue( $this->validate( $this->existing_ride( array( 'ride' => str_repeat( 'é', 120 ), 'park' => 'Energylandia' ) ) )['ok'], '120 multibyte chars is fine' );

		$result = $this->validate( $this->existing_ride( array( 'ride' => 'Big One', 'park' => str_repeat( 'a', 121 ), 'new_park_country' => str_repeat( 'a', 61 ) ) ) );
		$this->assertArrayHasKey( 'park', $result['errors'] );
		$this->assertArrayHasKey( 'new_park_country', $result['errors'] );
	}

	public function test_strips_tags_and_rejects_overlong_fields(): void {
		$result = $this->validate( $this->existing_ride( array( 'blurb' => 'Hot <script>alert(1)</script>chips <b>after</b> the splash.' ) ) );
		$this->assertTrue( $result['ok'] );
		$this->assertStringNotContainsString( '<', $result['data']['blurb'] );
		$this->assertSame( 'Hot chips after the splash.', $result['data']['blurb'], 'script contents go too' );

		$result = $this->validate( $this->existing_ride( array( 'name' => str_repeat( 'a', 81 ) ) ) );
		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertTrue( $this->validate( $this->existing_ride( array( 'name' => str_repeat( 'é', 80 ) ) ) )['ok'], '80 multibyte chars is fine' );

		foreach ( array( 'title' => 121, 'blurb' => 601 ) as $field => $length ) {
			$result = $this->validate( $this->existing_ride( array( $field => str_repeat( 'a', $length ) ) ) );
			$this->assertArrayHasKey( $field, $result['errors'], $field );
		}
	}

	public function test_keeps_angle_brackets_that_are_not_tags(): void {
		$keep = array(
			'I <3 this',
			'<£5 at the kiosk',
			'Kids <3 it, honestly',
			'Queue < 10 minutes, then > 1 hour',
		);
		foreach ( $keep as $text ) {
			$this->assertSame( $text, $this->validate( $this->existing_ride( array( 'title' => $text ) ) )['data']['title'], $text );
		}
		$strip = array(
			'<script>x</script>'            => null,
			'<b>hi</b>'                     => 'hi',
			'Tea <!-- sneaky --> and cake'  => 'Tea and cake',
			'<a href="http://x">Chips</a>!' => 'Chips!',
		);
		foreach ( $strip as $text => $expected ) {
			$this->assertSame( $expected, $this->validate( $this->existing_ride( array( 'title' => $text ) ) )['data']['title'], $text );
		}
	}

	public function test_requires_title_name_and_a_real_blurb(): void {
		$result = $this->validate( $this->existing_ride( array( 'title' => '<b></b>', 'name' => ' ', 'blurb' => 'Too short' ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array( 'title', 'blurb', 'name' ), array_keys( $result['errors'] ) );
	}

	public function test_single_line_fields_collapse_whitespace(): void {
		$result = $this->validate( $this->existing_ride( array( 'title' => "Fish\r\nBcc: x@y.z", 'name' => "Sam\t Smith" ) ) );

		$this->assertSame( 'Fish Bcc: x@y.z', $result['data']['title'] );
		$this->assertSame( 'Sam Smith', $result['data']['name'] );
	}

	public function test_rejects_unknown_moment(): void {
		foreach ( array( 'snack', '', 'Before' ) as $moment ) {
			$result = $this->validate( $this->existing_ride( array( 'moment' => $moment ) ) );
			$this->assertArrayHasKey( 'moment', $result['errors'], "moment '$moment'" );
		}
		$this->assertTrue( $this->validate( $this->existing_ride( array( 'moment' => 'later' ) ) )['ok'] );
	}

	public function test_email_optional_but_validated(): void {
		$this->assertNull( $this->validate( $this->existing_ride( array( 'email' => '  ' ) ) )['data']['email'] );

		$result = $this->validate( $this->existing_ride( array( 'email' => 'not an email' ) ) );
		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
	}

	public function test_link_optional_but_must_be_http(): void {
		$this->assertNull( $this->validate( $this->existing_ride() )['data']['link'] );
		foreach ( array( 'javascript:alert(1)', 'ftp://example.com', 'example.com' ) as $link ) {
			$this->assertArrayHasKey( 'link', $this->validate( $this->existing_ride( array( 'link' => $link ) ) )['errors'], $link );
		}
		$this->assertTrue( $this->validate( $this->existing_ride( array( 'link' => 'http://example.com' ) ) )['ok'] );

		$base = 'https://example.com/';
		$this->assertTrue( $this->validate( $this->existing_ride( array( 'link' => $base . str_repeat( 'a', 2000 - strlen( $base ) ) ) ) )['ok'], '2000 is fine' );
		$long = $this->validate( $this->existing_ride( array( 'link' => $base . str_repeat( 'a', 2001 - strlen( $base ) ) ) ) );
		$this->assertArrayHasKey( 'link', $long['errors'] );
		$this->assertStringContainsString( 'long', $long['errors']['link'] );
	}

	public function test_non_string_input_is_treated_as_empty(): void {
		$result = $this->validate( $this->existing_ride( array( 'title' => array( 'x' ), 'ride' => array( 'Hyperion' ) ) ) );

		$this->assertArrayHasKey( 'title', $result['errors'] );
		$this->assertArrayHasKey( 'ride', $result['errors'] );
	}
}
