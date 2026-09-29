<script lang="ts">
	import Seo from '$lib/components/Seo.svelte';
	import { PAIRING_TYPES } from '$lib/content/constants';
	import {
		bodyLabels,
		finishLabels,
		pairingTypeDescriptions,
		pairingTypeLabels,
		strengthLabels
	} from '$lib/content/labels';
	import * as jsonld from '$lib/seo/jsonld';
	import { site } from '$lib/site';
</script>

<Seo
	title="About"
	description="How Drop and Pair works: what the courses mean, how body, finish and vintage are judged, and where the affiliate links are."
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
			Wine gets pairings. Whisky gets pairings. Rides, for some reason, do not — even though a
			four-minute coaster leaves you in a very specific mood, and that mood has a right film, a
			right record, and a right thing to eat while your hands stop shaking.
		</p>
		<p>
			So: every ride here gets a tasting note and a set of pairings. Some are obvious. Some exist
			because a park was in a film, or because a ride is rough enough that the honest pairing is a
			hot bath. All of them are opinions, written by hand by one enthusiast, and none of them are
			sponsored placements dressed up as recommendations.
		</p>

		<h2>How rides are described</h2>
		<p>
			Three borrowed words do most of the work, because they are more useful than a number on its
			own.
		</p>

		<dl class="glossary">
			<div>
				<dt>Body</dt>
				<dd>
					How hard it hits, from {bodyLabels[1].toLowerCase()} to
					{bodyLabels[5].toLowerCase()}. A gentle dark ride is light-bodied; a launch coaster with
					no let-up is cask strength.
				</dd>
			</div>
			<div>
				<dt>Finish</dt>
				<dd>
					How you feel walking off, from a {finishLabels[1].toLowerCase()} to an
					{finishLabels[5].toLowerCase()}. This is about roughness, not intensity: a ride can be
					enormous and still silky, or mild and still rattle your teeth.
				</dd>
			</div>
			<div>
				<dt>Vintage</dt>
				<dd>The year it opened. Older is not worse. Sometimes it is the whole point.</dd>
			</div>
		</dl>

		<p>
			Each pairing also carries a match rating, from
			<em>{strengthLabels[1].toLowerCase()}</em> up to a
			<em>{strengthLabels[5].toLowerCase()}</em>. That rating is about how well the pairing suits
			<em>that</em> ride, not how good the film or the record is on its own.
		</p>

		<h2>The courses</h2>
		<p>Pairings are grouped into courses, and read in the order you would meet them.</p>
	</div>

	<dl class="courses">
		{#each PAIRING_TYPES as type (type)}
			<div class="courses__item">
				<dt>{pairingTypeLabels[type]}</dt>
				<dd>{pairingTypeDescriptions[type]}</dd>
			</div>
		{/each}
	</dl>

	<div class="prose">
		<h2>Affiliate links and honesty</h2>
		<p>
			Some outbound links are affiliate links. Every one of them is labelled on the page, and marked
			up as sponsored so search engines know too. Nothing is listed here because it pays; pairings
			are chosen first and links added afterwards, if they exist at all. If that ever stops being
			true, this paragraph will change.
		</p>
		<p>
			There is no advertising, no tracking, and no analytics on this site as it stands. It is a
			static site: plain HTML and CSS, written in Markdown, built from a Git repository.
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
	.courses {
		display: grid;
		gap: var(--space-s);
		margin: 0;
	}

	.courses {
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
	}

	.courses__item {
		padding: var(--space-s);
		border: 1px solid var(--line);
		border-radius: var(--radius-lg);
		background: var(--surface);
	}

	:is(.glossary, .courses) dt {
		font-family: var(--font-display);
		font-size: var(--step-1);
		font-weight: 600;
	}

	:is(.glossary, .courses) dd {
		margin: 0;
		color: var(--ink-muted);
	}

	.glossary {
		max-width: var(--measure);
		padding-inline-start: var(--space-s);
		border-inline-start: 3px double var(--line-strong);
	}
</style>
