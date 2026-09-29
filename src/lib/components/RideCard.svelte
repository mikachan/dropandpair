<script lang="ts">
	import { bodyLabel, rideTypeLabels } from '$lib/content/labels';
	import type { RideSummary } from '$lib/content/view';
	import PairingBadge from './PairingBadge.svelte';

	interface Props {
		ride: RideSummary;
		/** Use h2 on pages where the card is a top-level list item. */
		headingLevel?: 2 | 3;
	}

	let { ride, headingLevel = 3 }: Props = $props();
</script>

<article class="ride-card">
	<p class="eyebrow">{rideTypeLabels[ride.rideType]} · {ride.opened}</p>

	<svelte:element this={`h${headingLevel}`} class="ride-card__name">
		<a href="/rides/{ride.id}">{ride.name}</a>
	</svelte:element>

	<p class="ride-card__park muted">
		{ride.park.name} · {ride.park.country}
	</p>

	<p class="ride-card__note">{ride.tastingNote}</p>

	<p class="ride-card__body">
		<span class="muted">{bodyLabel(ride.intensity)}</span>
		{#if ride.pairingCount > 0}
			<span aria-hidden="true" class="muted"> · </span>
			<span class="muted">{ride.pairingCount} pairing{ride.pairingCount === 1 ? '' : 's'}</span>
		{/if}
	</p>

	{#if ride.pairingTypes.length > 0}
		<ul class="tag-list">
			{#each ride.pairingTypes as type (type)}
				<li><PairingBadge {type} /></li>
			{/each}
		</ul>
	{/if}

	{#if ride.draft}
		<p class="draft-flag">Draft — not yet verified</p>
	{/if}
</article>

<style>
	.ride-card {
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

	.ride-card__name {
		font-size: var(--step-1);
	}

	.ride-card__name a {
		color: var(--ink);
		text-decoration: none;
	}

	.ride-card__name a:hover {
		color: var(--accent);
		text-decoration: underline;
	}

	.ride-card__park {
		font-size: var(--step--1);
	}

	/* The tasting note is the point of the card, so it gets the label voice. */
	.ride-card__note {
		font-family: var(--font-display);
		font-size: var(--step-0);
		font-style: italic;
		padding-block: var(--space-3xs);
		border-block: 1px solid var(--line);
	}

	.ride-card__body {
		font-size: var(--step--1);
	}

	/* Push the badges to the bottom so cards in a row line up. */
	.tag-list {
		margin-block-start: auto;
		padding-block-start: var(--space-3xs);
	}
</style>
