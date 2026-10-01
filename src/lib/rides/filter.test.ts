import type { RideSummary } from '$lib/content/view';
import { describe, expect, it } from 'vitest';
import {
	EMPTY_FILTERS,
	buildFacets,
	filterRides,
	isFiltered,
	parseFilters,
	toSearchParams
} from './filter';

function summary(overrides: Partial<RideSummary> = {}): RideSummary {
	return {
		id: 'a-ride',
		name: 'A Ride',
		rideType: 'coaster',
		opened: 1994,
		intensity: 3,
		roughness: 3,
		vibe: ['brutal'],
		gist: 'Mostly airtime.',
		park: {
			id: 'a-park',
			name: 'A Park',
			country: 'United Kingdom',
			region: 'Staffordshire'
		},
		pairingTypes: ['watch'],
		pairingCount: 1,
		featured: false,
		draft: false,
		...overrides
	};
}

const rides: RideSummary[] = [
	summary({ id: 'nemesis', rideType: 'coaster', intensity: 5, vibe: ['brutal', 'scenic'] }),
	summary({
		id: 'valhalla',
		rideType: 'water-ride',
		intensity: 3,
		vibe: ['eerie'],
		pairingTypes: ['later']
	}),
	summary({
		id: 'silver-star',
		rideType: 'coaster',
		intensity: 4,
		vibe: ['stately'],
		pairingTypes: ['listen', 'eat-and-drink'],
		park: { id: 'europa-park', name: 'Europa-Park', country: 'Germany', region: 'Baden' }
	})
];

describe('parseFilters and toSearchParams', () => {
	it('round-trips a full selection', () => {
		const params = new URLSearchParams(
			'type=coaster&type=water-ride&pairing=watch&vibe=brutal&country=Germany&intensity=4'
		);
		const filters = parseFilters(params);

		expect(filters).toEqual({
			types: ['coaster', 'water-ride'],
			pairing: 'watch',
			vibe: 'brutal',
			country: 'Germany',
			intensity: '4'
		});
		expect(toSearchParams(filters).toString()).toBe(params.toString());
	});

	it('reads an empty query as no filters, and writes no filters as an empty query', () => {
		expect(parseFilters(new URLSearchParams())).toEqual(EMPTY_FILTERS);
		expect(toSearchParams(EMPTY_FILTERS).toString()).toBe('');
		expect(isFiltered(EMPTY_FILTERS)).toBe(false);
	});

	it('leaves empty values out of the query so a cleared filter leaves a clean URL', () => {
		const query = toSearchParams({ ...EMPTY_FILTERS, vibe: 'brutal' }).toString();

		expect(query).toBe('vibe=brutal');
	});
});

describe('filterRides', () => {
	it('returns everything when nothing is selected', () => {
		expect(filterRides(rides, EMPTY_FILTERS)).toHaveLength(3);
	});

	it('treats several ride types as "any of these"', () => {
		const result = filterRides(rides, { ...EMPTY_FILTERS, types: ['coaster', 'water-ride'] });

		expect(result).toHaveLength(3);
		expect(filterRides(rides, { ...EMPTY_FILTERS, types: ['water-ride'] })).toHaveLength(1);
	});

	it('matches a ride by the pairing types it covers', () => {
		const result = filterRides(rides, { ...EMPTY_FILTERS, pairing: 'listen' });

		expect(result.map((ride) => ride.id)).toEqual(['silver-star']);
	});

	it('matches a vibe anywhere in the list', () => {
		expect(filterRides(rides, { ...EMPTY_FILTERS, vibe: 'scenic' }).map((r) => r.id)).toEqual([
			'nemesis'
		]);
	});

	it('matches country through the park', () => {
		expect(filterRides(rides, { ...EMPTY_FILTERS, country: 'Germany' }).map((r) => r.id)).toEqual([
			'silver-star'
		]);
	});

	it('matches intensity as an exact intensity', () => {
		expect(filterRides(rides, { ...EMPTY_FILTERS, intensity: '5' }).map((r) => r.id)).toEqual([
			'nemesis'
		]);
	});

	it('combines filters as AND', () => {
		const result = filterRides(rides, {
			...EMPTY_FILTERS,
			types: ['coaster'],
			country: 'United Kingdom'
		});

		expect(result.map((ride) => ride.id)).toEqual(['nemesis']);
		expect(
			filterRides(rides, { ...EMPTY_FILTERS, types: ['water-ride'], country: 'Germany' })
		).toEqual([]);
	});
});

describe('buildFacets', () => {
	it('offers only values that exist, so no filter can return nothing on its own', () => {
		const facets = buildFacets(rides);

		expect(facets.rideTypes).toEqual(['coaster', 'water-ride']);
		expect(facets.countries).toEqual(['Germany', 'United Kingdom']);
		expect(facets.intensities).toEqual([3, 4, 5]);
		expect(facets.vibes).toEqual(['brutal', 'eerie', 'scenic', 'stately']);
	});

	it('keeps controlled vocabularies in their canonical order, not alphabetical', () => {
		const facets = buildFacets(rides);

		// Moment order: eat-and-drink, listen, watch, later.
		expect(facets.pairingTypes).toEqual(['eat-and-drink', 'listen', 'watch', 'later']);
	});

	it('every facet value matches at least one ride', () => {
		const facets = buildFacets(rides);

		for (const vibe of facets.vibes) {
			expect(filterRides(rides, { ...EMPTY_FILTERS, vibe }).length).toBeGreaterThan(0);
		}
		for (const country of facets.countries) {
			expect(filterRides(rides, { ...EMPTY_FILTERS, country }).length).toBeGreaterThan(0);
		}
	});
});
