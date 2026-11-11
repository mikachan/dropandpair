<?php
namespace DropAndPair\Submission;

use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a "suggest a pairing" form post and turns it into clean data or per-field errors.
 *
 * Pure (no WordPress calls) so PHPUnit can run it: the caller says which published
 * ride or park, if any, the visitor's text names. Every text field has anything that looks like a tag stripped
 * (but not a stray "<", so "I <3 this" survives) and its whitespace collapsed, so a
 * newline can't sneak into an email subject. Escaping still happens
 * on output; this is about storing what the visitor meant, not making it HTML-safe.
 */
final class Validator {

	public const MAX_NAME    = 80;
	public const MAX_TITLE   = 120;
	public const MIN_BLURB   = 10;
	public const MAX_BLURB   = 600;
	public const MAX_PLACE   = 120;
	public const MAX_COUNTRY = 60;
	public const MAX_LINK    = 2000;

	/**
	 * @param array    $input     Unslashed form fields.
	 * @param callable $find_ride fn( string $typed ): ?int, the published ride it names.
	 * @param callable $find_park fn( string $typed ): ?int, the published park it names.
	 * @return array{ok:bool, data:array, errors:array<string,string>}
	 */
	public static function validate( array $input, callable $find_ride, callable $find_park ): array {
		$errors = array();
		$data   = array(
			'ride_id'          => null,
			'new_ride_name'    => null,
			'park_id'          => null,
			'new_park_name'    => null,
			'new_park_country' => null,
		);

		// A ride that's already on the site is linked to; anything else is a new one.
		$ride = self::text( $input['ride'] ?? null );
		if ( '' === $ride ) {
			$errors['ride'] = 'Tell me which ride this goes with.';
		} else {
			$ride_id = $find_ride( $ride );
			if ( is_int( $ride_id ) && $ride_id > 0 ) {
				$data['ride_id'] = $ride_id;
			} else {
				$data['ride_id']       = 0;
				$data['new_ride_name'] = self::place( $ride, 'ride', $errors );
			}
		}

		// The park and country only count for a new ride. With JS off they're always
		// on screen, so anything typed there for a known ride is ignored.
		if ( 0 === $data['ride_id'] ) {
			$park = self::text( $input['park'] ?? null );
			if ( '' === $park ) {
				$errors['park'] = 'Which park is it at?';
			} else {
				$park_id = $find_park( $park );
				if ( is_int( $park_id ) && $park_id > 0 ) {
					$data['park_id'] = $park_id;
				} else {
					$data['park_id']          = 0;
					$data['new_park_name']    = self::place( $park, 'park', $errors );
					$data['new_park_country'] = self::required( $input, 'new_park_country', self::MAX_COUNTRY, 'Which country is the park in?', $errors );
				}
			}
		}

		$data['title'] = self::required( $input, 'title', self::MAX_TITLE, "What's the pairing? A film, a song, something to eat, anything.", $errors );

		$moment = self::text( $input['moment'] ?? null );
		if ( isset( Vocab::MOMENTS[ $moment ] ) ) {
			$data['moment'] = $moment;
		} else {
			$data['moment']   = null;
			$errors['moment'] = 'Pick a moment, so I know when it goes.';
		}

		$blurb = self::text( $input['blurb'] ?? null );
		if ( mb_strlen( $blurb ) < self::MIN_BLURB ) {
			$errors['blurb'] = "Tell me why it works with this ride. A sentence or two is plenty.";
		} elseif ( mb_strlen( $blurb ) > self::MAX_BLURB ) {
			$errors['blurb'] = sprintf( "That's a lot! Could you get it down to %d characters?", self::MAX_BLURB );
		}
		$data['blurb'] = '' === $blurb ? null : $blurb;

		$link = self::text( $input['link'] ?? null );
		if ( mb_strlen( $link ) > self::MAX_LINK ) {
			$errors['link'] = sprintf( "That link's really long. Could you find a shorter one? Up to %d characters is fine.", self::MAX_LINK );
		} elseif ( '' !== $link && ! self::is_http_url( $link ) ) {
			$errors['link'] = "That link doesn't look right. It needs to start with http:// or https://.";
		}
		$data['link'] = '' === $link ? null : $link;

		$data['name'] = self::required( $input, 'name', self::MAX_NAME, "What should I call you? Your name goes next to the pairing.", $errors );

		$email = self::text( $input['email'] ?? null );
		if ( '' !== $email && false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$errors['email'] = "That email address doesn't look right. It's optional, so you can leave it blank.";
		}
		$data['email'] = '' === $email ? null : $email;

		return array(
			'ok'     => ! $errors,
			'data'   => $data,
			'errors' => $errors,
		);
	}

	/** A required text field: its clean value, or null with an error added. */
	private static function required( array $input, string $field, int $max, string $missing, array &$errors ): ?string {
		$value = self::text( $input[ $field ] ?? null );
		if ( '' === $value ) {
			$errors[ $field ] = $missing;
		} elseif ( mb_strlen( $value ) > $max ) {
			$errors[ $field ] = sprintf( "That's a bit long. Could you keep it under %d characters?", $max );
		}
		return '' === $value ? null : $value;
	}

	/** A new ride or park name, already cleaned and known not to be empty, with an error if it's too long. */
	private static function place( string $name, string $field, array &$errors ): string {
		if ( mb_strlen( $name ) > self::MAX_PLACE ) {
			$errors[ $field ] = sprintf( "That's a bit long. Could you keep it under %d characters?", self::MAX_PLACE );
		}
		return $name;
	}

	/** Tags stripped, whitespace collapsed; anything that isn't a string or number is empty. */
	private static function text( $value ): string {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return '';
		}
		return trim( (string) preg_replace( '/\s+/u', ' ', self::strip_tags( (string) $value ) ) );
	}

	/**
	 * Removes what looks like markup. strip_tags() treats any "<" as the start of a tag,
	 * so "Kids <3 it" became "Kids ". Here a tag has to start with a letter, "/" or "!".
	 * Script and style blocks go with their contents, as wp_strip_all_tags() does.
	 */
	private static function strip_tags( string $value ): string {
		$value = (string) preg_replace( '@<(script|style)\b[^>]*>.*?</\1\s*>@is', '', $value );
		$value = (string) preg_replace( '@<!--.*?-->@s', '', $value );
		return (string) preg_replace( '@<[a-z/!][^>]*>@i', '', $value );
	}

	private static function is_http_url( string $value ): bool {
		$scheme = strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) && false !== filter_var( $value, FILTER_VALIDATE_URL );
	}
}
