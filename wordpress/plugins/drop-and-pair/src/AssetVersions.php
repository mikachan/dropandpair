<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Versions the plugin's and theme's scripts and styles by their contents.
 *
 * Block assets otherwise carry the WordPress version (?ver=7.1.4) and the theme's
 * stylesheet its unchanging theme version, and Pressable's CDN keeps those URLs for
 * years. So after a deploy, visitors would go on getting the old file. A short hash
 * of the file gives every change a new URL.
 */
final class AssetVersions {

	public static function hooks(): void {
		add_filter( 'script_loader_src', array( self::class, 'filter' ) );
		add_filter( 'style_loader_src', array( self::class, 'filter' ) );
	}

	/** @param string|false $src */
	public static function filter( $src ) {
		if ( ! is_string( $src ) ) {
			return $src;
		}
		$roots = array(
			DROP_AND_PAIR_URL              => DROP_AND_PAIR_DIR,
			get_stylesheet_directory_uri() => get_stylesheet_directory(),
		);
		return self::versioned( $src, $roots, array( self::class, 'hash' ) );
	}

	/**
	 * The URL with `ver` set to the file's hash, when it's one of our files.
	 *
	 * @param array<string, string> $roots Base URL => the directory it serves.
	 * @param callable              $hash  fn( string $file ): ?string, null when there's no such file.
	 */
	public static function versioned( string $src, array $roots, callable $hash ): string {
		$parts = explode( '?', $src, 2 );
		foreach ( $roots as $url => $dir ) {
			$url = rtrim( $url, '/' ) . '/';
			if ( 0 !== strpos( $parts[0], $url ) ) {
				continue;
			}
			$relative = substr( $parts[0], strlen( $url ) );
			if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
				return $src;
			}
			$version = $hash( rtrim( $dir, '/' ) . '/' . $relative );
			if ( null === $version ) {
				return $src;
			}
			parse_str( $parts[1] ?? '', $query );
			$query['ver'] = $version;
			return $parts[0] . '?' . http_build_query( $query );
		}
		return $src;
	}

	public static function hash( string $file ): ?string {
		return is_file( $file ) ? (string) hash_file( 'crc32b', $file ) : null;
	}
}
