<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post types, taxonomies and meta, and seeds the fixed terms.
 */
final class ContentTypes {

	private const SEED_OPTION = 'dp_terms_seeded';

	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
		// After register(), so the taxonomies exist when terms are inserted.
		add_action( 'init', array( self::class, 'maybe_seed_terms' ), 20 );
	}

	public static function register(): void {
		self::register_post_types();
		self::register_taxonomies();
		self::register_meta();
	}

	private static function register_post_types(): void {
		$types = array(
			'dp_park'    => array( 'Parks', 'Park', 'parks' ),
			'dp_ride'    => array( 'Rides', 'Ride', 'rides' ),
			'dp_pairing' => array( 'Pairings', 'Pairing', 'pairings' ),
		);
		foreach ( $types as $type => list( $plural, $singular, $slug ) ) {
			register_post_type(
				$type,
				array(
					'labels'       => array(
						'name'          => $plural,
						'singular_name' => $singular,
					),
					'public'       => true,
					'show_in_rest' => true,
					'has_archive'  => true,
					'rewrite'      => array( 'slug' => $slug ),
					// No excerpt: ride "gist" and pairing "description" are meta fields.
					'supports'     => array( 'title', 'editor', 'custom-fields' ),
					'menu_icon'    => 'dashicons-star-filled',
				)
			);
		}
	}

	private static function register_taxonomies(): void {
		// Ride types and moments are fixed vocabularies seeded from Vocab, so editors
		// can assign them but not add, rename or delete them.
		$fixed_caps = array(
			'manage_terms' => 'do_not_allow',
			'edit_terms'   => 'do_not_allow',
			'delete_terms' => 'do_not_allow',
			'assign_terms' => 'edit_posts',
		);
		$fixed      = array(
			'public'            => true,
			'show_in_rest'      => true,
			'show_in_menu'      => false,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'capabilities'      => $fixed_caps,
		);

		register_taxonomy(
			'dp_ride_type',
			'dp_ride',
			$fixed + array(
				'labels'  => array(
					'name'          => 'Ride types',
					'singular_name' => 'Ride type',
				),
				'rewrite' => array( 'slug' => 'ride-type' ),
			)
		);
		register_taxonomy(
			'dp_moment',
			'dp_pairing',
			$fixed + array(
				'labels'  => array(
					'name'          => 'Moments',
					'singular_name' => 'Moment',
				),
				'rewrite' => array( 'slug' => 'moment' ),
			)
		);
		// Vibes are free-form tags, so they stay fully editable.
		register_taxonomy(
			'dp_vibe',
			'dp_ride',
			array(
				'labels'            => array(
					'name'          => 'Vibes',
					'singular_name' => 'Vibe',
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'vibe' ),
			)
		);
	}

	private static function register_meta(): void {
		$text = array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
		);
		$reg  = static function ( string $post_type, string $key, array $args ): void {
			register_post_meta( $post_type, 'dp_' . $key, $args + array( 'single' => true, 'show_in_rest' => true ) );
		};

		foreach ( array( 'country', 'region', 'website' ) as $key ) {
			$reg( 'dp_park', $key, $text );
		}

		foreach ( array( 'park_id', 'opened', 'inversions' ) as $key ) {
			$reg( 'dp_ride', $key, array( 'type' => 'integer' ) );
		}
		foreach ( array( 'manufacturer', 'added', 'gist' ) as $key ) {
			$reg( 'dp_ride', $key, $text );
		}
		foreach ( array( 'height', 'speed', 'length' ) as $key ) {
			$reg( 'dp_ride', $key, array( 'type' => 'number' ) );
		}
		// Intensity and roughness are 1 to 5; the REST schema enforces the range.
		foreach ( array( 'intensity', 'roughness' ) as $key ) {
			$reg(
				'dp_ride',
				$key,
				array(
					'type'         => 'integer',
					'show_in_rest' => array( 'schema' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ) ),
				)
			);
		}
		$reg( 'dp_ride', 'featured', array( 'type' => 'boolean' ) );
		$reg(
			'dp_ride',
			'further_reading',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'title' => array( 'type' => 'string' ),
								'url'   => array( 'type' => 'string' ),
							),
						),
					),
				),
			)
		);
		// Registered now so the shape is fixed; Task 3 adds the logic around it.
		$reg(
			'dp_ride',
			'pairings',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema'           => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'pairing_id'      => array( 'type' => 'integer' ),
								'blurb'           => array( 'type' => 'string' ),
								'strength'        => array( 'type' => array( 'integer', 'null' ) ),
								'suggested_by'    => array( 'type' => array( 'string', 'null' ) ),
								'submitter_email' => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					),
					'prepare_callback' => array( self::class, 'prepare_pairings_for_rest' ),
				),
			)
		);

		foreach ( array( 'description', 'url', 'image' ) as $key ) {
			$reg( 'dp_pairing', $key, $text );
		}
	}

	/**
	 * Visitors suggest pairings with an email address; it must never be public.
	 * Anyone who can't edit posts gets the links without `submitter_email`.
	 *
	 * @param mixed $value Stored meta value.
	 * @return mixed
	 */
	public static function prepare_pairings_for_rest( $value ) {
		if ( ! is_array( $value ) || current_user_can( 'edit_posts' ) ) {
			return $value;
		}
		return array_map(
			static function ( $link ) {
				if ( is_array( $link ) ) {
					unset( $link['submitter_email'] );
				}
				return $link;
			},
			$value
		);
	}

	/** Seeds once per vocabulary version, so it also covers an already-active plugin. */
	public static function maybe_seed_terms(): void {
		if ( get_option( self::SEED_OPTION ) === DROP_AND_PAIR_VERSION . ':' . self::vocab_hash() ) {
			return;
		}
		self::seed_terms();
	}

	/** Idempotent: existing terms are left alone, missing ones are inserted in order. */
	public static function seed_terms(): void {
		// On activation, init hasn't registered the taxonomies yet.
		if ( ! taxonomy_exists( 'dp_moment' ) ) {
			self::register();
		}
		foreach ( Vocab::RIDE_TYPES as $slug => $label ) {
			self::ensure_term( 'dp_ride_type', $slug, $label );
		}
		foreach ( Vocab::MOMENTS as $slug => $moment ) {
			self::ensure_term( 'dp_moment', $slug, $moment['label'], $moment['description'] );
		}
		update_option( self::SEED_OPTION, DROP_AND_PAIR_VERSION . ':' . self::vocab_hash() );
	}

	private static function ensure_term( string $taxonomy, string $slug, string $label, string $description = '' ): void {
		if ( term_exists( $slug, $taxonomy ) ) {
			return;
		}
		wp_insert_term( $label, $taxonomy, array( 'slug' => $slug, 'description' => $description ) );
	}

	private static function vocab_hash(): string {
		return md5( wp_json_encode( array( Vocab::RIDE_TYPES, Vocab::MOMENTS ) ) );
	}
}
