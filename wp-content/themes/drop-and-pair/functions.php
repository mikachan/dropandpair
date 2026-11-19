<?php
/**
 * Drop and Pair theme setup.
 *
 * Kept small on purpose: the look lives in theme.json and style.css, and the
 * content model lives in the plugin. The theme still renders without the plugin.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block themes don't load style.css on their own. It depends on global-styles so
 * it prints afterwards (hence priority 20, after core registers that handle),
 * which lets the dark mode overrides win over the theme.json presets.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$theme = wp_get_theme();
		wp_enqueue_style(
			'drop-and-pair',
			get_stylesheet_uri(),
			array( 'global-styles' ),
			$theme->get( 'Version' )
		);
	},
	20
);

/**
 * Use the same stylesheet in the editor, so tickets and tables look right there.
 */
add_action(
	'after_setup_theme',
	static function () {
		add_editor_style( 'style.css' );
	}
);

/**
 * The coaster favicon. Skipped if a Site Icon has been set in the Customizer,
 * since WordPress prints that one itself.
 */
add_action(
	'wp_head',
	static function () {
		if ( has_site_icon() ) {
			return;
		}
		printf(
			'<link rel="icon" href="%s" type="image/svg+xml" />' . "\n",
			esc_url( get_theme_file_uri( 'assets/favicon.svg' ) )
		);
	}
);

/**
 * GoatCounter analytics. Only on the live domain, so staging and local visits
 * don't count, and not for logged-in users, so editing the site doesn't either.
 */
add_action(
	'wp_head',
	static function () {
		if ( is_user_logged_in() || 'dropandpair.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return;
		}
		echo '<script data-goatcounter="https://dropandpair.goatcounter.com/count" async src="https://gc.zgo.at/count.js"></script>' . "\n";
	}
);
