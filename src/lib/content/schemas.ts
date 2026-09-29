import { z } from 'zod';

/**
 * Frontmatter schemas for everything in `content/`.
 *
 * These are the single source of truth: the TypeScript types in `types.ts` are
 * derived from them, so adding a field here is enough to make it type-safe
 * everywhere. A typo in a Markdown file fails the build with the file name,
 * the field, and what was expected.
 */

export const RIDE_TYPES = ['coaster', 'dark-ride', 'flat-ride', 'water-ride', 'drop-tower'] as const;

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

/** Lowercase kebab-case, matching the Markdown file name. */
const slug = z
	.string()
	.regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/, 'must be a lowercase kebab-case slug, e.g. "big-one"');

/** 1–5, used for intensity, roughness and pairing strength. */
const scale = z.number().int().min(1).max(5);

const nonEmpty = z.string().trim().min(1, 'must not be empty');

export const parkSchema = z.strictObject({
	id: slug,
	name: nonEmpty,
	country: nonEmpty,
	region: nonEmpty,
	website: z.url().optional(),
	draft: z.boolean().default(false)
});

export const pairingSchema = z.strictObject({
	id: slug,
	title: nonEmpty,
	pairingType: z.enum(PAIRING_TYPES),
	description: nonEmpty,
	url: z.url().optional(),
	affiliate: z.boolean().default(false),
	image: z
		.string()
		.refine(
			(value) => value.startsWith('/') && !value.includes('://'),
			'must be a local path beginning with "/", e.g. "/images/foo.jpg" — remote images are not allowed'
		)
		.optional(),
	draft: z.boolean().default(false)
});

/** Only filled in where it tells you something. A dark ride rarely needs a speed. */
export const rideStatsSchema = z.strictObject({
	/** Metres. */
	height: z.number().positive().optional(),
	/** Kilometres per hour. */
	speed: z.number().positive().optional(),
	/** Metres. */
	length: z.number().positive().optional(),
	inversions: z.number().int().min(0).optional()
});

export const ridePairingRefSchema = z.strictObject({
	pairing: slug,
	blurb: nonEmpty,
	strength: scale
});

export const rideSchema = z.strictObject({
	id: slug,
	name: nonEmpty,
	park: slug,
	rideType: z.enum(RIDE_TYPES),
	opened: z.number().int().min(1800).max(2100),
	manufacturer: nonEmpty,
	stats: rideStatsSchema.optional(),
	/** Body, in wine terms. */
	intensity: scale,
	/** Finish, in wine terms. */
	roughness: scale,
	vibe: z.array(nonEmpty).min(1, 'needs at least one vibe'),
	tastingNote: nonEmpty,
	pairings: z.array(ridePairingRefSchema).default([]),
	featured: z.boolean().default(false),
	draft: z.boolean().default(false)
});
