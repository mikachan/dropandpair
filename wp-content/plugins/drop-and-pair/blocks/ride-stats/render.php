<?php
/**
 * "The ride": a table of facts. Rows with no value are left out.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$ride_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$meta    = static function ( string $key ) use ( $ride_id ) {
	$value = get_post_meta( $ride_id, 'dp_' . $key, true );
	return ( '' === $value || null === $value || false === $value ) ? null : $value;
};
/** 1450 => "1,450 m"; 77.5 => "77.5 m". */
$measure = static function ( $value, string $unit ): ?string {
	if ( null === $value || ! is_numeric( $value ) ) {
		return null;
	}
	$number = rtrim( rtrim( number_format( (float) $value, 2 ), '0' ), '.' );
	return $number . ' ' . $unit;
};
/** Five dots, as in the mockup; the dots are decoration, the word carries the meaning. */
$meter = static function ( $value, callable $label ): ?string {
	$n = (int) $value;
	if ( $n < 1 || $n > 5 ) {
		return null;
	}
	return sprintf(
		'<span aria-hidden="true">%s</span> %s',
		str_repeat( '●', $n ) . str_repeat( '○', 5 - $n ),
		esc_html( strtolower( $label( $n ) ) )
	);
};

$inversions = $meta( 'inversions' );
$rows       = array(
	'opened'       => null !== $meta( 'opened' ) ? esc_html( (string) $meta( 'opened' ) ) : null,
	'manufacturer' => null !== $meta( 'manufacturer' ) ? esc_html( (string) $meta( 'manufacturer' ) ) : null,
	'height'       => ( $v = $measure( $meta( 'height' ), 'm' ) ) ? esc_html( $v ) : null,
	'speed'        => ( $v = $measure( $meta( 'speed' ), 'km/h' ) ) ? esc_html( $v ) : null,
	'length'       => ( $v = $measure( $meta( 'length' ), 'm' ) ) ? esc_html( $v ) : null,
	'inversions'   => null !== $inversions ? esc_html( (string) (int) $inversions ) : null,
	'intensity'    => $meter( $meta( 'intensity' ), array( Vocab::class, 'intensity_label' ) ),
	'comfort'      => $meter( $meta( 'roughness' ), array( Vocab::class, 'comfort_label' ) ),
);
$rows       = array_filter( $rows, static fn( $v ) => null !== $v );
if ( ! $rows ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-ride-stats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2 class="dp-ride-stats__title">the ride</h2>
	<table class="dp-table">
		<?php foreach ( $rows as $label => $value ) : ?>
			<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></td></tr>
		<?php endforeach; ?>
	</table>
</div>
