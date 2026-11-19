<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * GoatCounter analytics. Only on the live domain, so staging and local visits don't
 * count, and not for logged-in users, so editing the site doesn't either.
 *
 * It lives in the plugin rather than the theme so that switching themes doesn't
 * quietly turn it off.
 */
final class Analytics {

	public const HOST = 'dropandpair.com';

	public static function hooks(): void {
		add_action( 'wp_head', array( self::class, 'print_script' ) );
	}

	/** Pure so it can be unit tested. */
	public static function counts( bool $logged_in, string $host ): bool {
		return ! $logged_in && self::HOST === $host;
	}

	public static function script(): string {
		return '<script data-goatcounter="https://dropandpair.goatcounter.com/count" async src="https://gc.zgo.at/count.js"></script>' . "\n";
	}

	public static function print_script(): void {
		if ( self::counts( is_user_logged_in(), (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			echo self::script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A fixed string.
		}
	}
}
