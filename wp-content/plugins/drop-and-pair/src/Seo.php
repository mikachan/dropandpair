<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Feed, sitemap, legacy redirects and structured data: the SvelteKit site's rss.xml,
 * sitemap.xml and src/lib/seo/jsonld.ts, expressed as WordPress hooks. Anything that
 * mentions a pairing goes through Queries::public_links_for_ride, so drafts never leak.
 */
final class Seo {

	/** How many rides the feed carries. */
	public const FEED_SIZE = 20;

	public static function hooks(): void {
		add_action( 'pre_get_posts', array( self::class, 'feed_query' ) );
		add_action( 'pre_get_posts', array( self::class, 'rides_archive_query' ) );
		add_filter( 'posts_clauses', array( self::class, 'order_clauses' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 1 );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'sitemap_provider' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'sitemap_taxonomies' ) );
		add_action( 'wp_head', array( self::class, 'print_jsonld' ), 20 );
	}

	/* ---------------------------------------------------------------- Feed */

	/**
	 * Newest dp_added first. Ordering by meta_value through WP_Query would drop rides with no
	 * dp_added (or, with an OR'd clause, sort them by some unrelated meta row), so the flag
	 * `dp_feed` has order_clauses() LEFT JOIN the date itself and put undated rides last.
	 */
	public static function feed_query_args(): array {
		return array(
			'post_type'      => 'dp_ride',
			'post_status'    => 'publish',
			'posts_per_page' => self::FEED_SIZE,
			'dp_feed'        => true,
		);
	}

	public static function order_clauses( array $clauses, \WP_Query $query ): array {
		global $wpdb;
		if ( ! $query->get( 'dp_feed' ) ) {
			return $clauses;
		}
		$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS dp_added_m ON ( {$wpdb->posts}.ID = dp_added_m.post_id AND dp_added_m.meta_key = 'dp_added' )";
		$clauses['groupby'] = "{$wpdb->posts}.ID";
		$clauses['orderby'] = "( dp_added_m.meta_value IS NULL OR dp_added_m.meta_value = '' ) ASC, dp_added_m.meta_value DESC, {$wpdb->posts}.post_title ASC";
		return $clauses;
	}

	/** True for the site's main feed only, not comment, archive, search or single-post feeds. */
	private static function is_main_feed( \WP_Query $query ): bool {
		return $query->is_main_query() && $query->is_feed() && ! $query->is_comment_feed()
			&& ! $query->is_singular() && ! $query->is_archive() && ! $query->is_search();
	}

	public static function feed_query( \WP_Query $query ): void {
		if ( is_admin() || ! self::is_main_feed( $query ) ) {
			return;
		}
		foreach ( self::feed_query_args() as $key => $value ) {
			$query->set( $key, $value );
		}
		// WordPress overrides posts_per_page with this option for feeds, and a feed shows
		// the description only, as the old rss.xml did.
		add_filter( 'pre_option_posts_per_rss', static fn() => self::FEED_SIZE );
		add_filter( 'pre_option_rss_use_excerpt', static fn() => 1 );
		// Priority 1: core's strip_tags / ent2ncr / esc_html run after, so the title is escaped once.
		add_filter( 'the_title_rss', array( self::class, 'feed_title' ), 1, 1 );
		add_filter( 'the_guid', array( self::class, 'feed_guid' ), 10, 2 );
		add_action( 'rss2_item', array( self::class, 'feed_category' ) );
		// Core hardcodes isPermaLink="false"; the guid here is the permalink, as in the old rss.xml.
		add_action( 'rss2_ns', static fn() => ob_start( static fn( $xml ) => str_replace( 'isPermaLink="false"', 'isPermaLink="true"', $xml ) ) );
		add_filter( 'the_excerpt_rss', array( self::class, 'feed_description' ), 10, 1 );
		add_filter( 'get_post_time', array( self::class, 'feed_post_time' ), 10, 3 );
	}

	/** "<Ride>, <Park>". */
	public static function feed_title( $title ) {
		$post = get_post();
		if ( ! $post || 'dp_ride' !== $post->post_type ) {
			return $title;
		}
		$park = Queries::park_for_ride( $post->ID );
		return get_the_title( $post ) . ', ' . ( $park ? get_the_title( $park ) : 'unknown park' );
	}

	public static function feed_description( $excerpt ) {
		$post = get_post();
		return $post && 'dp_ride' === $post->post_type ? self::ride_description( $post ) : $excerpt;
	}

	/** The permalink, so subscribers keep recognising items the old feed already delivered. */
	public static function feed_guid( $guid, $id ) {
		$post = get_post( $id );
		return $post && 'dp_ride' === $post->post_type ? get_permalink( $post ) : $guid;
	}

	/** Ride type as the item's category, as the old feed had. */
	public static function feed_category(): void {
		$post  = get_post();
		$types = $post && 'dp_ride' === $post->post_type ? get_the_terms( $post->ID, 'dp_ride_type' ) : false;
		if ( is_array( $types ) && $types ) {
			$label = Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name;
			echo '<category><![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $label ) . "]]></category>\n";
		}
	}

	/** Publish date shown is the date the ride was added to the site, when it has one. */
	public static function feed_post_time( $time, $format, $gmt ) {
		$post  = get_post();
		$added = $post && 'dp_ride' === $post->post_type ? (string) get_post_meta( $post->ID, 'dp_added', true ) : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $added ) || ! is_string( $format ) ) {
			return $time;
		}
		// Midnight in the site's timezone; UTC when the caller asked for GMT.
		$when = new \DateTimeImmutable( $added . ' 00:00:00', wp_timezone() );
		return $when->setTimezone( $gmt ? new \DateTimeZone( 'UTC' ) : wp_timezone() )->format( $format );
	}

	/** First sentence of the write-up, then "<Type> at <Park>, <Country>.", then "N pairings." (public links only). */
	public static function ride_description( \WP_Post $ride ): string {
		$park  = Queries::park_for_ride( $ride->ID );
		$types = get_the_terms( $ride->ID, 'dp_ride_type' );
		$type  = is_array( $types ) && $types ? ( Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name ) : '';
		return self::describe(
			self::lead( $ride->post_content ),
			$type,
			$park ? get_the_title( $park ) : '',
			$park ? (string) get_post_meta( $park->ID, 'dp_country', true ) : '',
			count( Queries::public_links_for_ride( $ride->ID ) )
		);
	}

	/**
	 * The first sentence of some post content, as plain text, for descriptions and
	 * search snippets. A full stop only ends the sentence when a space or the end
	 * follows it, so "1.56 seconds" stays whole.
	 */
	public static function lead( string $html ): string {
		$text = (string) preg_replace( '/<!--.*?-->|<[^>]+>/s', ' ', $html );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		return preg_match( '/^.*?[.!?](?=\s|$)/u', $text, $m ) ? $m[0] : $text;
	}

	/** Pure formatter behind ride_description, mirroring the rss.xml route. */
	public static function describe( string $lead, string $type, string $park, string $country, int $pairings ): string {
		$where = '' === $park ? '' : trim( $type . ' at ' ) . ' ' . $park . ( '' === $country ? '' : ', ' . $country ) . '.';
		$parts = array(
			trim( $lead ),
			'' === $where ? '' : trim( $where ),
			$pairings > 0 ? $pairings . ( 1 === $pairings ? ' pairing.' : ' pairings.' ) : '',
		);
		return implode( ' ', array_filter( $parts, static fn( $p ) => '' !== $p ) );
	}

	/* ------------------------------------------------------------- Sitemap */

	/** No user sitemap: people pages are not content here. */
	public static function sitemap_provider( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function sitemap_post_types( array $types ): array {
		foreach ( array( 'dp_ride', 'dp_park', 'dp_pairing' ) as $slug ) {
			$object = get_post_type_object( $slug );
			if ( $object ) {
				$types[ $slug ] = $object;
			}
		}
		return $types;
	}

	/**
	 * None of the taxonomies has term pages (they redirect to the ride browser), so none
	 * gets a sitemap. Core already skips taxonomies that aren't publicly queryable; this
	 * keeps it that way if that ever changes.
	 */
	public static function sitemap_taxonomies( array $taxonomies ): array {
		unset( $taxonomies['dp_ride_type'], $taxonomies['dp_vibe'], $taxonomies['dp_moment'] );
		return $taxonomies;
	}

	/* -------------------------------------------------------- Rides archive */

	/**
	 * /rides/ is the ride-browser block, which runs its own unpaged query, so the main
	 * query is never shown. Without this it paginated, and /rides/page/2/ repeated the
	 * whole browser. One row and no count is all it needs; /rides/page/N/ redirects.
	 */
	public static function rides_archive_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || $query->is_feed() || ! $query->is_post_type_archive( 'dp_ride' ) ) {
			return;
		}
		$query->set( 'posts_per_page', 1 );
		$query->set( 'no_found_rows', true );
	}

	/* ----------------------------------------------------------- Redirects */

	/**
	 * Where a legacy URL should go, as a site-relative path, or null to leave it alone.
	 * Only ever returns fixed paths plus an encoded term, so it can't be steered to
	 * another host.
	 *
	 * Term archives (/moment/<slug>/, /ride-type/<slug>/, /vibe/<slug>/, plus anything
	 * after the slug) go to the ride browser with that filter set; an unknown moment or
	 * ride type is left to 404. The browser filters vibes by name, so $vibe_name maps a
	 * slug to its name, or null when there's no such vibe; without it the slug is used.
	 *
	 * @param callable|null $vibe_name fn( string $slug ): ?string
	 */
	public static function redirect_target( string $request_uri, ?callable $vibe_name = null ): ?string {
		$parts = explode( '?', $request_uri, 2 );
		$path  = '/' . trim( strtolower( $parts[0] ), '/' );
		if ( preg_match( '#^/rides/page/\d+$#', $path ) ) {
			return '/rides/';
		}
		if ( preg_match( '#^/(moment|ride-type|vibe)/([^/]+)#', $path, $m ) ) {
			$slug = urldecode( $m[2] );
			if ( 'vibe' === $m[1] ) {
				$name = $vibe_name ? $vibe_name( $slug ) : $slug;
				return null === $name ? null : '/rides/?vibe=' . rawurlencode( $name );
			}
			$known = 'moment' === $m[1] ? Vocab::MOMENTS : Vocab::RIDE_TYPES;
			if ( ! isset( $known[ $slug ] ) ) {
				return null;
			}
			return '/rides/?' . ( 'moment' === $m[1] ? 'pairing' : 'type' ) . '=' . rawurlencode( $slug );
		}
		if ( '/rss.xml' === $path ) {
			return '/feed/';
		}
		if ( '/sitemap.xml' === $path ) {
			return '/wp-sitemap.xml';
		}
		if ( '/search' === $path ) {
			parse_str( $parts[1] ?? '', $args );
			$term = isset( $args['q'] ) && is_string( $args['q'] ) ? $args['q'] : '';
			return '/?s=' . rawurlencode( $term );
		}
		return null;
	}

	public static function maybe_redirect(): void {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		// Sites in a subdirectory: judge the path below the home URL.
		$base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $base && 0 === strpos( $uri, $base . '/' ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}
		$vibe   = static function ( string $slug ): ?string {
			$term = get_term_by( 'slug', $slug, 'dp_vibe' );
			return $term instanceof \WP_Term ? $term->name : null;
		};
		$target = self::redirect_target( (string) $uri, $vibe );
		if ( null !== $target ) {
			wp_safe_redirect( home_url( $target ), 301 );
			exit;
		}
	}

	/* ----------------------------------------------------------- JSON-LD */

	private static bool $printed = false;

	/** @return array<string, mixed> */
	public static function website_node(): array {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'alternateName'   => 'Before the queue and after the drop.',
			'description'     => 'Pairings for theme park rides: what to read, watch, listen to and eat before you queue and after you get off.',
			'url'             => home_url( '/' ),
			'inLanguage'      => 'en-GB',
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/** @param array<int, array{name:string, url:string}> $trail */
	public static function breadcrumb_node( array $trail ): array {
		$items = array();
		foreach ( array_values( $trail ) as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	public static function ride_node( \WP_Post $ride ): array {
		$park  = Queries::park_for_ride( $ride->ID );
		$types = get_the_terms( $ride->ID, 'dp_ride_type' );
		$type  = is_array( $types ) && $types ? ( Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name ) : '';
		$vibes = get_the_terms( $ride->ID, 'dp_vibe' );
		$words = is_array( $vibes ) ? wp_list_pluck( $vibes, 'name' ) : array();
		if ( '' !== $type ) {
			$words[] = $type;
		}
		$related = array();
		foreach ( Queries::public_links_for_ride( $ride->ID ) as $link ) {
			$related[] = array(
				'@type' => 'Thing',
				'name'  => get_the_title( $link['pairing'] ),
			);
		}
		$node = array(
			'@type'       => 'TouristAttraction',
			'@id'         => get_permalink( $ride ),
			'name'        => get_the_title( $ride ),
			'description' => self::lead( $ride->post_content ),
			'url'         => get_permalink( $ride ),
		);
		if ( '' !== $type ) {
			$node['additionalType'] = $type;
		}
		if ( $park ) {
			$node['containedInPlace'] = array(
				'@type' => 'AmusementPark',
				'@id'   => get_permalink( $park ),
				'name'  => get_the_title( $park ),
			);
		}
		$node['keywords'] = implode( ', ', $words );
		if ( $related ) {
			$node['isRelatedTo'] = $related;
		}
		return $node;
	}

	public static function park_node( \WP_Post $park ): array {
		$site    = trim( (string) get_post_meta( $park->ID, 'dp_website', true ) );
		$region  = (string) get_post_meta( $park->ID, 'dp_region', true );
		$country = (string) get_post_meta( $park->ID, 'dp_country', true );
		$rides   = count( Queries::rides_for_park( $park->ID ) );
		$node    = array(
			'@type'   => 'AmusementPark',
			'@id'     => get_permalink( $park ),
			'name'    => get_the_title( $park ),
			'url'     => '' !== $site ? $site : get_permalink( $park ),
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressRegion'   => $region,
				'addressCountry'  => $country,
			),
		);
		if ( $rides > 0 ) {
			$node['numberOfItems'] = $rides;
		}
		return $node;
	}

	/** One graph per page: the site node plus whatever describes the current post. */
	public static function page_graph(): array {
		$nodes = array( self::website_node() );
		$post  = is_singular( array( 'dp_ride', 'dp_park', 'dp_pairing' ) ) ? get_queried_object() : null;
		if ( $post instanceof \WP_Post ) {
			$trail = array( array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );
			$arch  = get_post_type_archive_link( $post->post_type );
			$label = get_post_type_object( $post->post_type )->labels->name ?? '';
			if ( $arch ) {
				$trail[] = array( 'name' => $label, 'url' => $arch );
			}
			$trail[] = array( 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) );
			if ( 'dp_ride' === $post->post_type ) {
				$nodes[] = self::ride_node( $post );
			} elseif ( 'dp_park' === $post->post_type ) {
				$nodes[] = self::park_node( $post );
			}
			$nodes[] = self::breadcrumb_node( $trail );
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
	}

	public static function print_jsonld(): void {
		if ( self::$printed || is_feed() || is_admin() ) {
			return;
		}
		self::$printed = true;
		// JSON_HEX flags (via wp_json_encode defaults plus these) keep "</script>" out of the output.
		$json = wp_json_encode( self::page_graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
