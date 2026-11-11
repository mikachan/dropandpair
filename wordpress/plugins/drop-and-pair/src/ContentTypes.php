<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post types, taxonomies and meta, and seeds the fixed terms.
 */
final class ContentTypes {

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
					// No excerpt: a pairing's "description" is a meta field, and a ride's comes from its text.
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
		// No term archives for any of the three: the theme has no taxonomy template, so
		// /moment/<slug>/ and the like rendered an empty page. Filtering lives in the
		// ride browser instead, and Seo redirects the old term URLs there.
		$no_archive = array(
			'publicly_queryable' => false,
			'rewrite'            => false,
		);
		$fixed      = $no_archive + array(
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
				'labels' => array(
					'name'          => 'Ride types',
					'singular_name' => 'Ride type',
				),
			)
		);
		register_taxonomy(
			'dp_moment',
			'dp_pairing',
			$fixed + array(
				'labels' => array(
					'name'          => 'Moments',
					'singular_name' => 'Moment',
				),
			)
		);
		// Vibes are free-form tags, so they stay fully editable.
		register_taxonomy(
			'dp_vibe',
			'dp_ride',
			$no_archive + array(
				'labels'            => array(
					'name'          => 'Vibes',
					'singular_name' => 'Vibe',
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
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
		foreach ( array( 'manufacturer', 'added' ) as $key ) {
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
		// Shape matches RidePairings; that class scrubs emails once a link is public.
		// Every write (editor, REST, importer, form) goes through normalise, so the stored
		// shape never depends on which client sent it.
		$reg(
			'dp_ride',
			'pairings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_pairings' ),
				'show_in_rest'      => array(
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

	/** @param mixed $value Incoming meta value, already unslashed. */
	public static function sanitize_pairings( $value ): array {
		return is_array( $value ) ? RidePairings::normalise( $value ) : array();
	}

	/**
	 * Unreviewed suggestions (draft or trashed pairings, links with no strength yet) and
	 * submitter emails are for Sarah, not the public. Anyone who can't edit this ride gets
	 * only the links a visitor would see on the page, without emails.
	 *
	 * The REST meta API doesn't pass the post to this callback, but the posts controller
	 * sets up the global post before it reads meta. With no ride to check, the answer is
	 * the public view, so a surprise caller can only see less.
	 *
	 * @param mixed $value Stored meta value.
	 * @return mixed
	 */
	public static function prepare_pairings_for_rest( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$ride = get_post();
		if ( $ride instanceof \WP_Post && 'dp_ride' === $ride->post_type && current_user_can( 'edit_post', $ride->ID ) ) {
			return $value;
		}
		return RidePairings::public_links( $value, array( RidePairings::class, 'pairing_status' ) );
	}

	/** Runs on every init so deleted terms come back; there are only 14, so the checks are cheap. */
	public static function maybe_seed_terms(): void {
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
	}

	private static function ensure_term( string $taxonomy, string $slug, string $label, string $description = '' ): void {
		if ( term_exists( $slug, $taxonomy ) ) {
			return;
		}
		wp_insert_term( $label, $taxonomy, array( 'slug' => $slug, 'description' => $description ) );
	}
}
