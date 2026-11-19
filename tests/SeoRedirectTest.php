<?php
use DropAndPair\Seo;
use PHPUnit\Framework\TestCase;

final class SeoRedirectTest extends TestCase {

	public function test_rides_pages_go_back_to_the_one_rides_page(): void {
		$this->assertSame( '/rides/', Seo::redirect_target( '/rides/page/2/' ) );
		$this->assertSame( '/rides/', Seo::redirect_target( '/rides/page/14?type=coaster' ) );
		// A ride could be called "page"; only a page number redirects.
		$this->assertNull( Seo::redirect_target( '/rides/page/' ) );
		$this->assertNull( Seo::redirect_target( '/rides/page-turner/' ) );
		$this->assertNull( Seo::redirect_target( '/rides/' ) );
	}

	public function test_term_archives_go_to_the_filtered_ride_browser(): void {
		$this->assertSame( '/rides/?pairing=eat-and-drink', Seo::redirect_target( '/moment/eat-and-drink/' ) );
		$this->assertSame( '/rides/?pairing=later', Seo::redirect_target( '/moment/later/page/2/' ) );
		$this->assertSame( '/rides/?type=dark-ride', Seo::redirect_target( '/ride-type/dark-ride/' ) );
		$this->assertSame( '/rides/?type=coaster', Seo::redirect_target( '/Ride-Type/Coaster/feed/' ) );
	}

	public function test_unknown_moments_and_ride_types_are_left_to_404(): void {
		$this->assertNull( Seo::redirect_target( '/moment/nonsense/' ) );
		$this->assertNull( Seo::redirect_target( '/ride-type/rollercoaster/' ) );
		$this->assertNull( Seo::redirect_target( '/moment/' ) );
	}

	public function test_vibes_redirect_by_name_through_the_lookup(): void {
		$names = array( 'slow-burn' => 'slow burn', 'eerie' => 'eerie' );
		$look  = static fn( string $slug ): ?string => $names[ $slug ] ?? null;
		$this->assertSame( '/rides/?vibe=slow%20burn', Seo::redirect_target( '/vibe/slow-burn/', $look ) );
		$this->assertSame( '/rides/?vibe=eerie', Seo::redirect_target( '/vibe/eerie', $look ) );
		$this->assertNull( Seo::redirect_target( '/vibe/missing/', $look ) );
		// Without a lookup the slug stands in for the name.
		$this->assertSame( '/rides/?vibe=eerie', Seo::redirect_target( '/vibe/eerie/' ) );
	}

	public function test_targets_stay_on_this_site(): void {
		$this->assertSame( '/rides/?vibe=%2F%2Fevil.com', Seo::redirect_target( '/vibe/%2F%2Fevil.com/' ) );
		$this->assertNull( Seo::redirect_target( '//evil.com/moment/later/' ) );
	}
}
