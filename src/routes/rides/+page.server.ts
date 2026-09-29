import { graph } from '$lib/content/load.server';
import { toRideSummary } from '$lib/content/view';
import { buildFacets } from '$lib/rides/filter';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = () => {
	// The whole list is sent once and filtered in the browser. It is a page of
	// summaries, not write-ups, so the payload stays small.
	const rides = graph.rides.map((ride) => toRideSummary(graph, ride));

	return { rides, facets: buildFacets(rides) };
};
