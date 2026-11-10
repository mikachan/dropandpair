<?php
/**
 * One ticket per moment, in Vocab order, each linking to the rides archive filtered by it.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Tickets;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-moment-index dp-ticket-grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php
	foreach ( Vocab::MOMENTS as $slug => $moment ) {
		echo Tickets::render( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Tickets.
			array(
				'moment'       => $slug,
				'title'        => $moment['label'],
				'url'          => home_url( '/rides/?pairing=' . rawurlencode( $slug ) ),
				'blurb'        => $moment['description'],
				'strength'     => null,
				'suggested_by' => null,
			)
		);
	}
	?>
</div>
