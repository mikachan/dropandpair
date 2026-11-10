<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

final class WpPostStore implements PostStore {

	public function find_by_slug( string $type, string $slug ): ?int {
		// 'any' leaves out trash, so list every status. A trashed post also has its
		// post_name changed to "<slug>__trashed" (the original is kept in
		// _wp_desired_post_slug), so match those too: it still owns the slug if restored.
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'name'           => $slug,
				'post_status'    => array_values( get_post_stati() ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'trash',
					'meta_key'       => '_wp_desired_post_slug',
					'meta_value'     => $slug,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
		}
		return $ids ? (int) $ids[0] : null;
	}

	public function create( array $postarr, array $meta, array $terms ): int {
		// wp_insert_post and the meta functions expect slashed data.
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		// Don't leave a half-built post behind: a later run would skip its slug forever.
		try {
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, $key, wp_slash( $value ) );
			}
			foreach ( $terms as $taxonomy => $slugs ) {
				if ( $slugs ) {
					$result = wp_set_object_terms( $id, $slugs, $taxonomy );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( $result->get_error_message() );
					}
				}
			}
		} catch ( \Throwable $e ) {
			wp_delete_post( $id, true );
			throw $e;
		}
		return (int) $id;
	}
}
