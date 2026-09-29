import { graph } from '$lib/content/load.server';
import { ridesForPairing } from '$lib/content/graph';
import { toPairingDetail, toRideSummary } from '$lib/content/view';
import { error } from '@sveltejs/kit';
import type { EntryGenerator, PageServerLoad } from './$types';

export const entries: EntryGenerator = () => graph.pairings.map((pairing) => ({ id: pairing.id }));

export const load: PageServerLoad = ({ params }) => {
	const pairing = graph.pairingById.get(params.id);
	if (!pairing) error(404, `No pairing called "${params.id}"`);

	// The reverse of the relationship authored on each ride.
	const rides = ridesForPairing(graph, pairing.id).map(({ ride, blurb, strength }) => ({
		ride: toRideSummary(graph, ride),
		blurb,
		strength
	}));

	return { pairing: toPairingDetail(graph, pairing), rides };
};
