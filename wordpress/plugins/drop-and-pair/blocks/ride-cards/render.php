<?php
/**
 * Rides as small tickets: name, park and ride type. "featured" falls back to the newest rides
 * when none are flagged, so the home page is never empty while rides exist.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Tickets;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$source = 'newest' === ( $attributes['source'] ?? '' ) ? 'newest' : 'featured';
$count  = max( 1, min( 24, (int) ( $attributes['count'] ?? 6 ) ) );
$rides  = Queries::home_rides( $source, $count );
if ( ! $rides ) {
	echo '<p>No rides here yet.</p>';
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-ride-cards dp-ticket-grid' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php
	foreach ( $rides as $ride ) {
		$park  = Queries::park_for_ride( $ride->ID );
		$types = get_the_terms( $ride->ID, 'dp_ride_type' );
		$type  = is_array( $types ) && $types ? ( Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name ) : '';
		echo Tickets::render( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Tickets.
			array(
				'moment'       => null,
				'admit'        => $type,
				'stub'         => 'Ride',
				'meta'         => $park ? get_the_title( $park ) : '',
				'title'        => get_the_title( $ride ),
				'url'          => get_permalink( $ride ),
				'blurb'        => '',
				'strength'     => null,
				'suggested_by' => null,
			)
		);
	}
	?>
</div>
