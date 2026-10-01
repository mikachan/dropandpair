/** Site-wide constants used by page metadata, the sitemap and the feed. */
export const site = {
	name: 'Drop and Pair',
	url: 'https://dropandpair.com',
	tagline: 'Rides, and what to have with them.',
	description:
		'Curated pairings for theme park rides: quick takes, and the films, records, meals and detours that belong with them.',
	locale: 'en_GB',
	lang: 'en-GB',
	author: 'Drop and Pair'
} as const;

/** Absolute URL for a site-relative path, for canonical tags, OG, the sitemap and the feed. */
export function absoluteUrl(path: string): string {
	return new URL(path, site.url).href;
}
