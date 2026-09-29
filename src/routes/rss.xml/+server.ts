import { ridesByAdded } from '$lib/content/graph';
import { rideTypeLabels } from '$lib/content/labels';
import { graph } from '$lib/content/load.server';
import { escapeXml, toRfc822 } from '$lib/seo/xml';
import { absoluteUrl, site } from '$lib/site';
import type { RequestHandler } from './$types';

export const prerender = true;

/** How many rides the feed carries. */
const FEED_SIZE = 20;

export const GET: RequestHandler = () => {
	const rides = ridesByAdded(graph).slice(0, FEED_SIZE);

	/*
	 * Derived from the newest ride rather than the clock, so rebuilding
	 * unchanged content produces an unchanged feed.
	 */
	const newest = rides.find((ride) => ride.added)?.added;

	const items = rides
		.map((ride) => {
			const url = absoluteUrl(`/rides/${ride.id}`);
			const park = graph.parkById.get(ride.park);
			const description = [
				ride.tastingNote,
				park ? `${rideTypeLabels[ride.rideType]} at ${park.name}, ${park.country}.` : '',
				ride.pairings.length > 0
					? `${ride.pairings.length} pairing${ride.pairings.length === 1 ? '' : 's'}.`
					: ''
			]
				.filter(Boolean)
				.join(' ');

			return `		<item>
			<title>${escapeXml(`${ride.name}, ${park?.name ?? 'unknown park'}`)}</title>
			<link>${escapeXml(url)}</link>
			<guid isPermaLink="true">${escapeXml(url)}</guid>
			<description>${escapeXml(description)}</description>
			<category>${escapeXml(rideTypeLabels[ride.rideType])}</category>${
				ride.added ? `\n\t\t\t<pubDate>${toRfc822(ride.added)}</pubDate>` : ''
			}
		</item>`;
		})
		.join('\n');

	const body = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>${escapeXml(site.name)}</title>
		<link>${escapeXml(absoluteUrl('/'))}</link>
		<description>${escapeXml(site.description)}</description>
		<language>${site.lang}</language>
		<atom:link href="${escapeXml(absoluteUrl('/rss.xml'))}" rel="self" type="application/rss+xml" />${
			newest ? `\n\t\t<lastBuildDate>${toRfc822(newest)}</lastBuildDate>` : ''
		}
${items}
	</channel>
</rss>
`;

	return new Response(body, {
		headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' }
	});
};
