<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Lookups shared by the blocks, so "what counts as public" is decided in one place.
 * Always goes through RidePairings::pairing_status, never get_post_status, so a link
 * to some other kind of post can't leak onto a ride page.
 */
final class Queries {

	/** The ride's park, only while the park itself is published. */
	public static function park_for_ride( int $ride_id ): ?\WP_Post {
		$park_id = (int) get_post_meta( $ride_id, 'dp_park_id', true );
		if ( $park_id < 1 ) {
			return null;
		}
		$park = get_post( $park_id );
		return ( $park instanceof \WP_Post && 'dp_park' === $park->post_type && 'publish' === $park->post_status ) ? $park : null;
	}

	/**
	 * Links a visitor may see, in the order they're stored, each with its `pairing` post added.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function public_links_for_ride( int $ride_id ): array {
		$links = get_post_meta( $ride_id, 'dp_pairings', true );
		if ( ! is_array( $links ) ) {
			return array();
		}
		$out = array();
		foreach ( RidePairings::public_links( $links, array( RidePairings::class, 'pairing_status' ) ) as $link ) {
			$pairing = get_post( (int) $link['pairing_id'] );
			if ( $pairing instanceof \WP_Post ) {
				$link['pairing'] = $pairing;
				$out[]           = $link;
			}
		}
		return $out;
	}

	/**
	 * Moment slug of a pairing, or null when it has none. With several terms the one
	 * earliest in Vocab::MOMENTS wins; term order is alphabetical and means nothing.
	 */
	public static function moment_for_pairing( int $pairing_id ): ?string {
		$terms = get_the_terms( $pairing_id, 'dp_moment' );
		if ( ! is_array( $terms ) || ! $terms ) {
			return null;
		}
		$rank = array_flip( array_keys( Vocab::MOMENTS ) );
		$best = null;
		foreach ( $terms as $term ) {
			if ( ! isset( $rank[ $term->slug ] ) ) {
				continue;
			}
			if ( null === $best || $rank[ $term->slug ] < $rank[ $best ] ) {
				$best = $term->slug;
			}
		}
		// Terms outside the vocabulary: fall back to the first rather than hiding the moment.
		return $best ?? $terms[0]->slug;
	}

	/**
	 * Stable sort by Vocab::MOMENTS order, so equal moments keep their link order.
	 * Pairings with no moment go last.
	 *
	 * @param array<int, array<string, mixed>> $links Links from public_links_for_ride().
	 */
	public static function order_by_moment( array $links ): array {
		$rank   = array_flip( array_keys( Vocab::MOMENTS ) );
		$keyed  = array();
		foreach ( array_values( $links ) as $i => $link ) {
			$slug    = self::moment_for_pairing( (int) $link['pairing_id'] );
			$keyed[] = array( $rank[ $slug ] ?? count( $rank ), $i, $link );
		}
		usort( $keyed, static fn( $a, $b ) => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );
		return array_column( $keyed, 2 );
	}

	/**
	 * Moment slugs used by the ride's public pairings, in Vocab order.
	 *
	 * @return string[]
	 */
	public static function moments_for_ride( int $ride_id ): array {
		$found = array();
		foreach ( self::public_links_for_ride( $ride_id ) as $link ) {
			$slug = self::moment_for_pairing( (int) $link['pairing_id'] );
			if ( null !== $slug && isset( Vocab::MOMENTS[ $slug ] ) ) {
				$found[ $slug ] = true;
			}
		}
		return array_values( array_intersect( array_keys( Vocab::MOMENTS ), array_keys( $found ) ) );
	}

	/**
	 * Published rides that list this pairing publicly.
	 *
	 * @return array<int, array{ride:\WP_Post, blurb:string, strength:int, suggested_by:?string}>
	 */
	public static function rides_for_pairing( int $pairing_id ): array {
		// Serialized meta stores ids as `"pairing_id";i:N;`; the LIKE can over-match, so
		// each candidate is checked against its public links below.
		$rides = get_posts(
			array(
				'post_type'      => 'dp_ride',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => 'dp_pairings',
						'value'   => '"pairing_id";i:' . $pairing_id . ';',
						'compare' => 'LIKE',
					),
				),
			)
		);
		$out   = array();
		foreach ( $rides as $ride ) {
			foreach ( self::public_links_for_ride( $ride->ID ) as $link ) {
				if ( (int) $link['pairing_id'] === $pairing_id ) {
					$out[] = array(
						'ride'         => $ride,
						'blurb'        => (string) $link['blurb'],
						'strength'     => (int) $link['strength'],
						'suggested_by' => $link['suggested_by'] ?? null,
					);
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Published rides at a park, by name.
	 *
	 * @return \WP_Post[]
	 */
	public static function rides_for_park( int $park_id ): array {
		return get_posts(
			array(
				'post_type'      => 'dp_ride',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_key'       => 'dp_park_id',
				'meta_value'     => $park_id,
			)
		);
	}

	/**
	 * Published parks grouped by country (A to Z), each with its published ride count.
	 * Parks with no country go under "Elsewhere", last.
	 *
	 * @return array<string, array<int, array{park:\WP_Post, rides:int}>>
	 */
	public static function parks_by_country(): array {
		global $wpdb;
		$parks  = get_posts(
			array(
				'post_type'      => 'dp_park',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$counts = array();
		// One grouped query rather than a ride lookup per park.
		$rows = $wpdb->get_results(
			"SELECT m.meta_value AS park_id, COUNT(*) AS n FROM {$wpdb->postmeta} m
			 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = 'dp_park_id' AND p.post_type = 'dp_ride' AND p.post_status = 'publish'
			 GROUP BY m.meta_value"
		);
		foreach ( $rows as $row ) {
			$counts[ (int) $row->park_id ] = (int) $row->n;
		}
		$groups = array();
		foreach ( $parks as $park ) {
			$country = trim( (string) get_post_meta( $park->ID, 'dp_country', true ) );
			$groups[ '' === $country ? 'Elsewhere' : $country ][] = array(
				'park'  => $park,
				'rides' => $counts[ $park->ID ] ?? 0,
			);
		}
		uksort(
			$groups,
			static fn( $a, $b ) => ( 'Elsewhere' === $a ) <=> ( 'Elsewhere' === $b ) ?: strcasecmp( $a, $b )
		);
		return $groups;
	}

	/**
	 * Published pairings grouped by moment in Vocab order; moments with none are left out.
	 * Pairings with no moment are left out too, since there's no section to put them in.
	 *
	 * @return array<string, \WP_Post[]> Moment slug => pairings, by title.
	 */
	public static function pairings_by_moment(): array {
		$pairings = get_posts(
			array(
				'post_type'      => 'dp_pairing',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$groups   = array();
		foreach ( $pairings as $pairing ) {
			$slug = self::moment_for_pairing( $pairing->ID );
			if ( null !== $slug && isset( Vocab::MOMENTS[ $slug ] ) ) {
				$groups[ $slug ][] = $pairing;
			}
		}
		$ordered = array();
		foreach ( array_keys( Vocab::MOMENTS ) as $slug ) {
			if ( isset( $groups[ $slug ] ) ) {
				$ordered[ $slug ] = $groups[ $slug ];
			}
		}
		return $ordered;
	}
}
