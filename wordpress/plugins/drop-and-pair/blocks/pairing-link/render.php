<?php
/**
 * "Find it" link, only when the pairing has a web address.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$pairing_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$url        = get_post_meta( $pairing_id, 'dp_url', true );
$url        = is_string( $url ) ? esc_url( $url, array( 'http', 'https' ) ) : '';
if ( '' === $url ) {
	return;
}
printf( '<p %s><a href="%s" rel="noopener">Find it</a></p>', get_block_wrapper_attributes( array( 'class' => 'dp-pairing-link' ) ), $url ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
