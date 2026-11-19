<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/** Site search covers rides, parks, pairings and pages, not blog posts. */
final class Search {

	public const TYPES = array( 'dp_ride', 'dp_park', 'dp_pairing', 'page' );

	public static function hooks(): void {
		add_action( 'pre_get_posts', array( self::class, 'limit_types' ) );
	}

	/** Main front-end search query only: admin lists and other queries keep their own types. */
	public static function limit_types( \WP_Query $query ): void {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$query->set( 'post_type', self::TYPES );
		}
	}
}
