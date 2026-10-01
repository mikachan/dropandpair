<script lang="ts">
	import RideCard from '$lib/components/RideCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import { pairingTypeDescriptions, pairingTypeLabels } from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import { site } from '$lib/site';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();
</script>

<Seo
	title={site.name}
	description={site.description}
	path="/"
	jsonLd={jsonld.graph(jsonld.website())}
/>

<section class="hero">
	<div class="page hero__inner marquee">
		<p class="eyebrow">Curated pairings for theme park rides</p>
		<h1>{site.tagline}</h1>
		<p class="lede">
			Rides are over in minutes, so let's make them last. For every ride there's something to read, watch or listen to before you go, and something to help you remember them by afterwards.
		</p>
		<p class="hero__actions">
			<a class="button" href="/rides">Browse the rides</a>
			<a class="button button--quiet" href="/about">How this works</a>
		</p>
		<p class="hero__stats muted">
			{data.totals.rides} rides · {data.totals.parks} parks · {data.totals.pairings} pairings
		</p>
	</div>
</section>

<section class="page section">
	<h2>Worth a ride</h2>
	<hr class="rule" />
	{#if data.featured.length > 0}
		<ul class="card-grid">
			{#each data.featured as ride (ride.id)}
				<li><RideCard {ride} /></li>
			{/each}
		</ul>
	{:else}
		<p class="muted">The list is empty. Add a ride in <code>content/rides/</code>.</p>
	{/if}
</section>

<section class="page section">
	<h2>Browse by moment</h2>
	<hr class="rule" />
	<p class="lede">
		Every pairing has its moment, from something before you queue to something for later, once the
		car park has emptied.
	</p>
	<ul class="moment-grid">
		{#each data.pairingTypes as entry (entry.type)}
			<li>
				<a class="moment ticket" href="/pairings#{entry.type}">
					<span class="moment__name">{pairingTypeLabels[entry.type]}</span>
					<span class="moment__description muted">{pairingTypeDescriptions[entry.type]}</span>
					<span class="moment__count muted ticket__stub">
						{entry.count} pairing{entry.count === 1 ? '' : 's'}
					</span>
				</a>
			</li>
		{/each}
	</ul>
</section>

<style>
	/* Big-top stripes behind a solid poster panel, so the text never sits on them. */
	.hero {
		padding: var(--space-xl) var(--space-s);
		background: repeating-linear-gradient(90deg, var(--stripe) 0 2.5rem, var(--paper) 2.5rem 5rem);
	}

	.hero__inner {
		display: flex;
		flex-direction: column;
		gap: var(--space-m);
		/* Keep the text well clear of the marquee bulbs, without crowding phones. */
		padding: var(--space-xl) clamp(var(--space-l), 6vw, var(--space-2xl));
		max-width: 56rem;
		margin-inline: auto;
		border: 3px solid var(--ink);
		border-radius: var(--radius-lg);
		background: var(--surface);
		box-shadow: 6px 6px 0 var(--offset-colour);
		text-align: center;
	}

	/*
	 * Marquee bulbs round the panel: a dotted ring of light drawn on an inset
	 * pseudo-element, so it adds nothing to the accessibility tree.
	 */
	.marquee {
		position: relative;
	}

	.marquee::before {
		content: '';
		position: absolute;
		inset: 8px;
		border-radius: calc(var(--radius-lg) - 2px);
		pointer-events: none;
		background:
			radial-gradient(circle, var(--bulb) 3px, transparent 3.5px) 0 0 / 22px 22px repeat-x,
			radial-gradient(circle, var(--bulb) 3px, transparent 3.5px) 0 100% / 22px 22px repeat-x,
			radial-gradient(circle, var(--bulb) 3px, transparent 3.5px) 0 0 / 22px 22px repeat-y,
			radial-gradient(circle, var(--bulb) 3px, transparent 3.5px) 100% 0 / 22px 22px repeat-y;
		filter: drop-shadow(0 0 3px var(--bulb-glow));
	}

	.hero h1 {
		font-size: var(--step-4);
		letter-spacing: 0.02em;
		text-transform: uppercase;
		color: var(--accent);
	}

	.hero .lede {
		margin-inline: auto;
		color: var(--ink);
	}

	.hero__actions {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-s);
		justify-content: center;
	}

	.hero__stats {
		font-size: var(--step--1);
		letter-spacing: 0.06em;
		text-transform: uppercase;
	}

	.section h2 + .rule {
		margin-block: var(--space-2xs) var(--space-m);
	}

	.moment-grid {
		display: grid;
		gap: var(--space-s);
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
		list-style: none;
		padding: 0;
		margin-block-start: var(--space-m);
	}

	.moment {
		display: flex;
		flex-direction: column;
		gap: var(--space-3xs);
		block-size: 100%;
		padding: var(--space-s) var(--space-m);
		color: var(--ink);
		text-decoration: none;
	}

	.moment:hover {
		border-color: var(--accent);
	}

	.moment__name {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	.moment:hover .moment__name {
		color: var(--accent);
	}

	.moment__description {
		font-size: var(--step--1);
	}

	.moment__count {
		font-size: var(--step--2);
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}
</style>
