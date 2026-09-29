<script lang="ts">
	import { absoluteUrl, site } from '$lib/site';

	interface Props {
		title: string;
		description: string;
		/** Site-relative path, e.g. `/rides/nemesis`. */
		path: string;
		/** `article` for a write-up, `website` for everything else. */
		type?: 'website' | 'article';
		/** Site-relative image path. */
		image?: string;
		/** A schema.org object, serialised into a JSON-LD script. */
		jsonLd?: Record<string, unknown>;
		noindex?: boolean;
	}

	let {
		title,
		description,
		path,
		type = 'website',
		image,
		jsonLd,
		noindex = false
	}: Props = $props();

	const isHome = $derived(path === '/');
	const documentTitle = $derived(
		isHome ? `${site.name} — ${site.tagline}` : `${title} · ${site.name}`
	);
	const canonical = $derived(absoluteUrl(path));
	const imageUrl = $derived(image ? absoluteUrl(image) : undefined);

	/*
	 * Svelte parses a literal script tag in the template as component code, so
	 * JSON-LD is emitted as markup instead.
	 *
	 * Two details matter. The closing tag is concatenated rather than written
	 * out, because Svelte ends this block at the first `</` it sees — writing it
	 * literally would truncate the component. And every `<` in the payload is
	 * escaped, so content containing a tag cannot break out of the script.
	 */
	const SCRIPT_CLOSE = '<' + '/script>';

	const jsonLdScript = $derived(
		jsonLd
			? `<script type="application/ld+json">${JSON.stringify(jsonLd).replace(/</g, '\\u003c')}${SCRIPT_CLOSE}`
			: ''
	);
</script>

<svelte:head>
	<title>{documentTitle}</title>
	<meta name="description" content={description} />
	<link rel="canonical" href={canonical} />
	{#if noindex}
		<meta name="robots" content="noindex, follow" />
	{/if}

	<meta property="og:site_name" content={site.name} />
	<meta property="og:type" content={type} />
	<meta property="og:title" content={isHome ? site.name : title} />
	<meta property="og:description" content={description} />
	<meta property="og:url" content={canonical} />
	<meta property="og:locale" content={site.locale} />

	<meta name="twitter:card" content={imageUrl ? 'summary_large_image' : 'summary'} />
	{#if imageUrl}
		<meta property="og:image" content={imageUrl} />
		<meta name="twitter:image" content={imageUrl} />
	{/if}

	<!-- Built from a plain object above, with `<` escaped. -->
	<!-- eslint-disable-next-line svelte/no-at-html-tags -->
	{@html jsonLdScript}
</svelte:head>
