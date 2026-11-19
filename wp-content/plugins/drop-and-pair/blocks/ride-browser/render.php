<?php
/**
 * Rides archive: a GET filter form (works without JS) and every matching published ride.
 * Filters are read from the raw query string so `type=a&type=b` keeps working.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;
use DropAndPair\RideFilter;
use DropAndPair\Vocab;

defined( 'ABSPATH' ) || exit;

$posts = get_posts(
	array(
		'post_type'      => 'dp_ride',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

// One row per ride. Country comes only from a published park, so a hidden park never leaks through the filter.
$rides = array();
foreach ( $posts as $post ) {
	$types   = get_the_terms( $post->ID, 'dp_ride_type' );
	$vibes   = get_the_terms( $post->ID, 'dp_vibe' );
	$park    = Queries::park_for_ride( $post->ID );
	$rides[] = array(
		'post'      => $post,
		'park'      => $park,
		'ride_type' => is_array( $types ) && $types ? $types[0]->slug : '',
		'moments'   => Queries::moments_for_ride( $post->ID ),
		'vibes'     => is_array( $vibes ) ? array_map( static fn( $t ) => $t->name, $vibes ) : array(),
		'country'   => $park ? trim( (string) get_post_meta( $park->ID, 'dp_country', true ) ) : '',
		'intensity' => (int) get_post_meta( $post->ID, 'dp_intensity', true ),
		'roughness' => (int) get_post_meta( $post->ID, 'dp_roughness', true ),
	);
}

$query_string = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( (string) $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$filters      = RideFilter::parse( $query_string );
$facets       = RideFilter::facets( $rides );
$shown        = RideFilter::apply( $rides, $filters );
$total        = count( $rides );
$count        = count( $shown );
$action       = get_post_type_archive_link( 'dp_ride' ) ?: home_url( '/rides/' );

/** Meter value as a word, or an empty string when unrated. */
$word = static function ( int $n, callable $label ): string {
	return ( $n >= 1 && $n <= 5 ) ? strtolower( $label( $n ) ) : '';
};
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-ride-browser' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<form class="dp-filters dp-dashed" method="get" action="<?php echo esc_url( $action ); ?>">
		<?php if ( $facets['ride_types'] ) : ?>
			<fieldset class="dp-filters__group">
				<legend class="dp-label">Ride type</legend>
				<div class="dp-filters__pills">
					<?php foreach ( $facets['ride_types'] as $slug ) : ?>
						<label class="dp-filters__pill">
							<input type="checkbox" name="type" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $filters['types'], true ) ); ?>>
							<span><?php echo esc_html( Vocab::RIDE_TYPES[ $slug ] ?? $slug ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<div class="dp-filters__selects">
			<p class="dp-filters__field">
				<label class="dp-label" for="dp-filter-pairing">Pairs with</label>
				<select id="dp-filter-pairing" name="pairing">
					<option value="">Anything</option>
					<?php foreach ( $facets['moments'] as $slug ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['pairing'], $slug ); ?>><?php echo esc_html( Vocab::MOMENTS[ $slug ]['label'] ?? $slug ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="dp-filters__field">
				<label class="dp-label" for="dp-filter-intensity">Intensity</label>
				<select id="dp-filter-intensity" name="intensity">
					<option value="">Any</option>
					<?php foreach ( $facets['intensities'] as $value ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $filters['intensity'], (string) $value ); ?>><?php echo esc_html( Vocab::intensity_label( $value ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="dp-filters__field">
				<label class="dp-label" for="dp-filter-vibe">Vibe</label>
				<select id="dp-filter-vibe" name="vibe">
					<option value="">Any</option>
					<?php foreach ( $facets['vibes'] as $vibe ) : ?>
						<option value="<?php echo esc_attr( $vibe ); ?>" <?php selected( $filters['vibe'], $vibe ); ?>><?php echo esc_html( $vibe ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="dp-filters__field">
				<label class="dp-label" for="dp-filter-country">Country</label>
				<select id="dp-filter-country" name="country">
					<option value="">Anywhere</option>
					<?php foreach ( $facets['countries'] as $country ) : ?>
						<option value="<?php echo esc_attr( $country ); ?>" <?php selected( $filters['country'], $country ); ?>><?php echo esc_html( $country ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<p class="dp-filters__actions">
			<button type="submit" class="dp-filters__apply">Filter</button>
			<?php if ( RideFilter::is_filtered( $filters ) ) : ?>
				<a href="<?php echo esc_url( $action ); ?>">Clear filters</a>
			<?php endif; ?>
		</p>
		<?php // Submit on change; the button above hides itself once this has run. ?>
		<script>(function(f){f.classList.add('dp-filters--auto');f.addEventListener('change',function(){f.submit()})})(document.currentScript.parentNode)</script>
	</form>

	<p class="dp-label dp-ride-browser__count" aria-live="polite">
		<?php
		if ( $count === $total ) {
			echo esc_html( sprintf( _n( '%d ride on the list.', '%d rides on the list.', $total, 'drop-and-pair' ), $total ) );
		} else {
			echo esc_html( sprintf( 'Showing %1$d of %2$d rides.', $count, $total ) );
		}
		?>
	</p>

	<?php if ( $shown ) : ?>
		<div class="dp-ride-browser__scroll">
			<table class="dp-table dp-table--stack dp-ride-browser__table">
				<thead>
					<tr><th scope="col">Ride</th><th scope="col">Park</th><th scope="col">Country</th><th scope="col">Type</th><th scope="col">Intensity</th><th scope="col">Comfort</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $shown as $ride ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_permalink( $ride['post'] ) ); ?>"><?php echo esc_html( get_the_title( $ride['post'] ) ); ?></a></td>
							<td data-label="Park"><?php echo $ride['park'] ? '<a href="' . esc_url( get_permalink( $ride['park'] ) ) . '">' . esc_html( get_the_title( $ride['park'] ) ) . '</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td data-label="Country"><?php echo esc_html( $ride['country'] ); ?></td>
							<td data-label="Type"><?php echo esc_html( Vocab::RIDE_TYPES[ $ride['ride_type'] ] ?? $ride['ride_type'] ); ?></td>
							<td data-label="Intensity"><?php echo esc_html( $word( $ride['intensity'], array( Vocab::class, 'intensity_label' ) ) ); ?></td>
							<td data-label="Comfort"><?php echo esc_html( $word( $ride['roughness'], array( Vocab::class, 'comfort_label' ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<p>No rides match those filters.</p>
	<?php endif; ?>
</div>
