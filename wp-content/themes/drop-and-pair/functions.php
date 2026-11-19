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
 * Marks the menu link for the page being viewed.
 *
 * The menu uses plain links so it works on any domain, which means core can't
 * tell which one is current. Compare paths instead: the rides, parks and
 * pairings archives (and their later pages), and pages such as /suggest/.
 */
add_filter(
	'render_block_core/navigation-link',
	static function ( string $block_content, array $block ): string {
		if ( is_front_page() ) {
			$link = home_url( '/' );
		} elseif ( is_page() ) {
			$link = get_permalink();
		} elseif ( is_post_type_archive() && get_queried_object() instanceof WP_Post_Type ) {
			$link = get_post_type_archive_link( get_queried_object()->name );
		} else {
			return $block_content;
		}

		$url = $block['attrs']['url'] ?? '';
		if ( ! is_string( $link ) || '' === $url ) {
			return $block_content;
		}

		$path    = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$current = trailingslashit( (string) wp_parse_url( $link, PHP_URL_PATH ) );
		if ( $path !== $current ) {
			return $block_content;
		}

		$tags = new WP_HTML_Tag_Processor( $block_content );
		if ( $tags->next_tag( 'li' ) ) {
			$tags->add_class( 'current-menu-item' );
		}
		if ( $tags->next_tag( 'a' ) ) {
			$tags->set_attribute( 'aria-current', 'page' );
		}
		return $tags->get_updated_html();
	},
	10,
	2
);
