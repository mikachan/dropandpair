<?php
/**
 * The ride's further reading links. URLs come from editable meta, so each is escaped
 * with esc_url, which also drops anything that isn't a normal web link.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$ride_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$items   = get_post_meta( $ride_id, 'dp_further_reading', true );
$links   = array();
foreach ( is_array( $items ) ? $items : array() as $item ) {
	$url = is_array( $item ) && is_string( $item['url'] ?? null ) ? esc_url( $item['url'], array( 'http', 'https' ) ) : '';
	if ( '' === $url ) {
		continue;
	}
	$title   = is_string( $item['title'] ?? null ) && '' !== trim( $item['title'] ) ? $item['title'] : $url;
	$links[] = sprintf( '<li><a href="%s" rel="noopener">%s</a></li>', $url, esc_html( $title ) );
}
if ( ! $links ) {
	return;
}
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-further-reading' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2>Further reading</h2>
	<ul>
		<?php echo implode( '', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
	</ul>
</section>
