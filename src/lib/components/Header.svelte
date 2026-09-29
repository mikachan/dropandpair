<script lang="ts">
	import { page } from '$app/state';
	import { site } from '$lib/site';

	const links = [
		{ href: '/rides', label: 'Rides' },
		{ href: '/parks', label: 'Parks' },
		{ href: '/pairings', label: 'Pairings' },
		{ href: '/search', label: 'Search' },
		{ href: '/about', label: 'About' }
	];

	/** A section link stays current while you are anywhere inside it. */
	function isCurrent(href: string): boolean {
		const path = page.url.pathname;
		return path === href || path.startsWith(`${href}/`);
	}
</script>

<header class="masthead">
	<div class="page masthead__inner">
		<a class="brand" href="/">
			<svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true">
				<path
					d="M16 4c5 5.8 7.8 9.7 7.8 13.1a7.8 7.8 0 0 1-15.6 0C8.2 13.7 11 9.8 16 4Z"
					fill="currentColor"
				/>
			</svg>
			<span class="brand__text">
				<span class="brand__name">{site.name}</span>
				<span class="brand__tagline">{site.tagline}</span>
			</span>
		</a>

		<nav aria-label="Main">
			<ul class="masthead__nav">
				{#each links as link (link.href)}
					<li>
						<a href={link.href} aria-current={isCurrent(link.href) ? 'page' : undefined}>
							{link.label}
						</a>
					</li>
				{/each}
			</ul>
		</nav>
	</div>
</header>

<style>
	.masthead {
		border-block-end: 3px double var(--line-strong);
		background: color-mix(in srgb, var(--surface) 70%, transparent);
	}

	.masthead__inner {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: var(--space-xs) var(--space-m);
		padding-block: var(--space-s);
	}

	.brand {
		display: flex;
		align-items: center;
		gap: var(--space-2xs);
		color: var(--ink);
		text-decoration: none;
	}

	.brand__mark {
		flex: none;
		inline-size: 1.75rem;
		color: var(--accent);
	}

	.brand__text {
		display: flex;
		flex-direction: column;
	}

	.brand__name {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
		line-height: 1.05;
		letter-spacing: -0.01em;
	}

	.brand__tagline {
		font-size: var(--step--2);
		letter-spacing: 0.06em;
		text-transform: uppercase;
		color: var(--ink-muted);
	}

	.masthead__nav {
		display: flex;
		flex-wrap: wrap;
		gap: var(--space-2xs) var(--space-s);
		list-style: none;
		margin: 0;
		padding: 0;
		font-size: var(--step--1);
		font-weight: 600;
	}

	.masthead__nav a {
		display: block;
		padding-block: var(--space-3xs);
		color: var(--ink);
		text-decoration: none;
		border-block-end: 2px solid transparent;
	}

	.masthead__nav a:hover {
		color: var(--accent);
		border-block-end-color: var(--line-strong);
	}

	.masthead__nav a[aria-current='page'] {
		color: var(--accent);
		border-block-end-color: var(--accent);
	}

	/* On narrow screens the tagline is noise next to the wordmark. */
	@media (max-width: 26rem) {
		.brand__tagline {
			display: none;
		}
	}
</style>
