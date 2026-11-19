<?php
namespace DropAndPair\Import;

defined( 'ABSPATH' ) || exit;

/** Reads from a real store but never writes, so `--dry-run` reports what would happen. */
final class DryRunStore implements PostStore {

	private PostStore $real;
	/** @var array<string, int> */
	private array $pretend = array();
	private int $next = -1;

	public function __construct( PostStore $real ) {
		$this->real = $real;
	}

	public function find_by_slug( string $type, string $slug ): ?int {
		return $this->pretend[ "$type:$slug" ] ?? $this->real->find_by_slug( $type, $slug );
	}

	public function create( array $postarr, array $meta, array $terms ): int {
		// Negative IDs can't collide with real ones.
		$id = $this->next--;
		$this->pretend[ $postarr['post_type'] . ':' . $postarr['post_name'] ] = $id;
		return $id;
	}
}
