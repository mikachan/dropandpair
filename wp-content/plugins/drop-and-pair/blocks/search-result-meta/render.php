<?php
/**
 * Type label (Ride, Park, Pairing, Page) plus the ride's park, the pairing's description or the park's location.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$type    = get_post_type( $post_id );
$labels  = array(
	'dp_ride'    => 'Ride',
	'dp_park'    => 'Park',
	'dp_pairing' => 'Pairing',
	'page'       => 'Page',
);
if ( ! isset( $labels[ $type ] ) ) {
	return;
}
if ( 'dp_ride' === $type ) {
	$park    = \DropAndPair\Queries::park_for_ride( $post_id );
	$summary = $park ? get_the_title( $park ) : '';
} elseif ( 'dp_pairing' === $type ) {
	$summary = (string) get_post_meta( $post_id, 'dp_description', true );
} elseif ( 'dp_park' === $type ) {
	$summary = implode( ', ', array_filter( array( get_post_meta( $post_id, 'dp_region', true ), get_post_meta( $post_id, 'dp_country', true ) ) ) );
} else {
	$summary = '';
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-search-result' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="dp-label"><?php echo esc_html( $labels[ $type ] ); ?></div>
	<?php if ( '' !== $summary ) : ?>
		<p class="dp-search-result__summary"><?php echo esc_html( $summary ); ?></p>
	<?php endif; ?>
</div>
