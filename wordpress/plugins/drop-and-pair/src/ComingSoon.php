<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Coming soon mode: while the option is on, everyone who isn't logged in gets a 503 holding
 * page instead of the site, and the REST API, the suggest-a-pairing POST, XML-RPC and ajax
 * refuse them too. It exists so the site can sit on a public staging URL without being public.
 *
 * It fails closed: a missing option counts as on, so the gate is up before anyone has
 * ever touched a setting. Only an explicit "0" lets the public in.
 */
final class ComingSoon {

	public const OPTION = 'dp_coming_soon';

	public const RETRY_AFTER = 86400;

	public static function hooks(): void {
		// Priority 0: ahead of Seo's legacy redirects, which would otherwise answer a logged-out
		// /rss.xml with a 301 and hint that the old URLs are live.
		add_action( 'template_redirect', array( self::class, 'gate_front_end' ), 0 );
		// 101: after core's cookie check (100), which is what drops the user for a missing nonce.
		add_filter( 'rest_authentication_errors', array( self::class, 'gate_rest' ), 101 );
		add_action( 'admin_post_nopriv_' . Submission\Handler::ACTION, array( self::class, 'refuse_submission' ), 0 );
		add_action( 'pre_comment_on_post', array( self::class, 'refuse_submission' ), 0 );
		add_action( 'admin_init', array( self::class, 'gate_ajax' ), 0 );
		add_filter( 'xmlrpc_enabled', static fn( $enabled ) => self::blocks_visitor() ? false : $enabled );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar_note' ), 100 );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_action( 'add_option_' . self::OPTION, static fn( $name, $value ) => self::hide_from_search( $value ), 10, 2 );
		add_action( 'update_option_' . self::OPTION, static fn( $old, $value ) => self::hide_from_search( $value ), 10, 2 );
	}

	/** Pure so it can be unit tested: only an explicit off value opens the site. */
	public static function is_on( $raw ): bool {
		return ! in_array( $raw, array( '0', 0, false ), true );
	}

	public static function enabled(): bool {
		return self::is_on( get_option( self::OPTION, '1' ) );
	}

	/** True when this request should be turned away. */
	public static function blocks_visitor(): bool {
		return self::enabled() && ! is_user_logged_in();
	}

	/** Unchecked checkboxes aren't posted, so null means off. */
	public static function sanitize( $raw ): string {
		return empty( $raw ) || '0' === $raw ? '0' : '1';
	}

	public static function activate(): void {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, '1' );
		}
		if ( self::enabled() ) {
			update_option( 'blog_public', '0' );
		}
	}

	/** Turning the gate on also asks search engines to stay away, in case it gets switched off later by accident. */
	public static function hide_from_search( $value ): void {
		if ( self::is_on( $value ) ) {
			update_option( 'blog_public', '0' );
		}
	}

	/* ----------------------------------------------------------- Gates */

	public static function gate_front_end(): void {
		if ( ! self::blocks_visitor() ) {
			return;
		}
		status_header( 503 );
		nocache_headers();
		header( 'Retry-After: ' . self::RETRY_AFTER );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo self::page_html( get_theme_root_uri() . '/drop-and-pair/assets/favicon.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup; the one URL is escaped inside.
		exit;
	}

	/**
	 * Every REST route, not only the ones that would leak content: with the site closed there is
	 * nothing a visitor needs from the API, and a short deny-list is how things slip through.
	 * Logged-in users, cookie or application password, are untouched so the block editor works.
	 */
	public static function gate_rest( $result ) {
		if ( null !== $result && true !== $result ) {
			return $result;
		}
		if ( self::blocks_visitor() ) {
			return new \WP_Error( 'dp_coming_soon', 'This site is not open yet.', array( 'status' => 401 ) );
		}
		return $result;
	}

	/** Hooked on the logged-out admin-post action for the pairing form, and on comment posting. */
	public static function refuse_submission(): void {
		if ( self::enabled() ) {
			wp_die( 'This site is not open yet.', 'Coming soon', array( 'response' => 503 ) );
		}
	}

	/** Logged-out admin-ajax calls (the nopriv actions). */
	public static function gate_ajax(): void {
		if ( wp_doing_ajax() && self::blocks_visitor() ) {
			wp_die( 'This site is not open yet.', 'Coming soon', array( 'response' => 503 ) );
		}
	}

	public static function admin_bar_note( \WP_Admin_Bar $bar ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'dp-coming-soon',
				'title' => 'Coming soon mode is on',
				'href'  => admin_url( 'options-reading.php#dp_coming_soon' ),
			)
		);
	}

	/* -------------------------------------------------------- Settings */

	public static function register_setting(): void {
		register_setting(
			'reading',
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => '1',
			)
		);
		add_settings_field(
			self::OPTION,
			'Coming soon',
			array( self::class, 'render_field' ),
			'reading'
		);
	}

	public static function render_field(): void {
		?>
		<label for="dp_coming_soon">
			<input type="checkbox" name="dp_coming_soon" id="dp_coming_soon" value="1" <?php checked( self::enabled() ); ?> />
			Show a coming soon page to visitors who aren't logged in
		</label>
		<p class="description">While this is on, the site, feeds, sitemaps and the suggest form are closed to the public, and search engines are asked to stay away.</p>
		<?php
	}

	/* ------------------------------------------------------------ Page */

	/**
	 * Self-contained on purpose (inline styles, wordmark copied from parts/header.html), so it
	 * still renders when the theme isn't active. Palette and dark mode follow the theme.
	 */
	public static function page_html( string $favicon_url ): string {
		$favicon = htmlspecialchars( $favicon_url, ENT_QUOTES, 'UTF-8' );
		return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Drop and Pair: coming soon</title>
<link rel="icon" href="{$favicon}" type="image/svg+xml">
<style>
:root { color-scheme: light dark; --paper: #f6f4ee; --ink: #1a1a1a; --muted: #5f5c55; --accent: #e8563f; }
@media (prefers-color-scheme: dark) { :root { --paper: #1d1b2b; --ink: #f6f4ee; --muted: #b4b0c4; } }
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px clamp(16px, 4vw, 34px); background: var(--paper); color: var(--ink); font: 16px/1.55 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
main { width: 100%; max-width: 640px; }
.dp-wordmark__svg { display: block; width: auto; height: 96px; overflow: visible; }
.dp-wordmark__text { font-size: 30px; font-weight: 800; }
.dp-wordmark__amp { opacity: .55; }
.dp-wordmark__track { fill: none; stroke: var(--accent); stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
h1 { margin: 40px 0 8px; font-size: 34px; font-weight: 800; line-height: 1.15; letter-spacing: -.01em; }
p { margin: 0; font-size: 20px; color: var(--muted); }
@media (max-width: 600px) { .dp-wordmark__svg { height: 72px; } h1 { font-size: 28px; } p { font-size: 18px; } }
</style>
</head>
<body>
<main>
<svg class="dp-wordmark__svg" viewBox="0 -2 150 70" role="img" aria-labelledby="dp-wordmark-title"><title id="dp-wordmark-title">drop &amp; pair</title><g class="dp-wordmark__text" fill="currentColor"><text x="0" y="58" textLength="64" lengthAdjust="spacingAndGlyphs">drop</text><text class="dp-wordmark__amp" x="71" y="58" textLength="21" lengthAdjust="spacingAndGlyphs">&amp;</text><text x="98" y="58" textLength="52" lengthAdjust="spacingAndGlyphs">pair</text></g><path class="dp-wordmark__track" d="M1.3 31H4Q6.5 31 8.6 29.7L37 11.3Q45.5 5.8 52.5 12.6Q55.5 15.6 63 26.2Q67 31.5 75 31.5H148.7"/><g class="dp-wordmark__car" fill="currentColor"><rect x="40.1" y="1.4" width="10" height="4.6" rx="1.6"/><circle cx="42.4" cy="6.5" r="1.15"/><circle cx="47.8" cy="6.5" r="1.15"/></g></svg>
<h1>Coming soon.</h1>
<p>Pairings for theme park rides, from the queue to the drive home.</p>
</main>
</body>
</html>
HTML;
	}
}
