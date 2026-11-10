<?php
namespace DropAndPair\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Five suggestions an hour per visitor, counted in a short-lived store (transients in
 * WordPress). The visitor is identified by a salted hash of their IP, so the raw
 * address is never written anywhere.
 *
 * Pure: storage comes in as callables so PHPUnit can run it without WordPress.
 */
final class RateLimiter {

	public const LIMIT  = 5;
	public const WINDOW = 3600;

	/** @var callable fn( string $key ): mixed, false when missing */
	private $get;

	/** @var callable fn( string $key, mixed $value, int $ttl ): void */
	private $set;

	private string $salt;

	public function __construct( callable $get, callable $set, string $salt ) {
		$this->get  = $get;
		$this->set  = $set;
		$this->salt = $salt;
	}

	/** Counts this attempt and says whether it's allowed. Blocked attempts aren't counted. */
	public function allow( string $ip, int $now ): bool {
		$key    = $this->key( $ip );
		$stored = ( $this->get )( $key );
		$start  = is_array( $stored ) ? (int) ( $stored['start'] ?? 0 ) : 0;
		$count  = is_array( $stored ) ? (int) ( $stored['count'] ?? 0 ) : 0;

		// A fixed window from the first attempt: once it's an hour old, start again.
		if ( $now - $start >= self::WINDOW ) {
			$start = $now;
			$count = 0;
		}
		if ( $count >= self::LIMIT ) {
			return false;
		}
		( $this->set )( $key, array( 'start' => $start, 'count' => $count + 1 ), max( 1, self::WINDOW - ( $now - $start ) ) );
		return true;
	}

	private function key( string $ip ): string {
		return 'dp_rl_' . substr( hash_hmac( 'sha256', $ip, $this->salt ), 0, 20 );
	}
}
