<script lang="ts">
	import Seo from '$lib/components/Seo.svelte';
	import * as jsonld from '$lib/seo/jsonld';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<Seo
	title="Parks"
	description="Every park with a ride in the cellar."
	path="/parks"
	jsonLd={jsonld.graph(
		jsonld.collection(
			'Parks',
			'/parks',
			data.parks.map((park) => ({ name: park.name, path: `/parks/${park.id}` }))
		),
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Parks', path: '/parks' }
		])
	)}
/>

<div class="page section parks">
	<header class="parks__header">
		<p class="eyebrow">The estates</p>
		<h1>Parks</h1>
		<p class="lede">Where the rides come from.</p>
	</header>

	{#if data.parks.length > 0}
		<ul class="card-grid">
			{#each data.parks as park (park.id)}
				<li>
					<article class="park-card">
						<p class="eyebrow">{park.region} · {park.country}</p>
						<h2 class="park-card__name"><a href="/parks/{park.id}">{park.name}</a></h2>
						<p class="muted">
							{park.rideCount} ride{park.rideCount === 1 ? '' : 's'} in the cellar
						</p>
						{#if park.draft}
							<p class="draft-flag">Draft — not yet verified</p>
						{/if}
					</article>
				</li>
			{/each}
		</ul>
	{:else}
		<p class="muted">No parks yet. Add one in <code>content/parks/</code>.</p>
	{/if}
</div>

<style>
	.parks {
		display: flex;
		flex-direction: column;
		gap: var(--space-m);
	}

	.parks__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.park-card {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
		block-size: 100%;
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
		box-shadow: var(--shadow);
	}

	.park-card__name {
		font-size: var(--step-1);
	}

	.park-card__name a {
		color: var(--ink);
		text-decoration: none;
	}

	.park-card__name a:hover {
		color: var(--accent);
		text-decoration: underline;
	}
</style>
