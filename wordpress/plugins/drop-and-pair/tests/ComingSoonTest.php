<?php
use DropAndPair\ComingSoon;
use PHPUnit\Framework\TestCase;

final class ComingSoonTest extends TestCase {

	public function test_gate_fails_closed(): void {
		foreach ( array( '1', 1, true, '', null, 'yes' ) as $raw ) {
			$this->assertTrue( ComingSoon::is_on( $raw ), var_export( $raw, true ) );
		}
		foreach ( array( '0', 0, false ) as $raw ) {
			$this->assertFalse( ComingSoon::is_on( $raw ), var_export( $raw, true ) );
		}
	}

	public function test_unchecked_checkbox_saves_off(): void {
		$this->assertSame( '0', ComingSoon::sanitize( null ) );
		$this->assertSame( '0', ComingSoon::sanitize( '0' ) );
		$this->assertSame( '1', ComingSoon::sanitize( '1' ) );
	}

	public function test_page_has_wordmark_copy_and_escaped_favicon(): void {
		$html = ComingSoon::page_html( 'https://x.test/a.svg?a=1&b="2"' );
		$this->assertStringContainsString( 'Coming soon.', $html );
		$this->assertStringContainsString( 'Pairings for theme park rides, from the queue to the drive home.', $html );
		$this->assertStringContainsString( 'dp-wordmark__svg', $html );
		$this->assertStringContainsString( 'href="https://x.test/a.svg?a=1&amp;b=&quot;2&quot;"', $html );
		$this->assertStringContainsString( 'prefers-color-scheme: dark', $html );
	}
}
