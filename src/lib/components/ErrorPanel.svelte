<script lang="ts">
	interface Props {
		status: number;
		/** Shown instead of the stock copy, for non-404 errors. */
		message?: string;
	}

	let { status, message }: Props = $props();

	const isNotFound = $derived(status === 404);
</script>

<div class="page section error">
	<p class="eyebrow">Error {status}</p>
	<h1>{isNotFound ? 'Nothing on that shelf' : 'Something went wrong'}</h1>

	{#if isNotFound}
		<p class="lede">
			That page is not on the list. It may have been renamed, or it may never have existed.
		</p>
	{:else}
		<p class="lede">{message ?? 'An unexpected error occurred.'}</p>
	{/if}

	<nav aria-label="Error recovery">
		<ul class="error__links">
			<li><a class="button" href="/">Back to the front page</a></li>
			<li><a class="button button--quiet" href="/rides">All rides</a></li>
			<li><a class="button button--quiet" href="/search">Search</a></li>
		</ul>
	</nav>
</div>

<style>
	.error {
		display: flex;
		flex-direction: column;
		gap: var(--space-s);
		align-items: start;
		max-width: var(--measure);
	}

	.error__links {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs);
		list-style: none;
		padding: 0;
		margin: 0;
	}
</style>
