<script lang="ts">
	import IntensityMeter from '$lib/components/IntensityMeter.svelte';
	import PairingCard from '$lib/components/PairingCard.svelte';
	import Seo from '$lib/components/Seo.svelte';
	import type { PairingType } from '$lib/content/constants';
	import {
		comfortLabel,
		intensityLabel,
		pairingTypeDescriptions,
		pairingTypeLabels,
		rideTypeLabels,
		statLabels
	} from '$lib/content/labels';
	import type { RidePairingView } from '$lib/content/view';
	import * as jsonld from '$lib/seo/jsonld';
	import type { PageData } from './$types';

	let { data }: { data: PageData } = $props();

	const ride = $derived(data.ride);

	/** Pairings arrive in course order, so consecutive runs are the groups. */
	const courses = $derived(
		ride.pairings.reduce<Array<{ type: PairingType; items: RidePairingView[] }>>((groups, item) => {
			const current = groups.at(-1);
			if (current && current.type === item.pairing.pairingType) current.items.push(item);
			else groups.push({ type: item.pairing.pairingType, items: [item] });
			return groups;
		}, [])
	);

	const stats = $derived(
		(['height', 'speed', 'length', 'inversions'] as const)
			.map((key) => ({ key, value: ride.stats?.[key] }))
			.filter(
				(stat): stat is { key: keyof typeof statLabels; value: number } => stat.value !== undefined
			)
	);
</script>

<Seo
	title="{ride.name}, {ride.park.name}"
	description={ride.gist}
	path="/rides/{ride.id}"
	type="article"
	jsonLd={jsonld.graph(
		jsonld.touristAttraction(
			ride,
			ride.pairings.map((entry) => entry.pairing)
		),
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'Rides', path: '/rides' },
			{ name: ride.name, path: `/rides/${ride.id}` }
		])
	)}
/>

<article class="page section ride" data-pagefind-body data-pagefind-meta="kind:Ride">
	<header class="ride__header">
		<p class="eyebrow">
			{rideTypeLabels[ride.rideType]} · <a href="/parks/{ride.park.id}">{ride.park.name}</a>
		</p>
		<h1>{ride.name}</h1>
		<p class="muted">{ride.park.region}, {ride.park.country}</p>
		{#if ride.draft}
			<p class="draft-flag">Draft — not yet verified</p>
		{/if}
	</header>

	<!-- The label: the gist, the meters and the facts at a glance. -->
	<section class="label" aria-labelledby="label-heading">
		<h2 class="visually-hidden" id="label-heading">The gist</h2>
		<p class="label__note">{ride.gist}</p>

		<div class="label__meters">
			<IntensityMeter
				label="Intensity"
				value={ride.intensity}
				text={intensityLabel(ride.intensity)}
			/>
			<IntensityMeter label="Comfort" value={ride.roughness} text={comfortLabel(ride.roughness)} />
		</div>

		<dl class="label__facts">
			<div>
				<dt>Opened</dt>
				<dd>{ride.opened}</dd>
			</div>
			<div>
				<dt>Manufacturer</dt>
				<dd>{ride.manufacturer}</dd>
			</div>
			{#each stats as stat (stat.key)}
				<div>
					<dt>{statLabels[stat.key].label}</dt>
					<dd>{stat.value}{statLabels[stat.key].unit ? ` ${statLabels[stat.key].unit}` : ''}</dd>
				</div>
			{/each}
		</dl>

		<ul class="tag-list label__vibes">
			{#each ride.vibe as vibe (vibe)}
				<li><a class="vibe" href="/rides?vibe={encodeURIComponent(vibe)}">{vibe}</a></li>
			{/each}
		</ul>
	</section>

	{#if ride.body}
		<div class="prose ride__body">
			<!-- Authored by hand in this repo and rendered at build time, so trusted. -->
			<!-- eslint-disable-next-line svelte/no-at-html-tags -->
			{@html ride.body}
		</div>
	{/if}

	<section class="ride__pairings">
		<h2>What to have with it</h2>
		<hr class="rule" />

		{#if courses.length > 0}
			{#each courses as course (course.type)}
				<section class="course">
					<h3 class="course__heading">{pairingTypeLabels[course.type]}</h3>
					<p class="muted course__description">{pairingTypeDescriptions[course.type]}</p>
					<ul class="card-grid">
						{#each course.items as item (item.pairing.id)}
							<li>
								<PairingCard
									pairing={item.pairing}
									blurb={item.blurb}
									strength={item.strength}
									showType={false}
									headingLevel={4}
								/>
							</li>
						{/each}
					</ul>
				</section>
			{/each}
		{:else}
			<p class="muted">No pairings yet. This one is still being tried out.</p>
		{/if}
	</section>

	{#if ride.furtherReading.length > 0}
		<section class="ride__reading" aria-labelledby="reading-heading">
			<h2 id="reading-heading">Further reading</h2>
			<hr class="rule" />
			<ul>
				{#each ride.furtherReading as link (link.url)}
					<li><a href={link.url} rel="noopener">{link.title}</a></li>
				{/each}
			</ul>
		</section>
	{/if}

	<p class="ride__back"><a href="/rides">← All rides</a></p>
</article>

<style>
	.ride {
		display: flex;
		flex-direction: column;
		gap: var(--space-l);
		max-width: 60rem;
	}

	.ride__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.label {
		display: flex;
		flex-direction: column;
		gap: var(--space-m);
		padding: var(--space-m);
		border: 1px solid var(--line-strong);
		border-radius: var(--radius-lg);
		background: var(--surface);
		box-shadow: var(--shadow);
	}

	.label__note {
		font-family: var(--font-display);
		font-size: var(--step-2);
		font-style: italic;
		line-height: 1.3;
		text-wrap: balance;
	}

	.label__meters {
		display: grid;
		gap: var(--space-m);
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr));
		padding-block: var(--space-s);
		border-block: 3px double var(--line-strong);
	}

	.label__facts {
		display: grid;
		gap: var(--space-s);
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
		margin: 0;
	}

	.label__facts dt {
		font-size: var(--step--2);
		font-weight: 600;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: var(--ink-muted);
	}

	.label__facts dd {
		margin: 0;
		font-family: var(--font-display);
		font-size: var(--step-0);
	}

	.vibe {
		display: inline-block;
		padding: 0.15em 0.6em;
		border: 1px solid var(--line-strong);
		border-radius: var(--radius-pill);
		color: var(--ink);
		font-size: var(--step--1);
		text-decoration: none;
	}

	.vibe:hover {
		border-color: var(--accent);
		color: var(--accent);
	}

	.ride__pairings h2 + .rule {
		margin-block: var(--space-2xs) var(--space-m);
	}

	.course {
		margin-block-end: var(--space-l);
	}

	.course__heading {
		font-size: var(--step-1);
	}

	.course__description {
		font-size: var(--step--1);
		margin-block-end: var(--space-s);
	}

	.course:last-child {
		margin-block-end: 0;
	}

	.ride__reading h2 + .rule {
		margin-block: var(--space-2xs) var(--space-s);
	}

	.ride__reading ul {
		display: flex;
		flex-direction: column;
		gap: var(--space-3xs);
		padding-inline-start: var(--space-s);
	}

	.ride__back {
		font-size: var(--step--1);
	}
</style>
