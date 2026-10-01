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
	<div class="page hero__inner">
		<p class="eyebrow">Curated pairings for theme park rides</p>
		<h1>{site.tagline}</h1>
		<p class="lede">
			Every ride gets rated for how hard it hits and how you feel walking off. We tell you what to
			watch, eat, read and do either side of it — and which rides deserve a spa afterwards.
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
	<h2>Browse by course</h2>
	<hr class="rule" />
	<p class="lede">
		Pairings come in courses, from something before you queue to something for later, once the car
		park has emptied.
	</p>
	<ul class="course-grid">
		{#each data.pairingTypes as entry (entry.type)}
			<li>
				<a class="course" href="/pairings#{entry.type}">
					<span class="course__name">{pairingTypeLabels[entry.type]}</span>
					<span class="course__description muted">{pairingTypeDescriptions[entry.type]}</span>
					<span class="course__count muted">
						{entry.count} pairing{entry.count === 1 ? '' : 's'}
					</span>
				</a>
			</li>
		{/each}
	</ul>
</section>

<style>
	.hero {
		border-block-end: 1px solid var(--line);
		background: var(--accent-wash);
	}

	.hero__inner {
		display: flex;
		flex-direction: column;
		gap: var(--space-s);
		padding-block: var(--space-xl);
		max-width: 52rem;
		margin-inline: auto;
		text-align: center;
	}

	.hero h1 {
		font-style: italic;
	}

	.hero .lede {
		margin-inline: auto;
		color: var(--ink);
	}

	.hero__actions {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs);
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

	.course-grid {
		display: grid;
		gap: var(--space-s);
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
		list-style: none;
		padding: 0;
		margin-block-start: var(--space-m);
	}

	.course {
		display: flex;
		flex-direction: column;
		gap: var(--space-3xs);
		block-size: 100%;
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
		color: var(--ink);
		text-decoration: none;
		transition: border-color var(--transition);
	}

	.course:hover {
		border-color: var(--accent);
	}

	.course__name {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	.course:hover .course__name {
		color: var(--accent);
	}

	.course__description {
		font-size: var(--step--1);
	}

	.course__count {
		margin-block-start: auto;
		padding-block-start: var(--space-2xs);
		font-size: var(--step--2);
		letter-spacing: 0.08em;
		text-transform: uppercase;
	}
</style>
