import { graph } from '$lib/content/load.server';
import { toParkSummary } from '$lib/content/view';
import type { PageServerLoad } from './$types';

export const load: PageServerLoad = () => ({
	parks: graph.parks.map((park) => toParkSummary(graph, park))
});
