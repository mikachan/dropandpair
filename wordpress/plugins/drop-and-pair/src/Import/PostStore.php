<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

/** What the importer needs from WordPress, so tests can swap in a fake. */
interface PostStore {

	/** The post ID with this slug in any status (trash included), or null. */
	public function find_by_slug( string $type, string $slug ): ?int;

	/**
	 * @param array                  $postarr wp_insert_post() fields.
	 * @param array<string, mixed>   $meta    Meta key => value.
	 * @param array<string, string[]> $terms  Taxonomy => term slugs.
	 * @return int New post ID.
	 */
	public function create( array $postarr, array $meta, array $terms ): int;
}
