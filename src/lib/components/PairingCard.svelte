<script lang="ts">
	import { strengthLabel } from '$lib/content/labels';
	import type { PairingSummary } from '$lib/content/view';
	import PairingBadge from './PairingBadge.svelte';

	interface Props {
		pairing: PairingSummary;
		/** This ride's take on the pairing. Shown instead of the general description. */
		blurb?: string;
		/** How well it suits this ride, 1-5. */
		strength?: number;
		/** Hide the badge where the card already sits under a type heading. */
		showType?: boolean;
		headingLevel?: 2 | 3 | 4;
	}

	let { pairing, blurb, strength, showType = true, headingLevel = 3 }: Props = $props();
</script>

<article class="pairing-card">
	{#if pairing.image}
		<!-- Images are optional everywhere; the card is designed to work without one. -->
		<img class="pairing-card__image" src={pairing.image} alt="" loading="lazy" decoding="async" />
	{/if}

	<div class="pairing-card__body">
		{#if showType}
			<p><PairingBadge type={pairing.pairingType} href="/pairings#{pairing.pairingType}" /></p>
		{/if}

		<svelte:element this={`h${headingLevel}`} class="pairing-card__title">
			<a href="/pairings/{pairing.id}">{pairing.title}</a>
		</svelte:element>

		<p class="pairing-card__note">{blurb ?? pairing.description}</p>

		<p class="pairing-card__meta muted">
			{#if strength !== undefined}
				<span>{strengthLabel(strength)}</span>
				<span aria-hidden="true"> · </span>
			{/if}
			<span>
				{pairing.rideCount} ride{pairing.rideCount === 1 ? '' : 's'}
			</span>
			{#if pairing.url}
				<span aria-hidden="true"> · </span>
				<a
					class="pairing-card__link"
					href={pairing.url}
					rel={pairing.affiliate ? 'nofollow sponsored noopener' : 'noopener'}
				>
					{pairing.affiliate ? 'Buy (affiliate)' : 'Find it'}
					<span class="visually-hidden">— opens {new URL(pairing.url).hostname}</span>
				</a>
			{/if}
		</p>

		{#if pairing.draft}
			<p class="draft-flag">Draft — not yet verified</p>
		{/if}
	</div>
</article>

<style>
	.pairing-card {
		display: flex;
		flex-direction: column;
		block-size: 100%;
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
		box-shadow: var(--shadow);
		overflow: hidden;
	}

	.pairing-card__image {
		aspect-ratio: 3 / 2;
		inline-size: 100%;
		object-fit: cover;
		border-block-end: 1px solid var(--line);
	}

	.pairing-card__body {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
		flex: 1;
		padding: var(--space-s);
	}

	.pairing-card__title {
		font-size: var(--step-1);
	}

	.pairing-card__title a {
		color: var(--ink);
		text-decoration: none;
	}

	.pairing-card__title a:hover {
		color: var(--accent);
		text-decoration: underline;
	}

	.pairing-card__note {
		font-size: var(--step-0);
	}

	.pairing-card__meta {
		margin-block-start: auto;
		padding-block-start: var(--space-3xs);
		font-size: var(--step--1);
	}
</style>
