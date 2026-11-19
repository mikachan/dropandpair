<?php
/**
 * One ticket per published ride that publicly features the current pairing, showing
 * that ride's own blurb and match word.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Tickets;

defined( 'ABSPATH' ) || exit;

$pairing_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$moment     = Queries::moment_for_pairing( $pairing_id );
$entries    = Queries::rides_for_pairing( $pairing_id );
if ( ! $entries ) {
	return;
}
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-pairing-rides' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2>rides it pairs with</h2>
	<div class="dp-ticket-grid">
		<?php foreach ( $entries as $entry ) : ?>
			<?php
			echo Tickets::render( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Tickets.
				array(
					'moment'       => $moment,
					'title'        => get_the_title( $entry['ride'] ),
					'url'          => get_permalink( $entry['ride'] ),
					'blurb'        => $entry['blurb'],
					'strength'     => $entry['strength'],
					'suggested_by' => $entry['suggested_by'],
				)
			);
			?>
		<?php endforeach; ?>
	</div>
</section>
