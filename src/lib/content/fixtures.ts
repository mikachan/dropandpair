import type { Collections } from './parse';
import type { Pairing, Park, Ride } from './types';

/** Test factories. Not collected by Vitest — the include pattern is `*.test.ts`. */

export function makePark(id: string, overrides: Partial<Park> = {}): Park {
	return {
		id,
		name: id,
		country: 'United Kingdom',
		region: 'Staffordshire',
		draft: false,
		body: '',
		sourcePath: `content/parks/${id}.md`,
		...overrides
	};
}

export function makePairing(id: string, overrides: Partial<Pairing> = {}): Pairing {
	return {
		id,
		title: id,
		pairingType: 'watch',
		description: 'A description.',
		affiliate: false,
		draft: false,
		body: '',
		sourcePath: `content/pairings/${id}.md`,
		...overrides
	};
}

export function makeRide(id: string, overrides: Partial<Ride> = {}): Ride {
	return {
		id,
		name: id,
		park: 'a-park',
		rideType: 'coaster',
		opened: 1994,
		manufacturer: 'A manufacturer',
		intensity: 3,
		roughness: 3,
		vibe: ['brutal'],
		gist: 'Mostly airtime.',
		pairings: [],
		furtherReading: [],
		featured: false,
		draft: false,
		body: '',
		sourcePath: `content/rides/${id}.md`,
		...overrides
	};
}

export function makeCollections(overrides: Partial<Collections> = {}): Collections {
	return { rides: [], parks: [], pairings: [], ...overrides };
}
