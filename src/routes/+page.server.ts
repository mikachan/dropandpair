import { PAIRING_TYPES } from '$lib/content/constants';
import { graph } from '$lib/content/load.server';
import { toRideSummary } from '$lib/content/view';
import type { PageServerLoad } from './$types';

/** How many rides the front page shelf holds. */
const SHELF_SIZE = 6;

export const load: PageServerLoad = () => {
	const featured = graph.rides.filter((ride) => ride.featured);
	// Before anything is marked featured, show whatever is on the list so the
	// front page is never empty.
	const shelf = (featured.length > 0 ? featured : graph.rides).slice(0, SHELF_SIZE);

	const pairingTypes = PAIRING_TYPES.map((type) => ({
		type,
		count: graph.pairings.filter((pairing) => pairing.pairingType === type).length
	})).filter((entry) => entry.count > 0);

	return {
		featured: shelf.map((ride) => toRideSummary(graph, ride)),
		pairingTypes,
		totals: {
			rides: graph.rides.length,
			parks: graph.parks.length,
			pairings: graph.pairings.length
		}
	};
};
