<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Ride list filtering, ported from `src/lib/rides/filter.ts`.
 *
 * The URL is the state: filters are read from and written to query params, so
 * a filtered view is shareable and the back button behaves. Pure functions, so
 * they run without WordPress. `pairing` is a moment slug, as it always was in
 * the URL; the facet is called `moments`.
 */
final class RideFilter {

	/**
	 * Reads the raw query string, because `type=a&type=b` repeats a key and
	 * PHP's `$_GET` would keep only the last one.
	 *
	 * @return array{types:string[],pairing:string,vibe:string,country:string,intensity:string}
	 */
	public static function parse( string $query_string ): array {
		$filters = array(
			'types'     => array(),
			'pairing'   => '',
			'vibe'      => '',
			'country'   => '',
			'intensity' => '',
		);
		$seen    = array();
		foreach ( explode( '&', $query_string ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$bits  = explode( '=', $pair, 2 );
			$key   = urldecode( $bits[0] );
			$value = urldecode( $bits[1] ?? '' );
			if ( 'type' === $key ) {
				if ( '' !== $value ) {
					$filters['types'][] = $value;
				}
			} elseif ( in_array( $key, array( 'pairing', 'vibe', 'country', 'intensity' ), true ) && ! isset( $seen[ $key ] ) ) {
				// First one wins, like URLSearchParams.get.
				$seen[ $key ]     = true;
				$filters[ $key ] = $value;
			}
		}
		return $filters;
	}

	/** Only non-empty filters are written, so an unfiltered list has a clean URL. */
	public static function to_query( array $filters ): string {
		$parts = array();
		foreach ( $filters['types'] ?? array() as $type ) {
			$parts[] = 'type=' . urlencode( (string) $type );
		}
		foreach ( array( 'pairing', 'vibe', 'country', 'intensity' ) as $key ) {
			if ( '' !== (string) ( $filters[ $key ] ?? '' ) ) {
				$parts[] = $key . '=' . urlencode( (string) $filters[ $key ] );
			}
		}
		return implode( '&', $parts );
	}

	public static function is_filtered( array $filters ): bool {
		return self::to_query( $filters ) !== '';
	}

	/**
	 * Rides are arrays of `ride_type`, `moments`, `vibes`, `country`, `intensity` plus anything the caller carries.
	 *
	 * @param array<int, array<string, mixed>> $rides Rides to filter.
	 */
	public static function apply( array $rides, array $filters ): array {
		$types     = $filters['types'] ?? array();
		$intensity = (string) ( $filters['intensity'] ?? '' );
		$out       = array_filter(
			$rides,
			static function ( array $ride ) use ( $filters, $types, $intensity ): bool {
				if ( $types && ! in_array( $ride['ride_type'], $types, true ) ) {
					return false;
				}
				if ( '' !== (string) ( $filters['pairing'] ?? '' ) && ! in_array( $filters['pairing'], $ride['moments'], true ) ) {
					return false;
				}
				if ( '' !== (string) ( $filters['vibe'] ?? '' ) && ! in_array( $filters['vibe'], $ride['vibes'], true ) ) {
					return false;
				}
				if ( '' !== (string) ( $filters['country'] ?? '' ) && $ride['country'] !== $filters['country'] ) {
					return false;
				}
				// Like Number(): a non-number never matches.
				if ( '' !== $intensity && ( ! is_numeric( $intensity ) || (float) $intensity !== (float) $ride['intensity'] ) ) {
					return false;
				}
				return true;
			}
		);
		return array_values( $out );
	}

	/**
	 * Facets come from the rides that exist, so no filter can return nothing on its own.
	 * Controlled vocabularies keep their canonical order; free text goes A to Z.
	 * Rides with no country (park missing or unpublished) add nothing to the country facet.
	 *
	 * @return array{ride_types:string[],moments:string[],vibes:string[],countries:string[],intensities:int[]}
	 */
	public static function facets( array $rides ): array {
		$types = $moments = $vibes = $countries = $intensities = array();
		foreach ( $rides as $ride ) {
			if ( '' !== $ride['ride_type'] ) {
				$types[ $ride['ride_type'] ] = true;
			}
			$intensities[ (int) $ride['intensity'] ] = true;
			if ( '' !== $ride['country'] ) {
				$countries[ $ride['country'] ] = true;
			}
			foreach ( $ride['vibes'] as $vibe ) {
				$vibes[ $vibe ] = true;
			}
			foreach ( $ride['moments'] as $moment ) {
				$moments[ $moment ] = true;
			}
		}
		$alpha = static function ( array $set ): array {
			$values = array_map( 'strval', array_keys( $set ) );
			usort( $values, 'strcasecmp' );
			return $values;
		};
		$vocab = static function ( array $set, array $order ): array {
			$known = array_values( array_intersect( $order, array_map( 'strval', array_keys( $set ) ) ) );
			$other = array_values( array_diff( array_map( 'strval', array_keys( $set ) ), $order ) );
			sort( $other );
			return array_merge( $known, $other );
		};
		$int   = array_keys( $intensities );
		sort( $int );
		return array(
			'ride_types'  => $vocab( $types, array_keys( Vocab::RIDE_TYPES ) ),
			'moments'     => $vocab( $moments, array_keys( Vocab::MOMENTS ) ),
			'vibes'       => $alpha( $vibes ),
			'countries'   => $alpha( $countries ),
			'intensities' => $int,
		);
	}
}
