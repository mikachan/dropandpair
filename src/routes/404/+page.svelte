<script lang="ts">
	import ErrorPanel from '$lib/components/ErrorPanel.svelte';
</script>

<!--
	A real page, prerendered to build/404.html, the conventional place for a
	not-found page in a directory of static files. Using adapter-static's
	`fallback` instead would write an empty app shell here, leaving anyone
	without JavaScript looking at a blank page.
-->
<svelte:head>
	<title>Not found · Drop and Pair</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<ErrorPanel status={404} />
