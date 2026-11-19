<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Ride-to-pairing links, stored in the ride's `dp_pairings` meta.
 *
 * The functions below are pure (no WordPress calls) so PHPUnit can run them;
 * `$status_of` lets callers say how to look up a pairing's post status.
 * Only `hooks()` touches WordPress.
 */
final class RidePairings {

	/** Guards against the hooks re-triggering themselves when they write meta. */
	private static bool $busy = false;

	/**
	 * Cleans raw meta or form input into the stored link shape.
	 *
	 * @param array $raw Possibly messy list of links.
	 * @return array<int, array{pairing_id:int, blurb:string, strength:?int, suggested_by:?string, submitter_email:?string}>
	 */
	public static function normalise( array $raw ): array {
		$out  = array();
		$seen = array();
		foreach ( $raw as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$id    = filter_var( $link['pairing_id'] ?? null, FILTER_VALIDATE_INT );
			$blurb = self::clean_string( $link['blurb'] ?? null );
			if ( false === $id || $id < 1 || null === $blurb || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = array(
				'pairing_id'      => $id,
				'blurb'           => $blurb,
				'strength'        => self::clean_strength( $link['strength'] ?? null ),
				'suggested_by'    => self::clean_string( $link['suggested_by'] ?? null ),
				'submitter_email' => self::clean_string( $link['submitter_email'] ?? null ),
			);
		}
		return $out;
	}

	/**
	 * The links a visitor may see: pairing published and strength set. Email never included.
	 *
	 * @param callable $status_of fn( int $pairing_id ): ?string, null when the post is missing.
	 */
	public static function public_links( array $links, callable $status_of ): array {
		$out = array();
		foreach ( $links as $link ) {
			if ( self::is_public( $link, $status_of ) ) {
				unset( $link['submitter_email'] );
				$out[] = $link;
			}
		}
		return $out;
	}

	/**
	 * Once a link is actually showing, the submitter's email has done its job, so drop it.
	 * Showing means the link is public and the ride itself is published too: a link on a
	 * draft ride isn't on any page yet. Links still waiting on review keep theirs so Sarah
	 * can reply.
	 *
	 * @param string $ride_status Post status of the ride the links belong to.
	 */
	public static function scrub_published_emails( array $links, callable $status_of, string $ride_status ): array {
		if ( 'publish' !== $ride_status ) {
			return $links;
		}
		foreach ( $links as $i => $link ) {
			if ( self::is_public( $link, $status_of ) ) {
				$links[ $i ]['submitter_email'] = null;
			}
		}
		return $links;
	}

	/**
	 * A save from the editor sends the whole list as the editor knew it, so a visitor's
	 * suggestion stored since then would be lost. Stored links whose pairing is in neither
	 * the incoming list nor the editor's base (every ID it has shown) arrived after the
	 * editor loaded, so they go after the incoming links, as stored, email and all. A link
	 * in the base but missing from the incoming list was removed on purpose and stays gone.
	 *
	 * @param array $incoming The links in the save request, not yet normalised.
	 * @param array $stored   The ride's current `dp_pairings` meta.
	 * @param array $base_ids Pairing IDs the editor has shown for this ride.
	 */
	public static function keep_new_arrivals( array $incoming, array $stored, array $base_ids ): array {
		$known = array();
		foreach ( $base_ids as $id ) {
			$known[ (int) $id ] = true;
		}
		foreach ( $incoming as $link ) {
			if ( is_array( $link ) ) {
				$known[ (int) ( $link['pairing_id'] ?? 0 ) ] = true;
			}
		}
		$out = $incoming;
		foreach ( $stored as $link ) {
			if ( is_array( $link ) && ! isset( $known[ (int) ( $link['pairing_id'] ?? 0 ) ] ) ) {
				$out[] = $link;
			}
		}
		return $out;
	}

	private static function is_public( array $link, callable $status_of ): bool {
		$strength = $link['strength'] ?? null;
		return is_int( $strength )
			&& $strength >= 1 && $strength <= 5
			&& 'publish' === $status_of( (int) ( $link['pairing_id'] ?? 0 ) );
	}

	private static function clean_string( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}

	private static function clean_strength( $value ): ?int {
		$n = filter_var( $value, FILTER_VALIDATE_INT );
		return ( false !== $n && $n >= 1 && $n <= 5 ) ? $n : null;
	}

	/* ---- WordPress wiring ---- */

	public static function hooks(): void {
		add_action( 'save_post_dp_ride', array( self::class, 'on_save_ride' ) );
		// REST saves write meta after save_post fires, so watch the meta itself too.
		add_action( 'added_post_meta', array( self::class, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( self::class, 'on_meta_change' ), 10, 3 );
		add_action( 'transition_post_status', array( self::class, 'on_pairing_transition' ), 10, 3 );
		add_action( 'transition_post_status', array( self::class, 'on_ride_transition' ), 10, 3 );
		add_filter( 'rest_request_before_callbacks', array( self::class, 'keep_arrivals_in_rest_save' ), 10, 3 );
	}

	/**
	 * The editor's ride saves carry `dp_pairings_base`, the pairing IDs it has shown (see
	 * the save middleware in editor/ride-sidebar.js). With it, links stored since then are
	 * added back to the incoming `dp_pairings` before the controller writes it. Requests
	 * without a base (other API users, an older editor script) are left as they are.
	 * Runs before the callbacks so the posts and autosaves controllers both see the merge.
	 *
	 * @param mixed            $response Passed through untouched.
	 * @param array            $handler  Route handler, unused.
	 * @param \WP_REST_Request $request  The request; its meta param is rewritten in place.
	 * @return mixed
	 */
	public static function keep_arrivals_in_rest_save( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request || 'GET' === $request->get_method()
			|| ! preg_match( '#^/wp/v2/dp_ride/(\d+)(?:/autosaves)?$#', $request->get_route(), $m ) ) {
			return $response;
		}
		$base = $request->get_param( 'dp_pairings_base' );
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $base ) || ! is_array( $meta ) || ! isset( $meta['dp_pairings'] ) || ! is_array( $meta['dp_pairings'] ) ) {
			return $response;
		}
		$stored = get_post_meta( (int) $m[1], 'dp_pairings', true );
		if ( ! is_array( $stored ) || ! $stored ) {
			return $response;
		}
		$meta['dp_pairings'] = self::keep_new_arrivals( $meta['dp_pairings'], $stored, $base );
		$request->set_param( 'meta', $meta );
		return $response;
	}

	/** Publishing a ride puts its live links on a page, so their emails can go. */
	public static function on_ride_transition( $new_status, $old_status, $post ): void {
		if ( 'publish' === $new_status && 'publish' !== $old_status && 'dp_ride' === $post->post_type ) {
			self::scrub_ride( (int) $post->ID );
		}
	}

	public static function on_save_ride( int $ride_id ): void {
		self::scrub_ride( $ride_id );
	}

	public static function on_meta_change( $meta_id, $post_id, $meta_key ): void {
		if ( 'dp_pairings' === $meta_key && 'dp_ride' === get_post_type( $post_id ) ) {
			self::scrub_ride( (int) $post_id );
		}
	}

	public static function on_pairing_transition( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status || 'dp_pairing' !== $post->post_type ) {
			return;
		}
		// Serialized meta stores ids as `"pairing_id";i:N;`; a LIKE can over-match
		// (e.g. N inside a longer run), which is harmless because scrubbing is idempotent.
		$rides = get_posts(
			array(
				'post_type'      => 'dp_ride',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => 'dp_pairings',
						'value'   => '"pairing_id";i:' . (int) $post->ID . ';',
						'compare' => 'LIKE',
					),
				),
			)
		);
		foreach ( $rides as $ride_id ) {
			self::scrub_ride( (int) $ride_id );
		}
	}

	/**
	 * Status of a pairing post, or null when the ID is missing or isn't a `dp_pairing`.
	 * A link to some other published post (a page, say) must not count as live.
	 */
	public static function pairing_status( int $id ): ?string {
		if ( 'dp_pairing' !== get_post_type( $id ) ) {
			return null;
		}
		$status = get_post_status( $id );
		return false === $status ? null : $status;
	}

	private static function scrub_ride( int $ride_id ): void {
		if ( self::$busy || wp_is_post_revision( $ride_id ) ) {
			return;
		}
		$links = get_post_meta( $ride_id, 'dp_pairings', true );
		if ( ! is_array( $links ) || ! $links ) {
			return;
		}
		$scrubbed = self::scrub_published_emails( $links, array( self::class, 'pairing_status' ), (string) get_post_status( $ride_id ) );
		if ( $scrubbed === $links ) {
			return;
		}
		self::$busy = true;
		try {
			update_post_meta( $ride_id, 'dp_pairings', $scrubbed );
		} finally {
			self::$busy = false;
		}
	}
}
