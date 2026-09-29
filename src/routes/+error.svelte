<script lang="ts">
	import { page } from '$app/state';
	import ErrorPanel from '$lib/components/ErrorPanel.svelte';
</script>

<svelte:head>
	<title>{page.status === 404 ? 'Not found' : 'Something went wrong'} · Drop and Pair</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<ErrorPanel status={page.status} message={page.error?.message} />
