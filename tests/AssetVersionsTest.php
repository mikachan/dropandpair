<?php
use DropAndPair\AssetVersions;
use PHPUnit\Framework\TestCase;

final class AssetVersionsTest extends TestCase {

	private const ROOTS = array(
		'https://example.com/wp-content/plugins/drop-and-pair/' => '/srv/plugins/drop-and-pair/',
		'https://example.com/wp-content/themes/drop-and-pair'   => '/srv/themes/drop-and-pair',
	);

	private function versioned( string $src ): string {
		$hashes = array(
			'/srv/plugins/drop-and-pair/blocks/suggest-form/view.js' => 'abc123',
			'/srv/themes/drop-and-pair/style.css'                    => 'def456',
		);
		return AssetVersions::versioned( $src, self::ROOTS, static fn( string $file ): ?string => $hashes[ $file ] ?? null );
	}

	public function test_our_files_get_a_version_from_their_contents(): void {
		$this->assertSame(
			'https://example.com/wp-content/plugins/drop-and-pair/blocks/suggest-form/view.js?ver=abc123',
			$this->versioned( 'https://example.com/wp-content/plugins/drop-and-pair/blocks/suggest-form/view.js?ver=7.1.4' )
		);
		$this->assertSame(
			'https://example.com/wp-content/themes/drop-and-pair/style.css?ver=def456',
			$this->versioned( 'https://example.com/wp-content/themes/drop-and-pair/style.css' )
		);
	}

	public function test_other_query_args_are_kept(): void {
		$this->assertSame(
			'https://example.com/wp-content/themes/drop-and-pair/style.css?a=1&ver=def456',
			$this->versioned( 'https://example.com/wp-content/themes/drop-and-pair/style.css?a=1&ver=0.1.0' )
		);
	}

	public function test_anything_else_is_left_alone(): void {
		$core = 'https://example.com/wp-includes/js/jquery/jquery.min.js?ver=3.7.1';
		$this->assertSame( $core, $this->versioned( $core ) );
		$missing = 'https://example.com/wp-content/plugins/drop-and-pair/nope.js?ver=7.1.4';
		$this->assertSame( $missing, $this->versioned( $missing ) );
		$escape = 'https://example.com/wp-content/plugins/drop-and-pair/../other/x.js?ver=1';
		$this->assertSame( $escape, $this->versioned( $escape ) );
		// A sibling plugin whose name starts the same way isn't ours.
		$sibling = 'https://example.com/wp-content/plugins/drop-and-pair-extra/x.js?ver=1';
		$this->assertSame( $sibling, $this->versioned( $sibling ) );
	}
}
