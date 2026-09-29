import adapter from '@sveltejs/adapter-static';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),
	kit: {
		// Everything is prerendered. `fallback` gives static hosts (Cloudflare
		// Pages, Netlify) a 404.html to serve for unknown paths.
		adapter: adapter({ fallback: '404.html' }),
		prerender: {
			handleHttpError: 'fail',
			handleMissingId: 'fail'
		}
	}
};

export default config;
