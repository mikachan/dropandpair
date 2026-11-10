<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * One pairing ticket, shared by the ride page, the pairing page and the pairing archive
 * so the markup (and its escaping) lives in one place. Styles are the theme's `.dp-ticket*`.
 */
final class Tickets {

	/**
	 * @param array{moment:?string, title:string, url:string, blurb:string, strength:?int, suggested_by:?string} $t
	 *        `moment` is a Vocab::MOMENTS slug. Plain text in, escaped HTML out.
	 */
	public static function render( array $t ): string {
		$slug   = $t['moment'] ?? null;
		$known  = null !== $slug && isset( Vocab::MOMENTS[ $slug ] );
		$moment = $known ? Vocab::MOMENTS[ $slug ]['label'] : '';
		$style  = $known ? sprintf( ' style="%s"', esc_attr( '--dp-stub: var(--wp--preset--color--stub-' . $slug . ')' ) ) : '';
		$by     = $t['suggested_by'] ?? null;

		$html  = '<div class="dp-ticket"' . $style . '><div class="dp-ticket__main">';
		$html .= '<div class="dp-ticket__admit">Admit one' . ( $moment ? ' · ' . esc_html( $moment ) : '' ) . '</div>';
		$html .= sprintf( '<h3 class="dp-ticket__title"><a href="%s">%s</a></h3>', esc_url( $t['url'] ), esc_html( $t['title'] ) );
		$html .= '<p class="dp-ticket__blurb">' . esc_html( $t['blurb'] ) . '</p>';
		if ( null !== ( $t['strength'] ?? null ) ) {
			$html .= '<div class="dp-ticket__match">' . esc_html( strtolower( Vocab::strength_label( (int) $t['strength'] ) ) ) . '</div>';
		}
		if ( is_string( $by ) && '' !== $by ) {
			$html .= '<div class="dp-label">Suggested by ' . esc_html( $by ) . '</div>';
		}
		$html .= '</div><div class="dp-ticket__stub"><span>' . esc_html( $moment ?: 'Pairing' ) . '</span></div></div>';
		return $html;
	}
}
