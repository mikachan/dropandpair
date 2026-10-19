<script lang="ts">
	import PairingCard from '$lib/components/PairingCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import { pairingTypeDescriptions, pairingTypeLabels } from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	const all = $derived(data.moments.flatMap((moment) => moment.pairings));
</script>

<Seo
	title="Pairings"
	description="Everything I've paired with a ride, grouped by moment: before, right after, wind down, later and the rest."
	path="/pairings"
	jsonLd={jsonld.graph(
		jsonld.collection(
			'Pairings',
			'/pairings',
			all.map((pairing) => ({ name: pairing.title, path: `/pairings/${pairing.id}` }))
		),
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Pairings', path: '/pairings' }
		])
	)}
/>

<div class="page section pairings">
	<header class="pairings__header">
		<p class="eyebrow">The list</p>
		<h1>Pairings</h1>
		<p class="lede">
			{data.total} things worth putting either side of a ride, grouped by moment.
		</p>

		{#if data.moments.length > 1}
			<nav aria-label="Moments">
				<ul class="tag-list">
					{#each data.moments as moment (moment.type)}
						<li><a class="jump" href="#{moment.type}">{pairingTypeLabels[moment.type]}</a></li>
					{/each}
				</ul>
			</nav>
		{/if}
	</header>

	{#each data.moments as moment (moment.type)}
		<section class="moment" id={moment.type}>
			<h2>{pairingTypeLabels[moment.type]}</h2>
			<hr class="rule" />
			<p class="muted moment__description">{pairingTypeDescriptions[moment.type]}</p>
			<ul class="card-grid">
				{#each moment.pairings as pairing (pairing.id)}
					<li><PairingCard {pairing} showType={false} /></li>
				{/each}
			</ul>
		</section>
	{:else}
		<p class="muted">No pairings yet. Add one in <code>content/pairings/</code>.</p>
	{/each}
</div>

<style>
	.pairings {
		display: flex;
		flex-direction: column;
		gap: var(--space-l);
	}

	.pairings__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.jump {
		display: inline-block;
		padding: 0.15em 0.6em;
		border: 1px solid var(--line-strong);
		border-radius: var(--radius-pill);
		font-size: var(--step--1);
		text-decoration: none;
	}

	.jump:hover {
		border-color: var(--accent);
	}

	.moment h2 + .rule {
		margin-block: var(--space-2xs) var(--space-2xs);
	}

	/* Offset so an anchored heading is not tight against the viewport edge. */
	.moment {
		scroll-margin-block-start: var(--space-m);
	}

	.moment__description {
		font-size: var(--step--1);
		margin-block-end: var(--space-s);
	}
</style>
