<?php
namespace DropAndPair;

defined( 'ABSPATH' ) || exit;

/**
 * Every bit of vocabulary lives here, mirroring `src/lib/content/labels.ts` and
 * `constants.ts` in the SvelteKit app, so a label change is a one-line edit.
 */
final class Vocab {

	/** Slug => label, in the same order as RIDE_TYPES. */
	public const RIDE_TYPES = array(
		'coaster'    => 'Coaster',
		'dark-ride'  => 'Dark ride',
		'flat-ride'  => 'Flat ride',
		'water-ride' => 'Water ride',
		'drop-tower' => 'Drop tower',
	);

	/**
	 * Ordered as you would meet them: before the ride, straight after, then later.
	 * Colours are the "stub" colours; each is readable with #1a1a1a text.
	 */
	public const MOMENTS = array(
		'before'        => array(
			'label'       => 'Before',
			'description' => 'Things to do before you ride.',
			'colour'      => '#ffd23f',
		),
		'right-after'   => array(
			'label'       => 'Right after',
			'description' => 'For straight after you get off.',
			'colour'      => '#ff9f6e',
		),
		'wind-down'     => array(
			'label'       => 'Wind down',
			'description' => 'Something gentle, for after a really intense ride.',
			'colour'      => '#b5c7f2',
		),
		'eat-and-drink' => array(
			'label'       => 'Eat and drink',
			'description' => "Something to eat or drink once you're off the ride.",
			'colour'      => '#7fd1c7',
		),
		'do-next'       => array(
			'label'       => 'Do next',
			'description' => 'Somewhere to go while the adrenaline wears off.',
			'colour'      => '#a9dd8a',
		),
		'listen'        => array(
			'label'       => 'Listen',
			'description' => 'For the queue, the car park, or the long drive home.',
			'colour'      => '#c9a8f0',
		),
		'read'          => array(
			'label'       => 'Read',
			'description' => 'For the hotel, the flight, or the long wait over the winter closed season.',
			'colour'      => '#f49cbb',
		),
		'watch'         => array(
			'label'       => 'Watch',
			'description' => 'A film or TV show that goes really well with the ride.',
			'colour'      => '#8cc8f2',
		),
		'later'         => array(
			'label'       => 'Later',
			'description' => 'One to save for later, or another day.',
			'colour'      => '#e3d9b5',
		),
	);

	private const INTENSITY = array( 1 => 'Gentle', 2 => 'Mild', 3 => 'Moderate', 4 => 'Strong', 5 => 'Maximum' );
	private const COMFORT   = array( 1 => 'Silky', 2 => 'Smooth', 3 => 'Bumpy', 4 => 'Rough', 5 => 'Brutal' );
	private const STRENGTH  = array(
		1 => 'Worth a try',
		2 => 'Gentle match',
		3 => 'Good match',
		4 => 'Strong match',
		5 => 'Perfect marriage',
	);

	/** Intensity: how hard it hits. */
	public static function intensity_label( int $intensity ): string {
		return self::INTENSITY[ $intensity ] ?? 'Unrated intensity';
	}

	/** Roughness, as comfort: how you feel walking off. */
	public static function comfort_label( int $roughness ): string {
		return self::COMFORT[ $roughness ] ?? 'Unrated comfort';
	}

	/** How well a pairing suits its ride. */
	public static function strength_label( int $strength ): string {
		return self::STRENGTH[ $strength ] ?? 'Unrated match';
	}
}
