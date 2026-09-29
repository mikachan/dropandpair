/**
 * The controlled vocabularies, kept free of any dependency on purpose.
 *
 * `schemas.ts` validates against these, and the UI orders and labels by them.
 * Because this module imports nothing, components can use it without pulling
 * Zod into the browser bundle.
 */

export const RIDE_TYPES = [
	'coaster',
	'dark-ride',
	'flat-ride',
	'water-ride',
	'drop-tower'
] as const;

/**
 * Ordered like a tasting flight: before the ride, straight after, then later.
 * This order drives the grouping on a ride page and the filter menus.
 */
export const PAIRING_TYPES = [
	'aperitif',
	'chaser',
	'palate-cleanser',
	'eat-and-drink',
	'do-next',
	'listen',
	'read',
	'watch',
	'nightcap'
] as const;

export type RideType = (typeof RIDE_TYPES)[number];
export type PairingType = (typeof PAIRING_TYPES)[number];
