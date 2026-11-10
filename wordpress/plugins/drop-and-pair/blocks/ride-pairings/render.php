<?php
/**
 * Pairing tickets, ordered by moment (Vocab order) then link order.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$ride_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$links   = Queries::order_by_moment( Queries::public_links_for_ride( $ride_id ) );
$suggest = esc_url( add_query_arg( 'ride', $ride_id, home_url( '/suggest/' ) ) );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-ride-pairings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2>pairings</h2>
	<?php if ( ! $links ) : ?>
		<p>No pairings yet. Got one? <a href="<?php echo $suggest; // phpcs:ignore WordPress.Security.EscapeOutput ?>">Suggest a pairing.</a></p>
	<?php else : ?>
		<div class="dp-ride-pairings__grid">
			<?php
			foreach ( $links as $link ) :
				$slug   = Queries::moment_for_pairing( (int) $link['pairing_id'] );
				$moment = $slug ? ( Vocab::MOMENTS[ $slug ]['label'] ?? '' ) : '';
				$style  = $slug && isset( Vocab::MOMENTS[ $slug ] ) ? sprintf( '--dp-stub: var(--wp--preset--color--stub-%s)', $slug ) : '';
				$by     = $link['suggested_by'] ?? null;
				?>
				<div class="dp-ticket"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
					<div class="dp-ticket__main">
						<div class="dp-ticket__admit">Admit one<?php echo $moment ? ' · ' . esc_html( $moment ) : ''; ?></div>
						<h3 class="dp-ticket__title"><a href="<?php echo esc_url( get_permalink( $link['pairing'] ) ); ?>"><?php echo esc_html( get_the_title( $link['pairing'] ) ); ?></a></h3>
						<p class="dp-ticket__blurb"><?php echo esc_html( $link['blurb'] ); ?></p>
						<div class="dp-ticket__match"><?php echo esc_html( strtolower( Vocab::strength_label( (int) $link['strength'] ) ) ); ?></div>
						<?php if ( is_string( $by ) && '' !== $by ) : ?>
							<div class="dp-label">Suggested by <?php echo esc_html( $by ); ?></div>
						<?php endif; ?>
					</div>
					<div class="dp-ticket__stub"><span><?php echo esc_html( $moment ?: 'Pairing' ); ?></span></div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="dp-ride-pairings__suggest"><a href="<?php echo $suggest; // phpcs:ignore WordPress.Security.EscapeOutput ?>">Suggest a pairing for this ride</a></p>
	<?php endif; ?>
</section>
