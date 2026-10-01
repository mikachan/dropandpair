<script lang="ts">
	import PairingCard from '$lib/components/PairingCard.svelte';
	import RideCard from '$lib/components/RideCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import * as jsonld from '$lib/seo/jsonld';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	const park = $derived(data.park);
</script>

<Seo
	title="{park.name}, {park.country}"
	description="The rides we have tried at {park.name}, and what to have with them."
	path="/parks/{park.id}"
	jsonLd={jsonld.graph(
		jsonld.amusementPark(park, data.rides.length),
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Parks', path: '/parks' },
			{ name: park.name, path: `/parks/${park.id}` }
		])
	)}
/>

<article class="page section park" data-pagefind-body data-pagefind-meta="kind:Park">
	<header class="park__header">
		<p class="eyebrow">{park.region} · {park.country}</p>
		<h1>{park.name}</h1>
		{#if park.website}
			<p><a href={park.website} rel="noopener">Official site</a></p>
		{/if}
		{#if park.draft}
			<p class="draft-flag">Draft — not yet verified</p>
		{/if}
	</header>

	{#if park.body}
		<div class="prose">
			<!-- Authored by hand in this repo and rendered at build time, so trusted. -->
			<!-- eslint-disable-next-line svelte/no-at-html-tags -->
			{@html park.body}
		</div>
	{/if}

	<section>
		<h2>Rides</h2>
		<hr class="rule" />
		{#if data.rides.length > 0}
			<ul class="card-grid">
				{#each data.rides as ride (ride.id)}
					<li><RideCard {ride} /></li>
				{/each}
			</ul>
		{:else}
			<p class="muted">Nothing tried here yet.</p>
		{/if}
	</section>

	{#if data.pairings.length > 0}
		<section>
			<h2>Pairings from this park</h2>
			<hr class="rule" />
			<p class="muted section__note">
				Everything paired with a ride here, across {data.rides.length} ride{data.rides.length === 1
					? ''
					: 's'}.
			</p>
			<ul class="card-grid">
				{#each data.pairings as pairing (pairing.id)}
					<li><PairingCard {pairing} /></li>
				{/each}
			</ul>
		</section>
	{/if}

	<p class="park__back"><a href="/parks">← All parks</a></p>
</article>

<style>
	.park {
		display: flex;
		flex-direction: column;
		gap: var(--space-l);
	}

	.park__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	h2 + .rule {
		margin-block: var(--space-2xs) var(--space-m);
	}

	.section__note {
		font-size: var(--step--1);
		margin-block-end: var(--space-s);
	}

	.park__back {
		font-size: var(--step--1);
	}
</style>
