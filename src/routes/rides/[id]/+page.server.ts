import { graph } from '$lib/content/load.server';
import { toRideDetail } from '$lib/content/view';
import { error } from '@sveltejs/kit';
import type { EntryGenerator, PageServerLoad } from './$types';

// Listed explicitly rather than relying on the crawler, so a ride that is not
// linked from anywhere still gets a page.
export const entries: EntryGenerator = () => graph.rides.map((ride) => ({ id: ride.id }));

export const load: PageServerLoad = ({ params }) => {
	const ride = graph.rideById.get(params.id);
	if (!ride) error(404, `No ride called "${params.id}"`);

	return { ride: toRideDetail(graph, ride) };
};
