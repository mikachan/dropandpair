import { graph } from '$lib/content/load.server';
import { escapeXml } from '$lib/seo/xml';
import { absoluteUrl } from '$lib/site';
import type { RequestHandler } from './$types';

export const prerender = true;

interface SitemapEntry {
	path: string;
	lastmod?: string;
	priority: string;
}

/** /search is left out on purpose: it has no content of its own and is noindex. */
export const GET: RequestHandler = () => {
	const entries: SitemapEntry[] = [
		{ path: '/', priority: '1.0' },
		{ path: '/rides', priority: '0.9' },
		{ path: '/pairings', priority: '0.8' },
		{ path: '/parks', priority: '0.8' },
		{ path: '/about', priority: '0.4' },
		...graph.rides.map((ride) => ({
			path: `/rides/${ride.id}`,
			lastmod: ride.added,
			priority: '0.7'
		})),
		...graph.pairings.map((pairing) => ({ path: `/pairings/${pairing.id}`, priority: '0.6' })),
		...graph.parks.map((park) => ({ path: `/parks/${park.id}`, priority: '0.6' }))
	];

	const urls = entries
		.map(
			(entry) => `	<url>
		<loc>${escapeXml(absoluteUrl(entry.path))}</loc>${
			entry.lastmod ? `\n\t\t<lastmod>${entry.lastmod}</lastmod>` : ''
		}
		<priority>${entry.priority}</priority>
	</url>`
		)
		.join('\n');

	const body = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>
`;

	return new Response(body, {
		headers: { 'Content-Type': 'application/xml; charset=utf-8' }
	});
};
