<?php
use DropAndPair\Seo;
use PHPUnit\Framework\TestCase;

final class SeoLeadTest extends TestCase {

	public function test_the_lead_is_the_first_sentence_of_the_text(): void {
		$html = "<!-- wp:paragraph -->\n<p>Hyperion is a huge Intamin mega coaster at Energylandia, open since 2018. The story's a space mission.</p>\n<!-- /wp:paragraph -->";
		$this->assertSame( 'Hyperion is a huge Intamin mega coaster at Energylandia, open since 2018.', Seo::lead( $html ) );
	}

	public function test_entities_are_decoded_and_spacing_tidied(): void {
		$this->assertSame( 'Fish & chips, by the sea!', Seo::lead( "<p>Fish &amp; chips,\n   by the sea! More.</p>" ) );
	}

	public function test_a_single_sentence_or_nothing(): void {
		$this->assertSame( 'Just the one', Seo::lead( '<p>Just the one</p>' ) );
		$this->assertSame( '', Seo::lead( '' ) );
	}

	public function test_a_decimal_point_does_not_end_the_sentence(): void {
		$this->assertSame( 'It hits 180 km/h in 1.56 seconds.', Seo::lead( '<p>It hits 180 km/h in 1.56 seconds. Then it stops.</p>' ) );
	}
}
