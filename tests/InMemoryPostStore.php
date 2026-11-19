<?php
namespace DropAndPair\Tests;

use DropAndPair\Import\PostStore;

/** Stand-in for WpPostStore so the importer runs without WordPress. */
class InMemoryPostStore implements PostStore {

	/** @var array<int, array{type:string, slug:string, postarr:array, meta:array, terms:array}> */
	public array $posts = array();
	/** @var string[] "type:slug" for every create() call. */
	public array $creates = array();
	private int $next = 100;

	/** Pre-seeds a post, e.g. one that already exists in another status. */
	public function seed( string $type, string $slug, string $status = 'publish' ): int {
		$id                = $this->next++;
		$this->posts[ $id ] = array(
			'type'    => $type,
			'slug'    => $slug,
			'postarr' => array( 'post_status' => $status ),
			'meta'    => array(),
			'terms'   => array(),
		);
		return $id;
	}

	public function find_by_slug( string $type, string $slug ): ?int {
		foreach ( $this->posts as $id => $post ) {
			if ( $post['type'] === $type && $post['slug'] === $slug ) {
				return $id;
			}
		}
		return null;
	}

	public function create( array $postarr, array $meta, array $terms ): int {
		$id                 = $this->next++;
		$this->creates[]    = $postarr['post_type'] . ':' . $postarr['post_name'];
		$this->posts[ $id ] = array(
			'type'    => $postarr['post_type'],
			'slug'    => $postarr['post_name'],
			'postarr' => $postarr,
			'meta'    => $meta,
			'terms'   => $terms,
		);
		return $id;
	}
}
