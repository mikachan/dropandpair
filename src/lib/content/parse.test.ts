import { describe, expect, it } from 'vitest';
import { ContentError, parseCollections, type RawFile } from './parse';

const validRide = `---
id: nemesis
name: Nemesis
park: alton-towers
rideType: coaster
opened: 1994
manufacturer: Bolliger & Mabillard
intensity: 5
roughness: 2
vibe:
  - brutal
  - scenic
tastingNote: Notes of airtime, a hint of headbanging.
pairings:
  - pairing: a-film
    blurb: Watch it afterwards.
    strength: 4
---

A **longer** write-up.
`;

function ride(raw: string, path = 'content/rides/nemesis.md'): RawFile[] {
	return [{ path, raw }];
}

function parseRides(files: RawFile[]) {
	return parseCollections({ rides: files, parks: [], pairings: [] }).rides;
}

describe('parseCollections', () => {
	it('parses frontmatter and renders the body to HTML', () => {
		const [entry] = parseRides(ride(validRide));

		expect(entry.id).toBe('nemesis');
		expect(entry.name).toBe('Nemesis');
		expect(entry.opened).toBe(1994);
		expect(entry.vibe).toEqual(['brutal', 'scenic']);
		expect(entry.pairings).toEqual([
			{ pairing: 'a-film', blurb: 'Watch it afterwards.', strength: 4 }
		]);
		expect(entry.body).toBe('<p>A <strong>longer</strong> write-up.</p>');
		expect(entry.sourcePath).toBe('content/rides/nemesis.md');
	});

	it('applies defaults for omitted optional fields', () => {
		const raw = validRide.replace(/pairings:\n(?: .*\n)*/, '');
		const [entry] = parseRides(ride(raw));

		expect(entry.pairings).toEqual([]);
		expect(entry.draft).toBe(false);
		expect(entry.featured).toBe(false);
		expect(entry.stats).toBeUndefined();
	});

	it('handles a file with no body', () => {
		const raw = validRide.split('---')[1];
		const [entry] = parseRides(ride(`---${raw}---\n`));

		expect(entry.body).toBe('');
	});

	it('names the file and the field when a required field is missing', () => {
		const raw = validRide.replace('park: alton-towers\n', '');

		expect(() => parseRides(ride(raw))).toThrow(ContentError);
		expect(() => parseRides(ride(raw))).toThrow(/content\/rides\/nemesis\.md/);
		expect(() => parseRides(ride(raw))).toThrow(/park/);
	});

	it('rejects a value outside the 1-5 scale', () => {
		const raw = validRide.replace('intensity: 5', 'intensity: 9');

		expect(() => parseRides(ride(raw))).toThrow(/intensity/);
	});

	it('rejects an unknown rideType', () => {
		const raw = validRide.replace('rideType: coaster', 'rideType: log-flume');

		expect(() => parseRides(ride(raw))).toThrow(/rideType/);
	});

	it('rejects a frontmatter key that is not in the schema, to catch typos', () => {
		const raw = validRide.replace('intensity: 5', 'intensitty: 5\nintensity: 5');

		expect(() => parseRides(ride(raw))).toThrow(/intensitty/);
	});

	it('requires the id to match the file name', () => {
		expect(() => parseRides(ride(validRide, 'content/rides/nemesis-inferno.md'))).toThrow(
			/file name/
		);
	});

	it('rejects a duplicate id', () => {
		const files = [
			{ path: 'content/rides/nemesis.md', raw: validRide },
			{ path: 'content/rides/copy.md', raw: validRide.replace('id: nemesis', 'id: copy') }
		];
		const duplicate = files[1].raw.replace('id: copy', 'id: nemesis');

		expect(() =>
			parseRides([files[0], { path: 'content/rides/nemesis.md', raw: duplicate }])
		).toThrow(/duplicates/);
	});

	it('reports every problem at once rather than stopping at the first', () => {
		const raw = validRide.replace('park: alton-towers\n', '').replace('intensity: 5', 'intensity: 9');

		try {
			parseRides(ride(raw));
			expect.unreachable('should have thrown');
		} catch (error) {
			expect(error).toBeInstanceOf(ContentError);
			const fields = (error as ContentError).problems.map((problem) => problem.field);
			expect(fields).toContain('park');
			expect(fields).toContain('intensity');
		}
	});

	it('rejects a remote image on a pairing', () => {
		const raw = `---
id: a-film
title: A Film
pairingType: watch
description: A description.
image: https://example.com/poster.jpg
---
`;

		expect(() =>
			parseCollections({
				rides: [],
				parks: [],
				pairings: [{ path: 'content/pairings/a-film.md', raw }]
			})
		).toThrow(/local path/);
	});

	it('accepts a local image on a pairing', () => {
		const raw = `---
id: a-film
title: A Film
pairingType: watch
description: A description.
image: /images/poster.jpg
---
`;
		const { pairings } = parseCollections({
			rides: [],
			parks: [],
			pairings: [{ path: 'content/pairings/a-film.md', raw }]
		});

		expect(pairings[0].image).toBe('/images/poster.jpg');
		expect(pairings[0].affiliate).toBe(false);
	});
});
