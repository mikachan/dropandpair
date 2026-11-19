<?php
/**
 * Park archive: parks grouped by country (A to Z), each with its published ride count.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;

defined( 'ABSPATH' ) || exit;

$groups = Queries::parks_by_country();
if ( ! $groups ) {
	echo '<p>No parks yet.</p>';
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-parks-by-country' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php foreach ( $groups as $country => $entries ) : ?>
		<section class="dp-archive-group">
			<h2><?php echo esc_html( $country ); ?></h2>
			<ul class="dp-park-list">
				<?php foreach ( $entries as $entry ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $entry['park'] ) ); ?>"><?php echo esc_html( get_the_title( $entry['park'] ) ); ?></a>
						<span class="dp-label"><?php echo esc_html( sprintf( _n( '%d ride', '%d rides', $entry['rides'], 'drop-and-pair' ), $entry['rides'] ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
</div>
