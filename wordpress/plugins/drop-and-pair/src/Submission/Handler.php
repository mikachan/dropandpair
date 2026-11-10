<?php
namespace DropAndPair\Submission;

use DropAndPair\RidePairings;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

/**
 * Takes a "suggest a pairing" post from admin-post.php and turns it into drafts for Sarah.
 *
 * After an error the form has to come back with the visitor's values and the errors
 * next to each field. admin-post.php isn't a themed page, so rather than render there
 * the values and errors go in a transient under a random token, and the visitor is
 * redirected to /suggest/?dp_form=<token>, where the block reads them back. The
 * transient only lasts long enough to fix a typo.
 */
final class Handler {

	public const ACTION      = 'dp_suggest';
	public const NONCE_FIELD = 'dp_nonce';
	public const PAGE_OPTION = 'dp_suggest_page_id';

	/** Faster than this and it's a bot: nobody reads the form and writes a blurb in under 3 seconds. */
	public const MIN_SECONDS = 3;

	private const FORM_TTL    = 15 * 60;
	private const FORM_PREFIX = 'dp_form_';
	private const FORM_ERROR  = '_form';
	private const MAX_KEPT    = 2000;

	/** Fields the form posts and the error page gives back, in form order. */
	public const FIELDS = array( 'ride_id', 'new_ride_name', 'park_id', 'new_park_name', 'new_park_country', 'title', 'moment', 'blurb', 'link', 'name', 'email' );

	public static function hooks(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_request' ) );
		// Activation doesn't re-run for a plugin that's already active (or symlinked in),
		// so the page is also checked for on every load. It's one cached lookup.
		add_action( 'init', array( self::class, 'ensure_page' ), 30 );
	}

	public static function handle_request(): void {
		self::handle( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in process().
	}

	/** @param array $post Unslashed form fields. */
	public static function handle( array $post ): void {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( self::process( $post, $ip, time() ), 303 );
		exit;
	}

	/**
	 * Does everything except the redirect, so the smoke test can call it.
	 *
	 * @param array $post Unslashed form fields.
	 * @return string Where to send the visitor next.
	 */
	public static function process( array $post, string $ip, int $now ): string {
		$nonce = isset( $post[ self::NONCE_FIELD ] ) && is_string( $post[ self::NONCE_FIELD ] ) ? $post[ self::NONCE_FIELD ] : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			return self::back( $post, array( self::FORM_ERROR => "Sorry, that form had been open a while and I couldn't take it. Could you press send again?" ) );
		}

		// Bots get the thank-you too, so there's nothing to learn from trying again.
		$started = filter_var( $post['started_at'] ?? null, FILTER_VALIDATE_INT );
		$honey   = $post['website_url'] ?? '';
		$filled  = ! is_string( $honey ) || '' !== trim( $honey );
		if ( $filled || false === $started || $now - $started < self::MIN_SECONDS ) {
			return self::thanks_url();
		}

		$limiter = new RateLimiter( 'get_transient', 'set_transient', wp_salt( 'nonce' ) );
		if ( ! $limiter->allow( $ip, $now ) ) {
			return self::back( $post, array( self::FORM_ERROR => "That's a lot of suggestions in one go. Have a breather and try again in a bit." ) );
		}

		$result = Validator::validate( $post, array( self::class, 'is_published_ride' ), array( self::class, 'is_published_park' ) );
		if ( ! $result['ok'] ) {
			return self::back( $post, $result['errors'] );
		}

		$created = self::create( $result['data'] );
		if ( null === $created ) {
			return self::back( $post, array( self::FORM_ERROR => "Something went wrong on my end and your suggestion didn't save. Could you try again in a minute?" ) );
		}
		self::notify( $result['data'], $created );
		return self::thanks_url();
	}

	public static function is_published_ride( int $id ): bool {
		return 'dp_ride' === get_post_type( $id ) && 'publish' === get_post_status( $id );
	}

	public static function is_published_park( int $id ): bool {
		return 'dp_park' === get_post_type( $id ) && 'publish' === get_post_status( $id );
	}

	/**
	 * Creates the drafts: park and ride if they're new, then the pairing, then the link.
	 * If any insert fails, whatever was made so far is deleted again.
	 *
	 * @return array{pairing:int, ride:int, park:?int, new_ride:bool, new_park:bool}|null
	 */
	private static function create( array $data ): ?array {
		$made   = array();
		$insert = static function ( string $type, string $title, array $meta = array() ) use ( &$made ): int {
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'      => $type,
						'post_status'    => 'draft',
						'post_title'     => $title,
						'comment_status' => 'closed',
						'meta_input'     => $meta,
					)
				),
				true
			);
			if ( is_wp_error( $id ) || ! $id ) {
				throw new \RuntimeException( "Could not create $type" );
			}
			$made[] = (int) $id;
			return (int) $id;
		};

		try {
			$park_id = $data['park_id'];
			if ( 0 === $data['ride_id'] && 0 === $park_id ) {
				$park_id = $insert( 'dp_park', $data['new_park_name'], array( 'dp_country' => $data['new_park_country'] ) );
			}
			$ride_id = $data['ride_id'];
			if ( 0 === $ride_id ) {
				$ride_id = $insert( 'dp_ride', $data['new_ride_name'], array( 'dp_park_id' => $park_id ) );
			}
			$pairing_id = $insert( 'dp_pairing', $data['title'], null === $data['link'] ? array() : array( 'dp_url' => $data['link'] ) );
			// tax_input needs a logged-in user who can assign terms; visitors aren't one.
			$terms = wp_set_object_terms( $pairing_id, $data['moment'], 'dp_moment' );
			if ( is_wp_error( $terms ) ) {
				throw new \RuntimeException( 'Could not set the moment' );
			}

			$links   = get_post_meta( $ride_id, 'dp_pairings', true );
			$links   = is_array( $links ) ? $links : array();
			$links[] = array(
				'pairing_id'      => $pairing_id,
				'blurb'           => $data['blurb'],
				'strength'        => null,
				'suggested_by'    => $data['name'],
				'submitter_email' => $data['email'],
			);
			update_post_meta( $ride_id, 'dp_pairings', wp_slash( RidePairings::normalise( $links ) ) );
		} catch ( \RuntimeException $e ) {
			foreach ( $made as $id ) {
				wp_delete_post( $id, true );
			}
			return null;
		}

		return array(
			'pairing'  => $pairing_id,
			'ride'     => $ride_id,
			'park'     => $park_id,
			'new_ride' => 0 === $data['ride_id'],
			'new_park' => 0 === $data['ride_id'] && 0 === $data['park_id'],
		);
	}

	/** Plain-text email to Sarah with what was sent and where to edit it. A failed send doesn't fail the suggestion. */
	private static function notify( array $data, array $created ): void {
		$ride  = $created['new_ride'] ? $data['new_ride_name'] : get_post_field( 'post_title', $created['ride'], 'raw' );
		$edit  = static fn( int $id ): string => admin_url( 'post.php?post=' . $id . '&action=edit' );
		$lines = array( "Someone's suggested a pairing. It's all saved as drafts for you to look over.", '' );

		if ( $created['new_ride'] ) {
			$park    = $created['new_park']
				? $data['new_park_name'] . ', ' . $data['new_park_country'] . ' (new park)'
				: get_post_field( 'post_title', (int) $created['park'], 'raw' );
			$lines[] = 'Ride: ' . $ride . ' (new ride, at ' . $park . ')';
		} else {
			$lines[] = 'Ride: ' . $ride;
		}
		$lines[] = 'Pairing: ' . $data['title'];
		$lines[] = 'Moment: ' . Vocab::MOMENTS[ $data['moment'] ]['label'];
		$lines[] = 'Why it works: ' . $data['blurb'];
		$lines[] = 'Link: ' . ( $data['link'] ?? '(none)' );
		$lines[] = 'Suggested by: ' . $data['name'];
		$lines[] = 'Email: ' . ( $data['email'] ?? '(not given)' );
		$lines[] = '';
		$lines[] = 'Edit the pairing: ' . $edit( $created['pairing'] );
		$lines[] = 'Edit the ride: ' . $edit( $created['ride'] );
		if ( $created['new_park'] ) {
			$lines[] = 'Edit the park: ' . $edit( (int) $created['park'] );
		}
		$lines[] = '';
		$lines[] = "The link on the ride has no strength yet, so it won't show until you give it one and publish the pairing.";

		// Address only: a name with a comma in it would be read as two addresses.
		$headers = null === $data['email'] ? array() : array( 'Reply-To: ' . $data['email'] );
		wp_mail( get_option( 'admin_email' ), 'New pairing suggestion: ' . $data['title'] . ' for ' . $ride, implode( "\n", $lines ), $headers );
	}

	/**
	 * Saves what the visitor typed plus the errors, and returns the form URL that shows them.
	 * The honeypot and nonce aren't kept. Escaping happens when the block prints them.
	 *
	 * @param array<string,string> $errors
	 */
	private static function back( array $post, array $errors ): string {
		$values = array();
		foreach ( self::FIELDS as $field ) {
			$value            = $post[ $field ] ?? '';
			$values[ $field ] = is_string( $value ) ? mb_substr( $value, 0, self::MAX_KEPT ) : '';
		}
		$token = wp_generate_password( 20, false );
		set_transient( self::FORM_PREFIX . $token, array( 'values' => $values, 'errors' => $errors ), self::FORM_TTL );
		return add_query_arg( 'dp_form', $token, self::page_url() );
	}

	/**
	 * The values and errors saved by back(), or null if the token's unknown or expired.
	 *
	 * @return array{values:array<string,string>, errors:array<string,string>, form_error:?string}|null
	 */
	public static function saved_form( string $token ): ?array {
		if ( ! preg_match( '/^[A-Za-z0-9]{20}$/', $token ) ) {
			return null;
		}
		$saved = get_transient( self::FORM_PREFIX . $token );
		if ( ! is_array( $saved ) || ! is_array( $saved['values'] ?? null ) || ! is_array( $saved['errors'] ?? null ) ) {
			return null;
		}
		$errors = $saved['errors'];
		$form   = $errors[ self::FORM_ERROR ] ?? null;
		unset( $errors[ self::FORM_ERROR ] );
		return array(
			'values'     => $saved['values'],
			'errors'     => $errors,
			'form_error' => $form,
		);
	}

	public static function page_url(): string {
		$id  = (int) get_option( self::PAGE_OPTION );
		$url = $id ? get_permalink( $id ) : false;
		return $url ? $url : home_url( '/suggest/' );
	}

	public static function thanks_url(): string {
		return add_query_arg( 'thanks', '1', self::page_url() );
	}

	/**
	 * Makes sure the /suggest/ page exists, holding the form block. Idempotent: an existing
	 * page (even a trashed one, so a deliberate trashing sticks) is adopted rather than
	 * duplicated. It only comes back if it's been permanently deleted.
	 */
	public static function ensure_page(): void {
		$id = (int) get_option( self::PAGE_OPTION );
		if ( $id && get_post( $id ) instanceof \WP_Post ) {
			return;
		}
		$page = get_page_by_path( 'suggest', OBJECT, 'page' );
		if ( $page instanceof \WP_Post ) {
			$id = $page->ID;
		} else {
			$id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_name'      => 'suggest',
					'post_title'     => 'Suggest a pairing',
					'post_content'   => '<!-- wp:drop-and-pair/suggest-form /-->',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);
			if ( is_wp_error( $id ) || ! $id ) {
				return;
			}
		}
		update_option( self::PAGE_OPTION, (int) $id );
	}
}
