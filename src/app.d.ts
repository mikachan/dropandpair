// See https://svelte.dev/docs/kit/types#app.d.ts
import type { PagefindUIConstructor } from '$lib/search/pagefind';

declare global {
	namespace App {
		// interface Error {}
		// interface Locals {}
		// interface PageData {}
		// interface PageState {}
		// interface Platform {}
	}

	interface Window {
		/** Registered by /pagefind/pagefind-ui.js, which is generated at build time. */
		PagefindUI?: PagefindUIConstructor;
	}
}

export {};
