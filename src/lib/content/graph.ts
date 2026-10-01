import type { PairingType } from './constants';
import { pairingTypeRank } from './labels';
import { ContentError, type Collections, type ContentProblem } from './parse';
import type { PairedRide, Pairing, Park, Ride, RidePairing } from './types';

/**
 * Resolving the content into a graph.
 *
 * Relationships are authored in one direction only — each ride lists its
 * pairings — so the reverse lookups every other page needs (a pairing's rides,
 * a park's rides) are computed here, once, at build time.
 *
 * Like `parse.ts`, this is pure: graph in, graph out.
 */

export interface ContentGraph {
	rides: Ride[];
	parks: Park[];
	pairings: Pairing[];
	rideById: Map<string, Ride>;
	parkById: Map<string, Park>;
	pairingById: Map<string, Pairing>;
	/** Reverse lookup: park id -> its rides. */
	ridesByPark: Map<string, Ride[]>;
	/** Reverse lookup: pairing id -> every ride that pairs with it. */
	ridesByPairing: Map<string, PairedRide[]>;
}

const byName = (a: { name: string }, b: { name: string }) => a.name.localeCompare(b.name, 'en');
const byTitle = (a: { title: string }, b: { title: string }) =>
	a.title.localeCompare(b.title, 'en');

/**
 * Checks every cross-collection reference. Runs against the full content set,
 * drafts included, so a typo always fails the build rather than hiding until
 * the day you publish.
 */
export function validateReferences(collections: Collections): void {
	const parkIds = new Set(collections.parks.map((park) => park.id));
	const pairingIds = new Set(collections.pairings.map((pairing) => pairing.id));
	const problems: ContentProblem[] = [];

	for (const ride of collections.rides) {
		if (!parkIds.has(ride.park)) {
			problems.push({
				path: ride.sourcePath,
				field: 'park',
				message: `refers to "${ride.park}", but there is no content/parks/${ride.park}.md`
			});
		}

		ride.pairings.forEach((ref, index) => {
			if (!pairingIds.has(ref.pairing)) {
				problems.push({
					path: ride.sourcePath,
					field: `pairings.${index}.pairing`,
					message: `refers to "${ref.pairing}", but there is no content/pairings/${ref.pairing}.md`
				});
			}
		});

		const seen = new Set<string>();
		for (const ref of ride.pairings) {
			if (seen.has(ref.pairing)) {
				problems.push({
					path: ride.sourcePath,
					field: 'pairings',
					message: `lists "${ref.pairing}" more than once`
				});
			}
			seen.add(ref.pairing);
		}
	}

	if (problems.length > 0) throw new ContentError(problems);
}

/**
 * Validates references, drops drafts unless asked to keep them, and builds the
 * lookups. Dropping a draft park or pairing also prunes anything that pointed
 * at it, so a published site never contains a dead link.
 */
export function buildGraph(
	collections: Collections,
	{ includeDrafts }: { includeDrafts: boolean }
): ContentGraph {
	validateReferences(collections);

	const published = <T extends { draft: boolean }>(entries: T[]) =>
		includeDrafts ? entries : entries.filter((entry) => !entry.draft);

	const parks = published(collections.parks).sort(byName);
	const pairings = published(collections.pairings).sort(byTitle);

	const parkIds = new Set(parks.map((park) => park.id));
	const pairingIds = new Set(pairings.map((pairing) => pairing.id));

	const rides = published(collections.rides)
		.filter((ride) => parkIds.has(ride.park))
		.map((ride) => ({
			...ride,
			pairings: ride.pairings.filter((ref) => pairingIds.has(ref.pairing))
		}))
		.sort(byName);

	const rideById = new Map(rides.map((ride) => [ride.id, ride]));
	const parkById = new Map(parks.map((park) => [park.id, park]));
	const pairingById = new Map(pairings.map((pairing) => [pairing.id, pairing]));

	const ridesByPark = new Map<string, Ride[]>(parks.map((park) => [park.id, []]));
	const ridesByPairing = new Map<string, PairedRide[]>(pairings.map((pairing) => [pairing.id, []]));

	for (const ride of rides) {
		ridesByPark.get(ride.park)?.push(ride);
		for (const ref of ride.pairings) {
			ridesByPairing.get(ref.pairing)?.push({ ride, blurb: ref.blurb, strength: ref.strength });
		}
	}

	for (const paired of ridesByPairing.values()) {
		paired.sort((a, b) => b.strength - a.strength || byName(a.ride, b.ride));
	}

	return {
		rides,
		parks,
		pairings,
		rideById,
		parkById,
		pairingById,
		ridesByPark,
		ridesByPairing
	};
}

/**
 * Rides newest first, for the feed and any "latest" listing. An undated ride
 * sorts after every dated one rather than pretending to be new.
 */
export function ridesByAdded(graph: ContentGraph): Ride[] {
	return [...graph.rides].sort((a, b) => {
		if (a.added && b.added) return b.added.localeCompare(a.added) || byName(a, b);
		if (a.added) return -1;
		if (b.added) return 1;
		return byName(a, b);
	});
}

/** A ride's pairings, resolved and in the order you would meet them. */
export function pairingsForRide(graph: ContentGraph, ride: Ride): RidePairing[] {
	return ride.pairings
		.flatMap((ref) => {
			const pairing = graph.pairingById.get(ref.pairing);
			return pairing ? [{ pairing, blurb: ref.blurb, strength: ref.strength }] : [];
		})
		.sort(
			(a, b) =>
				pairingTypeRank(a.pairing.pairingType) - pairingTypeRank(b.pairing.pairingType) ||
				b.strength - a.strength ||
				byTitle(a.pairing, b.pairing)
		);
}

export function ridesForPairing(graph: ContentGraph, pairingId: string): PairedRide[] {
	return graph.ridesByPairing.get(pairingId) ?? [];
}

export function ridesForPark(graph: ContentGraph, parkId: string): Ride[] {
	return graph.ridesByPark.get(parkId) ?? [];
}

/** The park a ride belongs to. Guaranteed to exist: `buildGraph` prunes orphans. */
export function parkForRide(graph: ContentGraph, ride: Ride): Park {
	const park = graph.parkById.get(ride.park);
	if (!park) throw new Error(`Ride "${ride.id}" has no park "${ride.park}" in the graph`);
	return park;
}

/** The distinct pairing types a ride covers, in course order. Used by the filters. */
export function pairingTypesForRide(graph: ContentGraph, ride: Ride): PairingType[] {
	const types = new Set<PairingType>();
	for (const ref of ride.pairings) {
		const pairing = graph.pairingById.get(ref.pairing);
		if (pairing) types.add(pairing.pairingType);
	}
	return [...types].sort((a, b) => pairingTypeRank(a) - pairingTypeRank(b));
}
