<?php
use DropAndPair\Tickets;
use PHPUnit\Framework\TestCase;

/** Tickets::render needs WordPress's escapers; these tests stub them to stay WordPress-free. */
final class TicketsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		foreach ( array( 'esc_html', 'esc_attr', 'esc_url' ) as $fn ) {
			if ( ! function_exists( $fn ) ) {
				eval( "function $fn( \$s ) { return htmlspecialchars( (string) \$s, ENT_QUOTES ); }" );
			}
		}
	}

	private function base(): array {
		return array( 'moment' => null, 'title' => 'Hyperion', 'url' => '/rides/hyperion/', 'blurb' => 'A <b>drop</b>.', 'strength' => null, 'suggested_by' => null );
	}

	public function test_admit_and_stub_overrides_and_meta_line(): void {
		$html = Tickets::render( $this->base() + array( 'admit' => 'Coaster', 'stub' => 'Ride', 'meta' => 'Energylandia' ) );
		$this->assertStringContainsString( 'Admit one · Coaster', $html );
		$this->assertStringContainsString( '<span>Ride</span>', $html );
		$this->assertStringContainsString( '<div class="dp-label">Energylandia</div>', $html );
		$this->assertStringContainsString( 'A &lt;b&gt;drop&lt;/b&gt;.', $html );
	}

	public function test_no_blurb_paragraph_when_there_is_no_blurb(): void {
		$html = Tickets::render( array_merge( $this->base(), array( 'blurb' => '' ) ) );
		$this->assertStringNotContainsString( 'dp-ticket__blurb', $html );
	}

	public function test_defaults_are_unchanged(): void {
		$html = Tickets::render( array_merge( $this->base(), array( 'moment' => 'read' ) ) );
		$this->assertStringContainsString( 'Admit one · Read', $html );
		$this->assertStringContainsString( '<span>Read</span>', $html );
		$this->assertStringNotContainsString( 'dp-label', $html );
	}
}
