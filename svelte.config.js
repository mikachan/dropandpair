import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),
	kit: {
		// Everything is prerendered. The /404 route writes build/404.html, which
		// static hosts serve for unknown paths — see src/routes/404.
		adapter: adapter(),
		prerender: {
			handleHttpError: 'fail',
			handleMissingId: 'fail'
		}
	}
};

export default config;
