<?php
namespace DropAndPair\Tests;

use DropAndPair\Import\HtmlToBlocks;
use PHPUnit\Framework\TestCase;

final class HtmlToBlocksTest extends TestCase {

	public function test_paragraphs(): void {
		$out = HtmlToBlocks::convert( "<p>One &amp; two.</p>\n<p>Three</p>" );
		$this->assertSame(
			"<!-- wp:paragraph -->\n<p>One &amp; two.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Three</p>\n<!-- /wp:paragraph -->",
			$out
		);
	}

	public function test_inline_markup_and_unicode_survive(): void {
		$out = HtmlToBlocks::convert( '<p>It\'s <em>smooth</em> – <a href="https://x.test/?a=1&amp;b=2">RCDB</a></p>' );
		$this->assertStringContainsString( '<p>It\'s <em>smooth</em> – <a href="https://x.test/?a=1&amp;b=2">RCDB</a></p>', $out );
	}

	public function test_heading_levels(): void {
		$out = HtmlToBlocks::convert( '<h2>Two</h2><h3>Three</h3><h4>Four</h4>' );
		$this->assertStringContainsString( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Two</h2>", $out );
		$this->assertStringContainsString( '<!-- wp:heading {"level":3} -->', $out );
		$this->assertStringContainsString( '<h4 class="wp-block-heading">Four</h4>', $out );
		$this->assertStringContainsString( '<!-- wp:heading {"level":4} -->', $out );
	}

	public function test_lists(): void {
		$out = HtmlToBlocks::convert( '<ul><li>A</li><li>B</li></ul><ol><li>First</li></ol>' );
		$this->assertStringContainsString( "<!-- wp:list -->\n<ul class=\"wp-block-list\">", $out );
		$this->assertSame( 3, substr_count( $out, '<!-- wp:list-item -->' ) );
		$this->assertStringContainsString( '<!-- wp:list {"ordered":true} -->', $out );
		$this->assertStringContainsString( '<ol class="wp-block-list">', $out );
		$this->assertStringContainsString( '<li>First</li>', $out );
	}

	public function test_quote_wraps_paragraphs(): void {
		$out = HtmlToBlocks::convert( '<blockquote><p>Said so.</p></blockquote>' );
		$this->assertStringStartsWith( "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n<!-- wp:paragraph -->", $out );
		$this->assertStringEndsWith( "</blockquote>\n<!-- /wp:quote -->", $out );
	}

	public function test_unknown_element_becomes_html_block(): void {
		$out = HtmlToBlocks::convert( '<dl><dt>Park</dt><dd>Alton</dd></dl>' );
		$this->assertSame( "<!-- wp:html -->\n<dl><dt>Park</dt><dd>Alton</dd></dl>\n<!-- /wp:html -->", $out );
	}

	public function test_round_trips_through_parse_blocks_shape(): void {
		$html = '<p>a</p><h2>b</h2><ul><li>c</li></ul><blockquote><p>d</p></blockquote><hr><p>e</p>';
		$out  = HtmlToBlocks::convert( $html );
		// 6 top-level (the <hr> becomes core/html) + 1 nested list-item + 1 nested quote paragraph.
		$this->assertSame( 8, preg_match_all( '/<!-- wp:/', $out ) );
		$this->assertSame( 8, preg_match_all( '/<!-- \/wp:/', $out ) );
		// Top-level blocks are separated by a blank line.
		$this->assertCount( 6, explode( "\n\n", $out ) );
	}

	public function test_empty_input(): void {
		$this->assertSame( '', HtmlToBlocks::convert( "  \n" ) );
	}
}
