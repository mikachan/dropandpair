<script lang="ts">
	import Seo from '$lib/components/Seo.svelte';
	import { PAIRING_TYPES } from '$lib/content/constants';
	import { pairingTypeDescriptions, pairingTypeLabels } from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import { site } from '$lib/site';
</script>

<Seo
	title="About"
	description="Why Drop and Pair exists, who writes it, and where the ride facts come from."
	path="/about"
	jsonLd={jsonld.graph(
		jsonld.breadcrumbs([
			{ name: 'Drop and Pair', path: '/' },
			{ name: 'About', path: '/about' }
		])
	)}
/>

<article class="page section about" data-pagefind-body data-pagefind-meta="kind:Page">
	<header class="about__header">
		<p class="eyebrow">Hello</p>
		<h1>About</h1>
		<p class="lede">{site.tagline}</p>
	</header>

	<div class="prose">
		<p>
			Getting as immersed as possible in whatever I'm doing is one of my favourite things. When I
			travel, I'll often read up on the local folklore, or pick up a local story or bestselling
			novel, and on the way there I'll usually have a playlist of local music on (especially local
			rock music).
		</p>
		<p>
			Rides are an easy fit for this, because so many of them are already based on a film, a book or
			a legend. I'm also a massive fan of a well-themed ride. Wicker Man at Alton Towers is a great
			example: there's a whole pre-show before you even get on. Drop and Pair is my way of trying to
			give every ride that feeling, with things to read, watch or listen to in anticipation, and
			things to do afterwards. Some rides make that easier than others!
		</p>

		<h2>About me</h2>
		<p>
			I'm a coaster nerd and a software engineer. My favourite park is Alton Towers, and my
			favourite rides include Wicker Man at Alton Towers, Zadra at Energylandia and Helios at
			Fantasiana.
		</p>

		<h2>The moments</h2>
		<p>
			Every pairing has a moment. They're listed in the order you'd likely want to experience them,
			from before you queue to after you've left the park.
		</p>
	</div>

	<dl class="moments">
		{#each PAIRING_TYPES as type (type)}
			<div class="moments__item">
				<dt>{pairingTypeLabels[type]}</dt>
				<dd>{pairingTypeDescriptions[type]}</dd>
			</div>
		{/each}
	</dl>

	<div class="prose">
		<h2>Thank you</h2>
		<p>
			The ride facts and stats here come from some brilliant resources, and I'm really grateful to
			everyone who keeps them going:
		</p>
		<ul>
			<li>
				<a href="https://rcdb.com" rel="noopener">RCDB</a>, the Roller Coaster DataBase, for most of
				the coaster stats and opening dates
			</li>
			<li>
				<a href="https://darkridedatabase.com" rel="noopener">the Dark Ride Database</a>, for the
				dark rides
			</li>

			<li>Wikipedia and YouTube, for ride histories, and for the films, books and music</li>
			<li>the parks' own websites and local tourist boards</li>
		</ul>
		<p>Every ride page links to the sources used for it, under Further reading.</p>
	</div>
</article>

<style>
	.about {
		display: flex;
		flex-direction: column;
		gap: var(--space-m);
		max-width: 52rem;
	}

	.about__header {
		display: flex;
		flex-direction: column;
		gap: var(--space-2xs);
	}

	.moments {
		display: grid;
		gap: var(--space-s);
		margin: 0;
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
	}

	.moments__item {
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
	}

	.moments dt {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	.moments dd {
		margin: 0;
		color: var(--ink-muted);
	}
</style>
