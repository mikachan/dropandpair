import { PAIRING_TYPES } from '$lib/content/constants';
import { graph } from '$lib/content/load.server';
import { toPairingSummary } from '$lib/content/view';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = () => {
	// Grouped by moment, in moment order, with an anchor per group. This is the
	// browse-by-type route, and it needs no JavaScript to work.
	const moments = PAIRING_TYPES.map((type) => ({
		type,
		pairings: graph.pairings
			.filter((pairing) => pairing.pairingType === type)
			.map((pairing) => toPairingSummary(graph, pairing))
	})).filter((moment) => moment.pairings.length > 0);

	return { moments, total: graph.pairings.length };
};
