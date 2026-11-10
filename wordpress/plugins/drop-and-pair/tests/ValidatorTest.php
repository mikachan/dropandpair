<?php
use DropAndPair\Submission\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	/** Published rides are 10 and 11; 12 is a draft ride; 20 is a published park. */
	private function validate( array $input ): array {
		$rides = static fn( int $id ): bool => in_array( $id, array( 10, 11 ), true );
		$parks = static fn( int $id ): bool => 20 === $id;
		return Validator::validate( $input, $rides, $parks );
	}

	private function existing_ride( array $overrides = array() ): array {
		return $overrides + array(
			'ride_id' => '10',
			'title'   => 'Fish and chips',
			'moment'  => 'eat-and-drink',
			'blurb'   => 'You come off soaked, so you want something hot.',
			'link'    => '',
			'name'    => 'Sam',
			'email'   => '',
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

	public function test_existing_ride_ignores_new_ride_fields(): void {
		// With JS off every field is visible, so stray new-ride values must not leak through.
		$result = $this->validate( $this->existing_ride( array( 'new_ride_name' => 'Stray', 'park_id' => '0', 'new_park_name' => 'Stray park' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertNull( $result['data']['new_ride_name'] );
		$this->assertNull( $result['data']['park_id'] );
		$this->assertNull( $result['data']['new_park_name'] );
	}

	public function test_valid_new_ride_and_new_park_submission(): void {
		$result = $this->validate(
			$this->existing_ride(
				array(
					'ride_id'          => '0',
					'new_ride_name'    => '  Big One ',
					'park_id'          => '0',
					'new_park_name'    => 'Pleasure Beach',
					'new_park_country' => 'United Kingdom',
				)
			)
		);

		$this->assertTrue( $result['ok'], print_r( $result['errors'], true ) );
		$this->assertSame( 0, $result['data']['ride_id'] );
		$this->assertSame( 'Big One', $result['data']['new_ride_name'] );
		$this->assertSame( 0, $result['data']['park_id'] );
		$this->assertSame( 'Pleasure Beach', $result['data']['new_park_name'] );
		$this->assertSame( 'United Kingdom', $result['data']['new_park_country'] );
	}

	public function test_new_ride_at_existing_park(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride_id' => '0', 'new_ride_name' => 'Big One', 'park_id' => '20', 'new_park_name' => 'Stray' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 20, $result['data']['park_id'] );
		$this->assertNull( $result['data']['new_park_name'] );
	}

	public function test_new_ride_needs_a_name_and_a_park(): void {
		$result = $this->validate( $this->existing_ride( array( 'ride_id' => '0' ) ) );

		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'new_ride_name', $result['errors'] );
		$this->assertArrayHasKey( 'park_id', $result['errors'] );

		$result = $this->validate( $this->existing_ride( array( 'ride_id' => '0', 'new_ride_name' => 'Big One', 'park_id' => '0' ) ) );
		$this->assertArrayHasKey( 'new_park_name', $result['errors'] );
		$this->assertArrayHasKey( 'new_park_country', $result['errors'] );

		$result = $this->validate( $this->existing_ride( array( 'ride_id' => '0', 'new_ride_name' => 'Big One', 'park_id' => '10' ) ) );
		$this->assertArrayHasKey( 'park_id', $result['errors'], 'a ride ID is not a park' );
	}

	public function test_rejects_ride_id_that_is_not_a_published_ride(): void {
		foreach ( array( '12', '20', '999', 'abc', '', '-1' ) as $id ) {
			$result = $this->validate( $this->existing_ride( array( 'ride_id' => $id ) ) );
			$this->assertFalse( $result['ok'], "ride_id $id" );
			$this->assertArrayHasKey( 'ride_id', $result['errors'], "ride_id $id" );
		}
		$missing = $this->existing_ride();
		unset( $missing['ride_id'] );
		$this->assertSame( 'Tell me which ride this goes with.', $this->validate( $missing )['errors']['ride_id'] );
	}

	public function test_strips_tags_and_rejects_overlong_fields(): void {
		$result = $this->validate( $this->existing_ride( array( 'blurb' => 'Hot <script>alert(1)</script>chips <b>after</b> the splash.' ) ) );
		$this->assertTrue( $result['ok'] );
		$this->assertStringNotContainsString( '<', $result['data']['blurb'] );
		$this->assertSame( 'Hot alert(1)chips after the splash.', $result['data']['blurb'] );

		$result = $this->validate( $this->existing_ride( array( 'name' => str_repeat( 'a', 81 ) ) ) );
		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertTrue( $this->validate( $this->existing_ride( array( 'name' => str_repeat( 'é', 80 ) ) ) )['ok'], '80 multibyte chars is fine' );

		$long = array(
			'title'         => 121,
			'blurb'         => 601,
			'new_ride_name' => 121,
		);
		foreach ( $long as $field => $length ) {
			$input  = $this->existing_ride( array( 'ride_id' => '0', 'park_id' => '20', 'new_ride_name' => 'Big One', $field => str_repeat( 'a', $length ) ) );
			$result = $this->validate( $input );
			$this->assertArrayHasKey( $field, $result['errors'], $field );
		}
		$result = $this->validate( $this->existing_ride( array( 'ride_id' => '0', 'new_ride_name' => 'Big One', 'park_id' => '0', 'new_park_name' => str_repeat( 'a', 121 ), 'new_park_country' => str_repeat( 'a', 61 ) ) ) );
		$this->assertArrayHasKey( 'new_park_name', $result['errors'] );
		$this->assertArrayHasKey( 'new_park_country', $result['errors'] );
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
	}

	public function test_non_string_input_is_treated_as_empty(): void {
		$result = $this->validate( $this->existing_ride( array( 'title' => array( 'x' ), 'ride_id' => array( 10 ) ) ) );

		$this->assertArrayHasKey( 'title', $result['errors'] );
		$this->assertArrayHasKey( 'ride_id', $result['errors'] );
	}
}
