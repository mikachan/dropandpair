<?php
namespace DropAndPair\Import;

use DropAndPair\RidePairings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates posts from the exported content JSON.
 *
 * Everything lands as a draft, and a slug that already exists (in any status) is
 * skipped rather than updated, so a re-run can never overwrite edits made in WordPress.
 */
final class Importer {

	private PostStore $store;

	public function __construct( PostStore $store ) {
		$this->store = $store;
	}

	public function run( array $data ): ImportReport {
		$report = new ImportReport();

		// Parks and pairings first so rides can resolve their IDs.
		foreach ( $data['parks'] ?? array() as $park ) {
			$this->import_park( $park, $report );
		}
		foreach ( $data['pairings'] ?? array() as $pairing ) {
			$this->import_pairing( $pairing, $report );
		}
		foreach ( $data['rides'] ?? array() as $ride ) {
			$this->import_ride( $ride, $report );
		}
		if ( isset( $data['pages']['about'] ) ) {
			$about = $data['pages']['about'];
			$this->import(
				'page',
				'about',
				array(
					'post_title'   => $about['title'],
					'post_content' => HtmlToBlocks::convert( $about['html'] ),
				),
				array(),
				array(),
				$report
			);
		}
		return $report;
	}

	private function import_park( array $park, ImportReport $report ): void {
		$this->import(
			'dp_park',
			$park['id'],
			array(
				'post_title'   => $park['name'],
				'post_content' => HtmlToBlocks::convert( $park['body'] ?? '' ),
			),
			self::present(
				array(
					'dp_country' => $park['country'] ?? null,
					'dp_region'  => $park['region'] ?? null,
					'dp_website' => $park['website'] ?? null,
				)
			),
			array(),
			$report
		);
	}

	private function import_pairing( array $pairing, ImportReport $report ): void {
		$this->import(
			'dp_pairing',
			$pairing['id'],
			array(
				'post_title'   => $pairing['title'],
				'post_content' => HtmlToBlocks::convert( $pairing['body'] ?? '' ),
			),
			self::present(
				array(
					'dp_description' => $pairing['description'] ?? null,
					'dp_url'         => $pairing['url'] ?? null,
					// A local path, kept as-is; there is no media import.
					'dp_image'       => $pairing['image'] ?? null,
				)
			),
			array( 'dp_moment' => array( $pairing['pairingType'] ) ),
			$report
		);
	}

	private function import_ride( array $ride, ImportReport $report ): void {
		$slug = $ride['id'];
		// Resolve first: a missing park is a content problem, not a reason to stop.
		$park_id = $this->store->find_by_slug( 'dp_park', $ride['park'] );
		if ( null === $park_id ) {
			$report->problems[] = "dp_ride:$slug: park '{$ride['park']}' not found, ride not imported";
			return;
		}

		$links = array();
		foreach ( $ride['pairings'] ?? array() as $link ) {
			$pairing_id = $this->store->find_by_slug( 'dp_pairing', $link['pairing'] );
			if ( null === $pairing_id ) {
				$report->problems[] = "dp_ride:$slug: pairing '{$link['pairing']}' not found, link dropped";
				continue;
			}
			$links[] = array(
				'pairing_id'      => $pairing_id,
				'blurb'           => $link['blurb'] ?? '',
				'strength'        => $link['strength'] ?? null,
				'suggested_by'    => null,
				'submitter_email' => null,
			);
		}

		$stats = $ride['stats'] ?? array();
		$meta  = self::present(
			array(
				'dp_park_id'      => $park_id,
				'dp_opened'       => $ride['opened'] ?? null,
				'dp_manufacturer' => $ride['manufacturer'] ?? null,
				'dp_added'        => $ride['added'] ?? null,
				'dp_height'       => $stats['height'] ?? null,
				'dp_speed'        => $stats['speed'] ?? null,
				'dp_length'       => $stats['length'] ?? null,
				'dp_inversions'   => $stats['inversions'] ?? null,
				'dp_intensity'    => $ride['intensity'] ?? null,
				'dp_roughness'    => $ride['roughness'] ?? null,
			)
		);
		$meta['dp_featured']        = (bool) ( $ride['featured'] ?? false );
		$meta['dp_further_reading'] = array_map(
			static fn( array $r ) => array(
				'title' => $r['title'],
				'url'   => $r['url'],
			),
			$ride['furtherReading'] ?? array()
		);
		// Through normalise() so strength is always an int, as the hooks expect.
		$meta['dp_pairings'] = RidePairings::normalise( $links );

		$this->import(
			'dp_ride',
			$slug,
			array(
				'post_title'   => $ride['name'],
				'post_content' => HtmlToBlocks::convert( $ride['body'] ?? '' ),
			),
			$meta,
			array(
				'dp_ride_type' => array( $ride['rideType'] ),
				'dp_vibe'      => array_values( $ride['vibe'] ?? array() ),
			),
			$report
		);
	}

	private function import( string $type, string $slug, array $postarr, array $meta, array $terms, ImportReport $report ): void {
		$label = "$type:$slug";
		if ( null !== $this->store->find_by_slug( $type, $slug ) ) {
			$report->skipped[] = $label;
			return;
		}
		// One bad item shouldn't stop the rest; the store cleans up after itself, so a re-run retries it.
		try {
			$this->store->create(
				$postarr + array(
					'post_type'   => $type,
					'post_name'   => $slug,
					'post_status' => 'draft',
				),
				$meta,
				$terms
			);
		} catch ( \Throwable $e ) {
			$report->problems[] = "$label: import failed: " . $e->getMessage();
			return;
		}
		$report->created[] = $label;
	}

	/** Drops nulls so optional fields don't become empty meta rows. */
	private static function present( array $meta ): array {
		return array_filter( $meta, static fn( $v ) => null !== $v );
	}
}
