<script lang="ts">
	import { intensityLabel, pairingTypeLabels, rideTypeLabels } from '$lib/content/labels';
	import type { PairingType, RideType } from '$lib/content/constants';
	import { parseFilters, type Facets, type Filters } from '$lib/rides/filter';

	interface Props {
		facets: Facets;
		/** Current selection, owned by the page and read from the URL. */
		filters: Filters;
		onchange: (filters: Filters) => void;
		resultCount: number;
		totalCount: number;
	}

	let { facets, filters, onchange, resultCount, totalCount }: Props = $props();

	let form = $state<HTMLFormElement | null>(null);

	/*
	 * The form is the source of truth for a change: reading it with FormData
	 * means the param names here and in the URL cannot drift apart, because
	 * `parseFilters` does both jobs.
	 */
	function emit() {
		if (!form) return;
		const entries = [...new FormData(form)].filter(
			(entry): entry is [string, string] => typeof entry[1] === 'string'
		);
		onchange(parseFilters(new URLSearchParams(entries)));
	}

	function handleSubmit(event: SubmitEvent) {
		// JavaScript is available, so filter in place rather than navigating.
		event.preventDefault();
		emit();
	}
</script>

<!--
	Without JavaScript the controls cannot do anything useful on a static site,
	so they are hidden rather than left there to be pressed to no effect. The
	full list still renders below, and the pairing-type sections on /pairings
	give a no-JS way to browse. The <style> lives in <head>, where it is valid.
-->
<svelte:head>
	<noscript>
		<style>
			.filter-bar__controls,
			.filter-bar__count {
				display: none;
			}
		</style>
	</noscript>
</svelte:head>

<form
	class="filter-bar"
	method="get"
	action="/rides"
	bind:this={form}
	onchange={emit}
	onsubmit={handleSubmit}
>
	<div class="filter-bar__controls">
		<fieldset class="filter-bar__group filter-bar__group--wide">
			<legend class="eyebrow">Ride type</legend>
			<div class="pills">
				{#each facets.rideTypes as type (type)}
					<label class="pill">
						<input
							type="checkbox"
							name="type"
							value={type}
							checked={filters.types.includes(type)}
						/>
						<span>{rideTypeLabels[type as RideType]}</span>
					</label>
				{/each}
			</div>
		</fieldset>

		<div class="filter-bar__selects">
			<p class="filter-bar__field">
				<label class="eyebrow" for="filter-pairing">Pairs with</label>
				<select id="filter-pairing" name="pairing">
					<option value="" selected={filters.pairing === ''}>Anything</option>
					{#each facets.pairingTypes as type (type)}
						<option value={type} selected={filters.pairing === type}>
							{pairingTypeLabels[type as PairingType]}
						</option>
					{/each}
				</select>
			</p>

			<p class="filter-bar__field">
				<label class="eyebrow" for="filter-intensity">Intensity</label>
				<select id="filter-intensity" name="intensity">
					<option value="" selected={filters.intensity === ''}>Any</option>
					{#each facets.intensities as value (value)}
						<option value={String(value)} selected={filters.intensity === String(value)}>
							{intensityLabel(value)}
						</option>
					{/each}
				</select>
			</p>

			<p class="filter-bar__field">
				<label class="eyebrow" for="filter-vibe">Vibe</label>
				<select id="filter-vibe" name="vibe">
					<option value="" selected={filters.vibe === ''}>Any</option>
					{#each facets.vibes as vibe (vibe)}
						<option value={vibe} selected={filters.vibe === vibe}>{vibe}</option>
					{/each}
				</select>
			</p>

			<p class="filter-bar__field">
				<label class="eyebrow" for="filter-country">Country</label>
				<select id="filter-country" name="country">
					<option value="" selected={filters.country === ''}>Anywhere</option>
					{#each facets.countries as country (country)}
						<option value={country} selected={filters.country === country}>{country}</option>
					{/each}
				</select>
			</p>
		</div>

		<div class="filter-bar__actions">
			<!-- A link, so clearing works by navigation and leaves a clean URL. -->
			<a class="button button--quiet" href="/rides">Clear filters</a>
		</div>
	</div>

	<!-- Changes apply as you make them, and the count is announced. -->
	<p class="filter-bar__count muted" aria-live="polite">
		{#if resultCount === totalCount}
			{totalCount} ride{totalCount === 1 ? '' : 's'} on the list.
		{:else}
			Showing {resultCount} of {totalCount} rides.
		{/if}
	</p>

	<noscript>
		<p class="filter-bar__note">
			Filtering needs JavaScript. All {totalCount} rides are listed below, and
			<a href="/pairings">the pairings index</a> groups everything by type.
		</p>
	</noscript>
</form>

<style>
	.filter-bar {
		display: flex;
		flex-direction: column;
		gap: var(--space-xs);
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
	}

	.filter-bar__controls {
		display: flex;
		flex-direction: column;
		gap: var(--space-s);
	}

	.filter-bar__group {
		border: 0;
		padding: 0;
		margin: 0;
	}

	.filter-bar__group legend {
		padding: 0;
		margin-block-end: var(--space-2xs);
	}

	.pills {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs);
	}

	/* The checkbox stays in the accessibility tree and drives the visible pill. */
	.pill {
		display: inline-flex;
		align-items: center;
		gap: var(--space-3xs);
		padding: 0.25em 0.75em;
		border: 1px solid var(--line-strong);
		border-radius: var(--radius-pill);
		font-size: var(--step--1);
		cursor: pointer;
		transition:
			background var(--transition),
			border-color var(--transition);
	}

	.pill:hover {
		border-color: var(--accent);
	}

	.pill:has(input:checked) {
		background: var(--accent);
		border-color: var(--accent);
		color: var(--accent-contrast);
		font-weight: 600;
	}

	.pill:has(input:focus-visible) {
		outline: 3px solid var(--accent);
		outline-offset: 2px;
	}

	.pill input {
		accent-color: var(--accent);
		margin: 0;
	}

	.pill:has(input:checked) input {
		accent-color: var(--accent-contrast);
	}

	.filter-bar__selects {
		display: grid;
		gap: var(--space-xs);
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 11rem), 1fr));
	}

	.filter-bar__field {
		display: flex;
		flex-direction: column;
		gap: var(--space-3xs);
	}

	select {
		padding: 0.4em 0.5em;
		border: 1px solid var(--line-strong);
		border-radius: var(--radius);
		background: var(--paper);
		font-size: var(--step--1);
	}

	.filter-bar__actions {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs);
	}

	.filter-bar__count,
	.filter-bar__note {
		font-size: var(--step--1);
		margin: 0;
	}

	.filter-bar__note {
		color: var(--ink-muted);
	}
</style>
