<script lang="ts">
	import { browser } from '$app/environment';
	import { replaceState } from '$app/navigation';
	import { page } from '$app/state';
	import FilterBar from '$lib/components/FilterBar.svelte';
	import RideCard from '$lib/components/RideCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import * as jsonld from '$lib/seo/jsonld';
	import {
		EMPTY_FILTERS,
		filterRides,
		isFiltered,
		parseFilters,
		toSearchParams,
		type Filters
	} from '$lib/rides/filter';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	/*
	 * The URL is the shareable record of a filtered view.
	 *
	 * Prerendering cannot depend on the query string — one file is generated for
	 * /rides, whatever the params — so the prerendered page is the full list,
	 * which is also exactly what a visitor without JavaScript should get. Only
	 * in the browser is the URL read and the list narrowed.
	 *
	 * A writable $derived gives both halves at once: `apply` assigns to it so a
	 * filter change is instant rather than waiting on navigation, and it falls
	 * back to recomputing from the URL whenever the URL changes underneath us —
	 * following the "Clear filters" link, or using the back button.
	 */
	let filters = $derived<Filters>(browser ? parseFilters(page.url.searchParams) : EMPTY_FILTERS);

	const visible = $derived(filterRides(data.rides, filters));

	function apply(next: Filters) {
		filters = next;
		const query = toSearchParams(next).toString();
		// Shallow, so the URL updates without re-running the load function.
		replaceState(query ? `?${query}` : page.url.pathname, page.state);
	}
</script>

<Seo
	title="Rides"
	description="Every ride on the list, filterable by type, pairing, intensity, vibe and country."
	path="/rides"
	jsonLd={jsonld.graph(
		jsonld.collection(
			'Rides',
			'/rides',
			data.rides.map((ride) => ({ name: ride.name, path: `/rides/${ride.id}` }))
		),
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Rides', path: '/rides' }
		])
	)}
/>

<div class="page section stack">
	<header class="stack">
		<p class="eyebrow">The list</p>
		<h1>Rides</h1>
		<p class="lede">
			Sorted by name. Intensity is how hard it hits, comfort is how you feel walking off.
		</p>
	</header>

	<FilterBar
		facets={data.facets}
		{filters}
		onchange={apply}
		resultCount={visible.length}
		totalCount={data.rides.length}
	/>

	{#if visible.length > 0}
		<ul class="card-grid">
			{#each visible as ride (ride.id)}
				<li><RideCard {ride} headingLevel={2} /></li>
			{/each}
		</ul>
	{:else}
		<p class="empty">
			Nothing on the list matches that combination.
			{#if isFiltered(filters)}
				<a href="/rides">Clear the filters</a> and start again.
			{/if}
		</p>
	{/if}
</div>

<style>
	.stack {
		--stack-gap: var(--space-m);
	}

	header {
		--stack-gap: var(--space-2xs);
	}

	.empty {
		padding: var(--space-l) var(--space-s);
		border: 1px dashed var(--line-strong);
		border-radius: var(--radius-lg);
		text-align: center;
		color: var(--ink-muted);
	}
</style>
