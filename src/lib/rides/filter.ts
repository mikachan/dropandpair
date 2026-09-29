import { PAIRING_TYPES, RIDE_TYPES, type PairingType } from '$lib/content/constants';
import type { RideSummary } from '$lib/content/view';

/**
 * Ride list filtering.
 *
 * The URL is the state: filters are read from and written to query params, so
 * a filtered view is shareable and the back button behaves. Pure functions, so
 * the same code runs during prerendering and in the browser.
 */

export interface Filters {
	/** Ride types, multi-select. */
	types: string[];
	/** Pairing type, single. Empty means any. */
	pairing: string;
	vibe: string;
	country: string;
	/** Intensity, as a string so it round-trips through the URL unchanged. */
	body: string;
}

export const EMPTY_FILTERS: Filters = { types: [], pairing: '', vibe: '', country: '', body: '' };

export interface Facets {
	rideTypes: string[];
	pairingTypes: string[];
	vibes: string[];
	countries: string[];
	bodies: number[];
}

export function parseFilters(params: URLSearchParams): Filters {
	return {
		types: params.getAll('type').filter(Boolean),
		pairing: params.get('pairing') ?? '',
		vibe: params.get('vibe') ?? '',
		country: params.get('country') ?? '',
		body: params.get('body') ?? ''
	};
}

/** Only non-empty filters are written, so an unfiltered list has a clean URL. */
export function toSearchParams(filters: Filters): URLSearchParams {
	const params = new URLSearchParams();
	for (const type of filters.types) params.append('type', type);
	if (filters.pairing) params.set('pairing', filters.pairing);
	if (filters.vibe) params.set('vibe', filters.vibe);
	if (filters.country) params.set('country', filters.country);
	if (filters.body) params.set('body', filters.body);
	return params;
}

export function isFiltered(filters: Filters): boolean {
	return (
		filters.types.length > 0 ||
		Boolean(filters.pairing) ||
		Boolean(filters.vibe) ||
		Boolean(filters.country) ||
		Boolean(filters.body)
	);
}

export function filterRides(rides: RideSummary[], filters: Filters): RideSummary[] {
	return rides.filter((ride) => {
		if (filters.types.length > 0 && !filters.types.includes(ride.rideType)) return false;
		if (filters.pairing && !ride.pairingTypes.includes(filters.pairing as PairingType))
			return false;
		if (filters.vibe && !ride.vibe.includes(filters.vibe)) return false;
		if (filters.country && ride.park.country !== filters.country) return false;
		if (filters.body && ride.intensity !== Number(filters.body)) return false;
		return true;
	});
}

/** Facets are derived from the rides that exist, so no filter can return nothing. */
export function buildFacets(rides: RideSummary[]): Facets {
	const vibes = new Set<string>();
	const countries = new Set<string>();
	const rideTypes = new Set<string>();
	const pairingTypes = new Set<string>();
	const bodies = new Set<number>();

	for (const ride of rides) {
		rideTypes.add(ride.rideType);
		countries.add(ride.park.country);
		bodies.add(ride.intensity);
		for (const vibe of ride.vibe) vibes.add(vibe);
		for (const type of ride.pairingTypes) pairingTypes.add(type);
	}

	const alphabetical = (a: string, b: string) => a.localeCompare(b, 'en');
	/** Controlled vocabularies keep their canonical order; free text goes A-Z. */
	const byVocabulary = (vocabulary: readonly string[]) => (a: string, b: string) =>
		vocabulary.indexOf(a) - vocabulary.indexOf(b);

	return {
		rideTypes: [...rideTypes].sort(byVocabulary(RIDE_TYPES)),
		pairingTypes: [...pairingTypes].sort(byVocabulary(PAIRING_TYPES)),
		vibes: [...vibes].sort(alphabetical),
		countries: [...countries].sort(alphabetical),
		bodies: [...bodies].sort((a, b) => a - b)
	};
}
