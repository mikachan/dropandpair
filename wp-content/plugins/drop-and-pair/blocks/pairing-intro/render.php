<?php
/**
 * The pairing's moment as a stub-coloured label, then its description.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$pairing_id  = (int) ( $block->context['postId'] ?? get_the_ID() );
$slug        = Queries::moment_for_pairing( $pairing_id );
$moment      = ( null !== $slug && isset( Vocab::MOMENTS[ $slug ] ) ) ? Vocab::MOMENTS[ $slug ]['label'] : '';
$description = get_post_meta( $pairing_id, 'dp_description', true );
$description = is_string( $description ) ? $description : '';
if ( '' === $moment && '' === $description ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-pairing-intro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( '' !== $moment ) : ?>
		<span class="dp-pairing-intro__moment dp-label" style="<?php echo esc_attr( '--dp-stub: var(--wp--preset--color--stub-' . $slug . ')' ); ?>"><?php echo esc_html( $moment ); ?></span>
	<?php endif; ?>
	<?php if ( '' !== $description ) : ?>
		<p class="dp-pairing-intro__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</div>
