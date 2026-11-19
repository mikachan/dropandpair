<?php
use DropAndPair\Submission\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase {

	/** @var array<string, array{value:mixed, ttl:int}> */
	private array $store = array();

	private function limiter( string $salt = 'salt' ): RateLimiter {
		return new RateLimiter(
			fn( string $key ) => $this->store[ $key ]['value'] ?? false,
			function ( string $key, $value, int $ttl ): void {
				$this->store[ $key ] = array( 'value' => $value, 'ttl' => $ttl );
			},
			$salt
		);
	}

	public function test_allows_five_per_hour_then_blocks(): void {
		$limiter = $this->limiter();
		$now     = 1_700_000_000;
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( $limiter->allow( '203.0.113.7', $now + $i * 60 ), "attempt $i" );
		}
		$this->assertFalse( $limiter->allow( '203.0.113.7', $now + 600 ) );
		$this->assertFalse( $limiter->allow( '203.0.113.7', $now + 3599 ) );
		$this->assertTrue( $limiter->allow( '198.51.100.2', $now + 600 ), 'other addresses have their own count' );
	}

	public function test_window_resets_after_an_hour(): void {
		$limiter = $this->limiter();
		$now     = 1_700_000_000;
		for ( $i = 0; $i < 5; $i++ ) {
			$limiter->allow( '203.0.113.7', $now );
		}
		$this->assertFalse( $limiter->allow( '203.0.113.7', $now + 10 ) );
		$this->assertTrue( $limiter->allow( '203.0.113.7', $now + 3600 ) );
		foreach ( $this->store as $entry ) {
			$this->assertGreaterThan( 0, $entry['ttl'] );
			$this->assertLessThanOrEqual( 3600, $entry['ttl'] );
		}
	}

	public function test_key_never_contains_raw_ip(): void {
		$ip = '203.0.113.7';
		$this->limiter()->allow( $ip, 1_700_000_000 );

		$this->assertCount( 1, $this->store );
		$key = array_key_first( $this->store );
		$this->assertSame( 'dp_rl_' . substr( hash_hmac( 'sha256', $ip, 'salt' ), 0, 20 ), $key );
		$this->assertStringNotContainsString( $ip, $key );
		$this->assertStringNotContainsString( $ip, serialize( $this->store ) );

		$this->limiter( 'other salt' )->allow( $ip, 1_700_000_000 );
		$this->assertCount( 2, $this->store, 'the salt changes the key' );
	}
}
