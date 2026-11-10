<?php
namespace DropAndPair\Submission;

use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a "suggest a pairing" form post and turns it into clean data or per-field errors.
 *
 * Pure (no WordPress calls) so PHPUnit can run it: the caller says what counts as a
 * published ride or park. Every text field has its tags stripped and its whitespace
 * collapsed, so a newline can't sneak into an email subject. Escaping still happens
 * on output; this is about storing what the visitor meant, not making it HTML-safe.
 */
final class Validator {

	public const MAX_NAME    = 80;
	public const MAX_TITLE   = 120;
	public const MIN_BLURB   = 10;
	public const MAX_BLURB   = 600;
	public const MAX_PLACE   = 120;
	public const MAX_COUNTRY = 60;

	/**
	 * @param array    $input             Unslashed form fields.
	 * @param callable $is_published_ride fn( int $id ): bool
	 * @param callable $is_published_park fn( int $id ): bool
	 * @return array{ok:bool, data:array, errors:array<string,string>}
	 */
	public static function validate( array $input, callable $is_published_ride, callable $is_published_park ): array {
		$errors = array();
		$data   = array(
			'ride_id'          => null,
			'new_ride_name'    => null,
			'park_id'          => null,
			'new_park_name'    => null,
			'new_park_country' => null,
		);

		$ride_raw = self::text( $input['ride_id'] ?? null );
		$ride_id  = self::id( $ride_raw );
		if ( '' === $ride_raw ) {
			$errors['ride_id'] = 'Tell me which ride this goes with.';
		} elseif ( null === $ride_id || ( 0 !== $ride_id && ! $is_published_ride( $ride_id ) ) ) {
			$errors['ride_id'] = "I can't find that ride. Pick one from the list, or choose \"It's not on here yet\".";
		} else {
			$data['ride_id'] = $ride_id;
		}

		// The ride and park extras only count when the visitor said the ride isn't listed.
		// With JS off they're always on screen, so anything typed there otherwise is ignored.
		if ( 0 === $ride_id ) {
			$data['new_ride_name'] = self::required( $input, 'new_ride_name', self::MAX_PLACE, "What's the ride called?", $errors );

			$park_raw = self::text( $input['park_id'] ?? null );
			$park_id  = self::id( $park_raw );
			if ( '' === $park_raw ) {
				$errors['park_id'] = 'Which park is it at?';
			} elseif ( null === $park_id || ( 0 !== $park_id && ! $is_published_park( $park_id ) ) ) {
				$errors['park_id'] = "I can't find that park. Pick one from the list, or choose \"It's a new park\".";
			} else {
				$data['park_id'] = $park_id;
			}

			if ( 0 === $park_id ) {
				$data['new_park_name']    = self::required( $input, 'new_park_name', self::MAX_PLACE, "What's the park called?", $errors );
				$data['new_park_country'] = self::required( $input, 'new_park_country', self::MAX_COUNTRY, 'Which country is the park in?', $errors );
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
		if ( '' !== $link && ! self::is_http_url( $link ) ) {
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

	/** Tags stripped, whitespace collapsed; anything that isn't a string or number is empty. */
	private static function text( $value ): string {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return '';
		}
		$value = strip_tags( (string) $value );
		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/** A non-negative whole number, or null. 0 means "it's not on here yet". */
	private static function id( string $value ): ?int {
		$n = filter_var( $value, FILTER_VALIDATE_INT );
		return ( false !== $n && $n >= 0 ) ? $n : null;
	}

	private static function is_http_url( string $value ): bool {
		$scheme = strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) && false !== filter_var( $value, FILTER_VALIDATE_URL );
	}
}
