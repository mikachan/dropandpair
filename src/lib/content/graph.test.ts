import { describe, expect, it } from 'vitest';
import { makeCollections, makePairing, makePark, makeRide } from './fixtures';
import {
	buildGraph,
	pairingsForRide,
	parkForRide,
	ridesForPairing,
	ridesForPark
} from './graph';
import { ContentError } from './parse';

const PUBLISHED = { includeDrafts: false };
const EVERYTHING = { includeDrafts: true };

describe('buildGraph reference validation', () => {
	it('fails when a ride points at a park that does not exist', () => {
		const collections = makeCollections({
			rides: [makeRide('nemesis', { park: 'typo-towers' })],
			parks: [makePark('alton-towers')]
		});

		expect(() => buildGraph(collections, PUBLISHED)).toThrow(ContentError);
		expect(() => buildGraph(collections, PUBLISHED)).toThrow(/content\/parks\/typo-towers\.md/);
	});

	it('fails when a ride points at a pairing that does not exist', () => {
		const collections = makeCollections({
			rides: [
				makeRide('nemesis', {
					park: 'a-park',
					pairings: [{ pairing: 'missing-film', blurb: 'Watch it.', strength: 3 }]
				})
			],
			parks: [makePark('a-park')]
		});

		try {
			buildGraph(collections, PUBLISHED);
			expect.unreachable('should have thrown');
		} catch (error) {
			const [problem] = (error as ContentError).problems;
			expect(problem.field).toBe('pairings.0.pairing');
			expect(problem.path).toBe('content/rides/nemesis.md');
		}
	});

	it('fails when a ride lists the same pairing twice', () => {
		const collections = makeCollections({
			rides: [
				makeRide('nemesis', {
					pairings: [
						{ pairing: 'a-film', blurb: 'One.', strength: 3 },
						{ pairing: 'a-film', blurb: 'Two.', strength: 4 }
					]
				})
			],
			parks: [makePark('a-park')],
			pairings: [makePairing('a-film')]
		});

		expect(() => buildGraph(collections, PUBLISHED)).toThrow(/more than once/);
	});

	it('validates drafts too, so a typo cannot hide until publication', () => {
		const collections = makeCollections({
			rides: [makeRide('nemesis', { draft: true, park: 'typo-towers' })],
			parks: [makePark('a-park')]
		});

		expect(() => buildGraph(collections, PUBLISHED)).toThrow(ContentError);
	});
});

describe('buildGraph reverse lookups', () => {
	const collections = makeCollections({
		parks: [makePark('alton-towers'), makePark('blackpool')],
		pairings: [
			makePairing('a-film', { pairingType: 'watch', title: 'A Film' }),
			makePairing('a-spa', { pairingType: 'palate-cleanser', title: 'A Spa' })
		],
		rides: [
			makeRide('nemesis', {
				name: 'Nemesis',
				park: 'alton-towers',
				pairings: [
					{ pairing: 'a-film', blurb: 'Watch it.', strength: 5 },
					{ pairing: 'a-spa', blurb: 'Book it.', strength: 2 }
				]
			}),
			makeRide('big-one', {
				name: 'Big One',
				park: 'blackpool',
				pairings: [{ pairing: 'a-spa', blurb: 'Your back will ask.', strength: 4 }]
			})
		]
	});

	it('groups rides by park', () => {
		const graph = buildGraph(collections, PUBLISHED);

		expect(ridesForPark(graph, 'alton-towers').map((ride) => ride.id)).toEqual(['nemesis']);
		expect(ridesForPark(graph, 'blackpool').map((ride) => ride.id)).toEqual(['big-one']);
	});

	it('lists every ride a pairing appears on, strongest match first', () => {
		const graph = buildGraph(collections, PUBLISHED);
		const paired = ridesForPairing(graph, 'a-spa');

		expect(paired.map((entry) => entry.ride.id)).toEqual(['big-one', 'nemesis']);
		expect(paired.map((entry) => entry.strength)).toEqual([4, 2]);
		expect(paired[0].blurb).toBe('Your back will ask.');
	});

	it('returns an empty list for a pairing nothing uses yet', () => {
		const graph = buildGraph(
			makeCollections({ ...collections, pairings: [...collections.pairings, makePairing('unused')] }),
			PUBLISHED
		);

		expect(ridesForPairing(graph, 'unused')).toEqual([]);
	});

	it('orders a ride pairings like a tasting flight, not like the file', () => {
		const graph = buildGraph(collections, PUBLISHED);
		const flight = pairingsForRide(graph, graph.rideById.get('nemesis')!);

		// palate-cleanser comes before watch, whatever order the file used.
		expect(flight.map((entry) => entry.pairing.pairingType)).toEqual(['palate-cleanser', 'watch']);
		expect(flight[0].blurb).toBe('Book it.');
	});

	it('resolves a ride park', () => {
		const graph = buildGraph(collections, PUBLISHED);

		expect(parkForRide(graph, graph.rideById.get('nemesis')!).id).toBe('alton-towers');
	});

	it('sorts rides by name', () => {
		const graph = buildGraph(collections, PUBLISHED);

		expect(graph.rides.map((ride) => ride.id)).toEqual(['big-one', 'nemesis']);
	});
});

describe('buildGraph draft handling', () => {
	it('excludes draft entries from a published build and keeps them in dev', () => {
		const collections = makeCollections({
			parks: [makePark('a-park')],
			rides: [makeRide('published'), makeRide('unfinished', { draft: true })]
		});

		expect(buildGraph(collections, PUBLISHED).rides.map((ride) => ride.id)).toEqual(['published']);
		expect(buildGraph(collections, EVERYTHING).rides.map((ride) => ride.id)).toEqual([
			'published',
			'unfinished'
		]);
	});

	it('prunes references to a draft pairing instead of leaving a dead link', () => {
		const collections = makeCollections({
			parks: [makePark('a-park')],
			pairings: [makePairing('solid'), makePairing('unverified', { draft: true })],
			rides: [
				makeRide('nemesis', {
					pairings: [
						{ pairing: 'solid', blurb: 'Yes.', strength: 4 },
						{ pairing: 'unverified', blurb: 'Maybe.', strength: 4 }
					]
				})
			]
		});

		const published = buildGraph(collections, PUBLISHED);
		expect(published.rides[0].pairings.map((ref) => ref.pairing)).toEqual(['solid']);
		expect(published.pairingById.has('unverified')).toBe(false);

		const dev = buildGraph(collections, EVERYTHING);
		expect(dev.rides[0].pairings).toHaveLength(2);
	});

	it('drops a ride whose park is still a draft', () => {
		const collections = makeCollections({
			parks: [makePark('unannounced', { draft: true })],
			rides: [makeRide('secret', { park: 'unannounced' })]
		});

		expect(buildGraph(collections, PUBLISHED).rides).toEqual([]);
		expect(buildGraph(collections, EVERYTHING).rides).toHaveLength(1);
	});
});
