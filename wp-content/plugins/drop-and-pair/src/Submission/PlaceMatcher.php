<?php
namespace DropAndPair\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the ride or park a visitor typed into the suggestion form.
 *
 * The form takes free text with suggestions, so "wilkolak" has to find "Wilkołak":
 * both sides lose their accents, case and extra spaces before they're compared.
 * Pure, so PHPUnit can run it: the caller hands over the labels and the accent
 * stripper (remove_accents() in WordPress).
 */
final class PlaceMatcher {

	/** @var array<string, ?int> Normalised label => post ID, or null when two posts share it. */
	private array $index = array();

	/** @var callable */
	private $strip_accents;

	/**
	 * @param array<int, array{0:string, 1:int}> $labels        [ label, post ID ] pairs. A post can have several labels.
	 * @param callable                           $strip_accents fn( string ): string
	 */
	public function __construct( array $labels, callable $strip_accents ) {
		$this->strip_accents = $strip_accents;
		foreach ( $labels as list( $label, $id ) ) {
			$key = $this->normalise( $label );
			// Two rides can share a name at different parks. Guessing would pin the
			// pairing to the wrong one, so a shared label matches neither.
			$this->index[ $key ] = array_key_exists( $key, $this->index ) && $this->index[ $key ] !== $id ? null : $id;
		}
	}

	/** The post ID the typed text names, or null if it names none (or more than one). */
	public function find( string $typed ): ?int {
		$key = $this->normalise( $typed );
		return '' === $key ? null : ( $this->index[ $key ] ?? null );
	}

	private function normalise( string $text ): string {
		$text = ( $this->strip_accents )( $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $text ) ) );
	}
}
