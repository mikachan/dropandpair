import { PAIRING_TYPES } from '$lib/content/constants';
import { graph } from '$lib/content/load.server';
import { toPairingSummary } from '$lib/content/view';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = () => {
	// Grouped by course, in course order, with an anchor per group. This is the
	// browse-by-type route, and it needs no JavaScript to work.
	const courses = PAIRING_TYPES.map((type) => ({
		type,
		pairings: graph.pairings
			.filter((pairing) => pairing.pairingType === type)
			.map((pairing) => toPairingSummary(graph, pairing))
	})).filter((course) => course.pairings.length > 0);

	return { courses, total: graph.pairings.length };
};
