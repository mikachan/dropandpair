import { pairingsForRide, ridesForPark } from '$lib/content/graph';
import { graph } from '$lib/content/load.server';
import { toParkDetail, toPairingSummary, toRideSummary } from '$lib/content/view';
import { error } from '@sveltejs/kit';
import type { EntryGenerator, PageServerLoad } from './$types';

export const entries: EntryGenerator = () => graph.parks.map((park) => ({ id: park.id }));

export const load: PageServerLoad = ({ params }) => {
	const park = graph.parkById.get(params.id);
	if (!park) error(404, `No park called "${params.id}"`);

	const rides = ridesForPark(graph, park.id);

	/*
	 * Every pairing used anywhere in the park, deduplicated. Because
	 * `pairingsForRide` returns moment order, walking the rides in order and
	 * keeping first sight of each pairing gives a sensibly ordered list.
	 */
	const seen = new Set<string>();
	const pairings = rides
		.flatMap((ride) => pairingsForRide(graph, ride))
		.filter(({ pairing }) => {
			if (seen.has(pairing.id)) return false;
			seen.add(pairing.id);
			return true;
		})
		.map(({ pairing }) => toPairingSummary(graph, pairing));

	return {
		park: toParkDetail(graph, park),
		rides: rides.map((ride) => toRideSummary(graph, ride)),
		pairings
	};
};
