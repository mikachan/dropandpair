<script lang="ts">
	import PairingBadge from '$lib/components/PairingBadge.svelte';
	import RideCard from '$lib/components/RideCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import { pairingTypeDescriptions, strengthLabel } from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	const pairing = $derived(data.pairing);
</script>

<Seo
	title={pairing.title}
	description={pairing.description}
	path="/pairings/{pairing.id}"
	type="article"
	image={pairing.image}
	jsonLd={jsonld.graph(
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Pairings', path: '/pairings' },
			{ name: pairing.title, path: `/pairings/${pairing.id}` }
		])
	)}
/>

<article class="page section pairing" data-pagefind-body data-pagefind-meta="kind:Pairing">
	<header class="pairing__header">
		<p><PairingBadge type={pairing.pairingType} href="/pairings#{pairing.pairingType}" /></p>
		<h1>{pairing.title}</h1>
		<p class="lede">{pairing.description}</p>
		<p class="muted pairing__moment">{pairingTypeDescriptions[pairing.pairingType]}</p>

		{#if pairing.url}
			<p>
				<a class="button button--quiet" href={pairing.url} rel="noopener"> Find it </a>
			</p>
		{/if}

		{#if pairing.draft}
			<p class="draft-flag">Draft — not yet verified</p>
		{/if}
	</header>

	{#if pairing.image}
		<img class="pairing__image" src={pairing.image} alt="" loading="lazy" decoding="async" />
	{/if}

	{#if pairing.body}
		<div class="prose">
			<!-- Authored by hand in this repo and rendered at build time, so trusted. -->
			<!-- eslint-disable-next-line svelte/no-at-html-tags -->
			{@html pairing.body}
		</div>
	{/if}

	<section>
		<h2>Paired with</h2>
		<hr class="rule" />
		{#if data.rides.length > 0}
			<ul class="paired">
				{#each data.rides as entry (entry.ride.id)}
					<li class="paired__item">
						<blockquote class="paired__blurb">
							{entry.blurb}
							<cite class="paired__cite">
								{entry.ride.name} · {strengthLabel(entry.strength)}
							</cite>
						</blockquote>
						<RideCard ride={entry.ride} />
					</li>
				{/each}
			</ul>
		{:else}
			<p class="muted">Not paired with anything yet.</p>
		{/if}
	</section>

	<p class="pairing__back"><a href="/pairings">← All pairings</a></p>
</article>

<style>
	.pairing {
		display: flex;
		flex-direction: column;
		gap: var(--space-l);
		max-width: 60rem;
	}

	.pairing__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.pairing__moment {
		font-size: var(--step--1);
		max-width: var(--measure);
	}

	.pairing__image {
		inline-size: 100%;
		max-inline-size: 40rem;
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
	}

	h2 + .rule {
		margin-block: var(--space-2xs) var(--space-m);
	}

	.paired {
		display: grid;
		gap: var(--space-m);
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
		list-style: none;
		padding: 0;
		margin: 0;
	}

	.paired__item {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.paired__blurb {
		font-size: var(--step-0);
	}

	.paired__cite {
		display: block;
		margin-block-start: var(--space-3xs);
		font-family: var(--font-body);
		font-size: var(--step--1);
		font-style: normal;
		color: var(--ink-muted);
	}

	.pairing__back {
		font-size: var(--step--1);
	}
</style>
