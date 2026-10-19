/** Site-wide constants used by page metadata, the sitemap and the feed. */
export const site = {
	name: 'Drop and Pair',
	url: 'https://dropandpair.com',
	tagline: 'Before the queue and after the drop.',
	description:
		'Pairings for theme park rides: what to read, watch, listen to and eat before you queue and after you get off.',
	locale: 'en_GB',
	lang: 'en-GB',
	author: 'Drop and Pair'
} as const;

/** Absolute URL for a site-relative path, for canonical tags, OG, the sitemap and the feed. */
export function absoluteUrl(path: string): string {
	return new URL(path, site.url).href;
}
