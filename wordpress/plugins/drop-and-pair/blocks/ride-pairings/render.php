<?php
/**
 * Pairing tickets, ordered by moment (Vocab order) then link order.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Submission\Handler;
use DropAndPair\Tickets;

defined( 'ABSPATH' ) || exit;

$ride_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$links   = Queries::order_by_moment( Queries::public_links_for_ride( $ride_id ) );
$suggest = esc_url( add_query_arg( 'ride', $ride_id, Handler::page_url() ) );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-ride-pairings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2>pairings</h2>
	<?php if ( ! $links ) : ?>
		<p>No pairings yet. Got one? <a href="<?php echo $suggest; // phpcs:ignore WordPress.Security.EscapeOutput ?>">Suggest a pairing.</a></p>
	<?php else : ?>
		<div class="dp-ticket-grid">
			<?php foreach ( $links as $link ) : ?>
				<?php
				echo Tickets::render( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Tickets.
					array(
						'moment'       => Queries::moment_for_pairing( (int) $link['pairing_id'] ),
						'title'        => get_the_title( $link['pairing'] ),
						'url'          => get_permalink( $link['pairing'] ),
						'blurb'        => (string) $link['blurb'],
						'strength'     => (int) $link['strength'],
						'suggested_by' => $link['suggested_by'] ?? null,
					)
				);
				?>
			<?php endforeach; ?>
		</div>
		<p class="dp-ride-pairings__suggest"><a href="<?php echo $suggest; // phpcs:ignore WordPress.Security.EscapeOutput ?>">Suggest a pairing for this ride</a></p>
	<?php endif; ?>
</section>
