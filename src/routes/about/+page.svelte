<script lang="ts">
	import Seo from '$lib/components/Seo.svelte';
	import { PAIRING_TYPES } from '$lib/content/constants';
	import {
		comfortLabels,
		intensityLabels,
		pairingTypeDescriptions,
		pairingTypeLabels,
		strengthLabels
	} from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import { site } from '$lib/site';
</script>

<Seo
	title="About"
	description="How Drop and Pair works: what the moments mean and how intensity and comfort are judged."
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
		<p class="eyebrow">The method</p>
		<h1>About</h1>
		<p class="lede">{site.tagline}</p>
	</header>

	<div class="prose">
		<p>
			Food gets pairings. Drinks get pairings. Rides, for some reason, do not — even though a
			four-minute coaster leaves you in a very specific mood, and that mood has a right film, a
			right record, and a right thing to eat while your hands stop shaking.
		</p>
		<p>
			So: every ride here gets a quick take and a set of pairings. Some are obvious. Some exist
			because a park was in a film, or because a ride is rough enough that the honest pairing is a
			hot bath. All of them are opinions, written by hand by one enthusiast, and none of them are
			sponsored placements dressed up as recommendations.
		</p>

		<h2>How rides are described</h2>
		<p>
			Two scales do most of the work, and a word for each step is more useful than a number on its
			own.
		</p>

		<dl class="glossary">
			<div>
				<dt>Intensity</dt>
				<dd>
					How hard it hits, from {intensityLabels[1].toLowerCase()} to
					{intensityLabels[5].toLowerCase()}. A slow dark ride is gentle; a launch coaster with no
					let-up is maximum.
				</dd>
			</div>
			<div>
				<dt>Comfort</dt>
				<dd>
					How you feel walking off, from {comfortLabels[1].toLowerCase()} to
					{comfortLabels[5].toLowerCase()}. This is about roughness, not intensity: a ride can be
					enormous and still silky, or mild and still rattle your teeth.
				</dd>
			</div>
		</dl>

		<p>
			Each pairing also carries a match rating, from
			<em>{strengthLabels[1].toLowerCase()}</em> up to a
			<em>{strengthLabels[5].toLowerCase()}</em>. That rating is about how well the pairing suits
			<em>that</em> ride, not how good the film or the record is on its own.
		</p>

		<h2>The moments</h2>
		<p>Every pairing has a moment, and they are listed in the order you would meet them.</p>
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
		<h2>Honesty</h2>
		<p>
			Nothing is listed here because someone paid for it. Pairings are chosen first, and links are
			added afterwards, if they exist at all.
		</p>
		<p>
			This is a static site: plain HTML and CSS, written in Markdown, built from a Git repository.
		</p>

		<h2>Corrections</h2>
		<p>
			Ride statistics and opening years get misremembered, re-profiled and quietly changed. If
			something here is wrong, it is worth telling me, and it will be fixed in the repository rather
			than patched over.
		</p>
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

	.glossary,
	.moments {
		display: grid;
		gap: var(--space-s);
		margin: 0;
	}

	.moments {
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
	}

	.moments__item {
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
	}

	:is(.glossary, .moments) dt {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	:is(.glossary, .moments) dd {
		margin: 0;
		color: var(--ink-muted);
	}

	.glossary {
		max-width: var(--measure);
		padding-inline-start: var(--space-s);
		border-inline-start: 3px double var(--line-strong);
	}
</style>
