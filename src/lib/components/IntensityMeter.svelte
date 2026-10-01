<script lang="ts">
	interface Props {
		/** The trait being measured, e.g. "Intensity" or "Comfort". */
		label: string;
		value: number;
		max?: number;
		/** The word this value maps to, e.g. "Strong". */
		text: string;
	}

	let { label, value, max = 5, text }: Props = $props();

	const pips = $derived(Array.from({ length: max }, (_, index) => index < value));
</script>

<!--
	The pips are decoration: they are hidden from assistive technology and the
	same information is given as visible text, so there is nothing to miss
	whether you are reading, listening, or looking at a printout.
-->
<div class="meter">
	<span class="meter__label">{label}</span>
	<span class="meter__pips" aria-hidden="true">
		{#each pips as filled, index (index)}
			<span class="meter__pip" class:is-filled={filled}></span>
		{/each}
	</span>
	<span class="meter__text">
		{text}
		<span class="meter__count">({value} of {max})</span>
	</span>
</div>

<style>
	.meter {
		display: grid;
		grid-template-columns: auto 1fr;
		align-items: center;
		gap: var(--space-3xs) var(--space-2xs);
	}

	.meter__label {
		grid-column: 1;
		font-size: var(--step--2);
		font-weight: 600;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: var(--ink-muted);
	}

	/* Marquee bulbs: lit ones glow, unlit ones are just the empty socket. */
	.meter__pips {
		grid-column: 2;
		display: flex;
		gap: 5px;
	}

	.meter__pip {
		inline-size: 0.8rem;
		block-size: 0.8rem;
		border: 2px solid var(--line-strong);
		border-radius: 50%;
	}

	.meter__pip.is-filled {
		background: radial-gradient(circle at 35% 35%, #fff8 0 20%, var(--bulb) 45%);
		border-color: var(--gold);
		box-shadow: 0 0 6px 1px var(--bulb-glow);
	}

	.meter__text {
		grid-column: 1 / -1;
		font-family: var(--font-display);
		font-size: var(--step-0);
	}

	.meter__count {
		color: var(--ink-muted);
		font-family: var(--font-body);
		font-size: var(--step--1);
	}
</style>
