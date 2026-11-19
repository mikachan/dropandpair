<?php
/**
 * Published rides at the current park, by name.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$park_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$rides   = Queries::rides_for_park( $park_id );
/** Meter value as a word, or an empty string when unrated. */
$word = static function ( $value, callable $label ): string {
	$n = (int) $value;
	return ( $n >= 1 && $n <= 5 ) ? strtolower( $label( $n ) ) : '';
};
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-park-rides' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<h2>rides</h2>
	<?php if ( ! $rides ) : ?>
		<p>No rides here yet.</p>
	<?php else : ?>
		<?php // Each cell names its column so the row can stack on a phone; see .dp-table--stack. ?>
		<table class="dp-table dp-table--stack dp-park-rides__table">
			<thead>
				<tr><th scope="col">Ride</th><th scope="col">Type</th><th scope="col">Opened</th><th scope="col">Intensity</th><th scope="col">Comfort</th></tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rides as $ride ) :
					$types = get_the_terms( $ride->ID, 'dp_ride_type' );
					$type  = is_array( $types ) && $types ? ( Vocab::RIDE_TYPES[ $types[0]->slug ] ?? $types[0]->name ) : '';
					?>
					<tr>
						<td><a href="<?php echo esc_url( get_permalink( $ride ) ); ?>"><?php echo esc_html( get_the_title( $ride ) ); ?></a></td>
						<td data-label="Type"><?php echo esc_html( $type ); ?></td>
						<td data-label="Opened"><?php echo esc_html( (string) get_post_meta( $ride->ID, 'dp_opened', true ) ); ?></td>
						<td data-label="Intensity"><?php echo esc_html( $word( get_post_meta( $ride->ID, 'dp_intensity', true ), array( Vocab::class, 'intensity_label' ) ) ); ?></td>
						<td data-label="Comfort"><?php echo esc_html( $word( get_post_meta( $ride->ID, 'dp_roughness', true ), array( Vocab::class, 'comfort_label' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
