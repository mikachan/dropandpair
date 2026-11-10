<?php
/**
 * Pairing archive: a section per moment that has published pairings, then a ticket each.
 * An archive ticket has no ride, so the blurb is the pairing's own description.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Tickets;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$groups = Queries::pairings_by_moment();
if ( ! $groups ) {
	echo '<p>No pairings yet.</p>';
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-pairings-by-moment' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php foreach ( $groups as $slug => $pairings ) : ?>
		<section class="dp-archive-group" id="<?php echo esc_attr( $slug ); ?>">
			<h2><?php echo esc_html( Vocab::MOMENTS[ $slug ]['label'] ); ?></h2>
			<p class="dp-moment-desc"><?php echo esc_html( Vocab::MOMENTS[ $slug ]['description'] ); ?></p>
			<div class="dp-ticket-grid">
				<?php foreach ( $pairings as $pairing ) : ?>
					<?php
					echo Tickets::render( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Tickets.
						array(
							'moment'       => $slug,
							'title'        => get_the_title( $pairing ),
							'url'          => get_permalink( $pairing ),
							'blurb'        => (string) get_post_meta( $pairing->ID, 'dp_description', true ),
							'strength'     => null,
							'suggested_by' => null,
						)
					);
					?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
