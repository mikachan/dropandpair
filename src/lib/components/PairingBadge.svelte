<script lang="ts">
	import type { PairingType } from '$lib/content/constants';
	import { pairingTypeLabels } from '$lib/content/labels';

	interface Props {
		type: PairingType;
		/** Renders as a link when given, for browsing by type. */
		href?: string;
	}

	let { type, href }: Props = $props();
</script>

{#if href}
	<a class="badge badge--link" {href}>{pairingTypeLabels[type]}</a>
{:else}
	<span class="badge">{pairingTypeLabels[type]}</span>
{/if}

<style>
	.badge {
		display: inline-block;
		padding: 0.1em 0.5em;
		border: 2px solid currentColor;
		border-radius: 3px;
		color: var(--gold);
		font-size: var(--step--2);
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		white-space: nowrap;
	}

	.badge--link {
		text-decoration: none;
		transition:
			border-color var(--transition),
			color var(--transition);
	}

	.badge--link:hover {
		border-color: var(--accent);
		color: var(--accent);
	}
</style>
