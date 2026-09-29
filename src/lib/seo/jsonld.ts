import { rideTypeLabels } from '$lib/content/labels';
import type { PairingSummary, RideDetail } from '$lib/content/view';
import { absoluteUrl, site } from '$lib/site';

/**
 * Structured data builders.
 *
 * Only emitted where it describes something schema.org actually models — a
 * ride is a tourist attraction, a park is an amusement park — plus breadcrumbs
 * on detail pages. Everything is wrapped in an `@graph` so a page can carry
 * more than one node.
 */

type Node = Record<string, unknown>;

export function graph(...nodes: Node[]): Node {
	return { '@context': 'https://schema.org', '@graph': nodes };
}

export function website(): Node {
	return {
		'@type': 'WebSite',
		'@id': absoluteUrl('/'),
		name: site.name,
		alternateName: site.tagline,
		description: site.description,
		url: absoluteUrl('/'),
		inLanguage: site.lang,
		potentialAction: {
			'@type': 'SearchAction',
			target: {
				'@type': 'EntryPoint',
				urlTemplate: `${absoluteUrl('/search')}?q={search_term_string}`
			},
			'query-input': 'required name=search_term_string'
		}
	};
}

export function breadcrumbs(trail: Array<{ name: string; path: string }>): Node {
	return {
		'@type': 'BreadcrumbList',
		itemListElement: trail.map((item, index) => ({
			'@type': 'ListItem',
			position: index + 1,
			name: item.name,
			item: absoluteUrl(item.path)
		}))
	};
}

export function collection(
	title: string,
	path: string,
	items: Array<{ name: string; path: string }>
): Node {
	return {
		'@type': 'CollectionPage',
		name: title,
		url: absoluteUrl(path),
		mainEntity: {
			'@type': 'ItemList',
			numberOfItems: items.length,
			itemListElement: items.map((item, index) => ({
				'@type': 'ListItem',
				position: index + 1,
				name: item.name,
				url: absoluteUrl(item.path)
			}))
		}
	};
}

/** Takes only what schema.org needs, so any park-shaped object will do. */
export interface ParkLike {
	id: string;
	name: string;
	region: string;
	country: string;
	website?: string;
}

export function amusementPark(park: ParkLike, rideCount: number): Node {
	return {
		'@type': 'AmusementPark',
		'@id': absoluteUrl(`/parks/${park.id}`),
		name: park.name,
		url: park.website ?? absoluteUrl(`/parks/${park.id}`),
		address: {
			'@type': 'PostalAddress',
			addressRegion: park.region,
			addressCountry: park.country
		},
		...(rideCount > 0 ? { numberOfItems: rideCount } : {})
	};
}

export function touristAttraction(ride: RideDetail, pairings: PairingSummary[]): Node {
	return {
		'@type': 'TouristAttraction',
		'@id': absoluteUrl(`/rides/${ride.id}`),
		name: ride.name,
		description: ride.tastingNote,
		url: absoluteUrl(`/rides/${ride.id}`),
		additionalType: rideTypeLabels[ride.rideType],
		containedInPlace: {
			'@type': 'AmusementPark',
			'@id': absoluteUrl(`/parks/${ride.park.id}`),
			name: ride.park.name
		},
		keywords: [...ride.vibe, rideTypeLabels[ride.rideType]].join(', '),
		...(pairings.length > 0
			? { isRelatedTo: pairings.map((pairing) => ({ '@type': 'Thing', name: pairing.title })) }
			: {})
	};
}
