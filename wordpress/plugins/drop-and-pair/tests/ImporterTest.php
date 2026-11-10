<?php
namespace DropAndPair\Tests;

use DropAndPair\Import\Importer;
use PHPUnit\Framework\TestCase;

final class ImporterTest extends TestCase {

	private function data(): array {
		return array(
			'parks'    => array(
				array( 'id' => 'alton-towers', 'name' => 'Alton Towers', 'country' => 'United Kingdom', 'region' => 'Staffordshire', 'website' => 'https://example.test', 'draft' => false, 'body' => '<p>Park text.</p>' ),
			),
			'pairings' => array(
				array( 'id' => 'a-pot-of-tea', 'title' => 'A pot of tea', 'pairingType' => 'wind-down', 'description' => 'Sit down.', 'image' => '/images/tea.jpg', 'draft' => false, 'body' => '<p>Tea.</p>' ),
			),
			'rides'    => array(
				array(
					'id'             => 'hyperion',
					'name'           => 'Hyperion',
					'park'           => 'alton-towers',
					'rideType'       => 'coaster',
					'opened'         => 2018,
					'manufacturer'   => 'Intamin',
					'added'          => '2026-10-01',
					'stats'          => array( 'height' => 77, 'speed' => 130 ),
					'intensity'      => 4,
					'roughness'      => 2,
					'vibe'           => array( 'thrilling' ),
					'gist'           => 'Tall.',
					'pairings'       => array( array( 'pairing' => 'a-pot-of-tea', 'blurb' => 'After.', 'strength' => 3 ) ),
					'furtherReading' => array( array( 'title' => 'RCDB', 'url' => 'https://rcdb.com/1.htm' ) ),
					'featured'       => true,
					'draft'          => false,
					'body'           => '<p>Ride text.</p>',
				),
			),
			'pages'    => array( 'about' => array( 'title' => 'About', 'html' => '<p>Hi.</p>' ) ),
		);
	}

	public function test_creates_everything_as_draft(): void {
		$store  = new InMemoryPostStore();
		$report = ( new Importer( $store ) )->run( $this->data() );

		$this->assertSame( array( 'dp_park:alton-towers', 'dp_pairing:a-pot-of-tea', 'dp_ride:hyperion', 'page:about' ), $report->created );
		$this->assertSame( array(), $report->problems );
		foreach ( $store->posts as $post ) {
			$this->assertSame( 'draft', $post['postarr']['post_status'] );
		}
		$ride = $store->posts[ $store->find_by_slug( 'dp_ride', 'hyperion' ) ];
		$this->assertSame( 'Hyperion', $ride['postarr']['post_title'] );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $ride['postarr']['post_content'] );
		$this->assertSame( array( 'coaster' ), $ride['terms']['dp_ride_type'] );
		$this->assertSame( array( 'thrilling' ), $ride['terms']['dp_vibe'] );
		$this->assertSame( 77, $ride['meta']['dp_height'] );
		$this->assertSame( 130, $ride['meta']['dp_speed'] );
		$this->assertArrayNotHasKey( 'dp_length', $ride['meta'] );
		$this->assertTrue( $ride['meta']['dp_featured'] );
		$this->assertSame( array( array( 'title' => 'RCDB', 'url' => 'https://rcdb.com/1.htm' ) ), $ride['meta']['dp_further_reading'] );
	}

	public function test_resolves_park_and_pairing_slugs_to_ids(): void {
		$store  = new InMemoryPostStore();
		( new Importer( $store ) )->run( $this->data() );

		$park    = $store->find_by_slug( 'dp_park', 'alton-towers' );
		$pairing = $store->find_by_slug( 'dp_pairing', 'a-pot-of-tea' );
		$ride    = $store->posts[ $store->find_by_slug( 'dp_ride', 'hyperion' ) ];

		$this->assertSame( $park, $ride['meta']['dp_park_id'] );
		$this->assertSame(
			array( array( 'pairing_id' => $pairing, 'blurb' => 'After.', 'strength' => 3, 'suggested_by' => null, 'submitter_email' => null ) ),
			$ride['meta']['dp_pairings']
		);
		$this->assertSame( array( 'wind-down' ), $store->posts[ $pairing ]['terms']['dp_moment'] );
		$this->assertSame( '/images/tea.jpg', $store->posts[ $pairing ]['meta']['dp_image'] );
	}

	public function test_existing_slug_is_never_modified(): void {
		$store = new InMemoryPostStore();
		$id    = $store->seed( 'dp_ride', 'hyperion', 'publish' );
		$report = ( new Importer( $store ) )->run( $this->data() );

		$this->assertNotContains( 'dp_ride:hyperion', $store->creates );
		$this->assertContains( 'dp_ride:hyperion', $report->skipped );
		$this->assertSame( 'publish', $store->posts[ $id ]['postarr']['post_status'] );
	}

	public function test_second_run_creates_nothing(): void {
		$store = new InMemoryPostStore();
		$importer = new Importer( $store );
		$importer->run( $this->data() );
		$count = count( $store->creates );

		$report = $importer->run( $this->data() );
		$this->assertSame( array(), $report->created );
		$this->assertCount( 4, $report->skipped );
		$this->assertCount( $count, $store->creates );
	}

	public function test_unknown_park_is_reported_not_fatal(): void {
		$data                   = $this->data();
		$data['rides'][0]['park'] = 'nowhere';
		$store                  = new InMemoryPostStore();
		$report                 = ( new Importer( $store ) )->run( $data );

		$this->assertCount( 1, $report->problems );
		$this->assertStringContainsString( 'nowhere', $report->problems[0] );
		$this->assertContains( 'page:about', $report->created );
		$this->assertNull( $store->find_by_slug( 'dp_ride', 'hyperion' ) );
	}

	public function test_unknown_pairing_is_reported_and_link_dropped(): void {
		$data                                  = $this->data();
		$data['rides'][0]['pairings'][0]['pairing'] = 'missing';
		$store                                 = new InMemoryPostStore();
		$report                                = ( new Importer( $store ) )->run( $data );

		$this->assertCount( 1, $report->problems );
		$ride = $store->posts[ $store->find_by_slug( 'dp_ride', 'hyperion' ) ];
		$this->assertSame( array(), $ride['meta']['dp_pairings'] );
	}

	public function test_failed_create_is_reported_and_the_rest_carry_on(): void {
		$store = new class() extends InMemoryPostStore {
			public function create( array $postarr, array $meta, array $terms ): int {
				if ( 'a-pot-of-tea' === $postarr['post_name'] ) {
					throw new \RuntimeException( 'boom' );
				}
				return parent::create( $postarr, $meta, $terms );
			}
		};
		$report = ( new Importer( $store ) )->run( $this->data() );

		// The pairing fails, so hyperion's link to it is dropped too, but the ride itself is created.
		$this->assertCount( 2, $report->problems );
		$this->assertStringContainsString( 'dp_pairing:a-pot-of-tea', $report->problems[0] );
		$this->assertSame( array( 'dp_park:alton-towers', 'dp_ride:hyperion', 'page:about' ), $report->created );
		$this->assertNull( $store->find_by_slug( 'dp_pairing', 'a-pot-of-tea' ) );
	}
}
