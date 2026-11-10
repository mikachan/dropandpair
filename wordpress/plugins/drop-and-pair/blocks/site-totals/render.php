<?php
/**
 * "12 rides · 3 parks · 40 pairings", published posts only.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content.
 * @var WP_Block $block      Block instance.
 */

use DropAndPair\Queries;

defined( 'ABSPATH' ) || exit;

$totals = Queries::totals();
?>
<p <?php echo get_block_wrapper_attributes( array( 'class' => 'dp-label dp-site-totals' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php
	$noun = static fn( int $n, string $one ) => $n . ' ' . $one . ( 1 === $n ? '' : 's' );
	echo esc_html( implode( ' · ', array( $noun( $totals['rides'], 'ride' ), $noun( $totals['parks'], 'park' ), $noun( $totals['pairings'], 'pairing' ) ) ) );
?></p>
