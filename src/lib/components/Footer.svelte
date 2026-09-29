<script lang="ts">
	import { site } from '$lib/site';

	const year = new Date().getFullYear();
</script>

<footer class="colophon">
	<div class="page colophon__inner">
		<div class="colophon__block">
			<p class="colophon__brand">{site.name}</p>
			<p class="muted">{site.tagline}</p>
		</div>

		<nav aria-label="Footer">
			<ul class="colophon__nav">
				<li><a href="/rides">Rides</a></li>
				<li><a href="/parks">Parks</a></li>
				<li><a href="/pairings">Pairings</a></li>
				<li><a href="/search">Search</a></li>
				<li><a href="/about">About</a></li>
				<li><a href="/rss.xml">RSS</a></li>
			</ul>
		</nav>

		<p class="colophon__small muted">
			Pairings are opinions, not advice. Some links are affiliate links and are labelled as such.
			Written by hand · © {year}
		</p>
	</div>
</footer>

<style>
	.colophon {
		margin-block-start: auto;
		border-block-start: 3px double var(--line-strong);
		background: color-mix(in srgb, var(--surface) 70%, transparent);
	}

	.colophon__inner {
		display: flex;
		flex-direction: column;
		gap: var(--space-s);
		padding-block: var(--space-l);
	}

	.colophon__brand {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	.colophon__nav {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs) var(--space-m);
		list-style: none;
		margin: 0;
		padding: 0;
		font-size: var(--step--1);
		font-weight: 600;
	}

	.colophon__small {
		font-size: var(--step--1);
		max-width: var(--measure);
	}

	@media (min-width: 48rem) {
		.colophon__inner {
			flex-direction: row;
			flex-wrap: wrap;
			align-items: baseline;
			justify-content: space-between;
		}

		.colophon__small {
			flex-basis: 100%;
		}
	}
</style>
