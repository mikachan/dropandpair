/**
 * Loading the Pagefind UI at runtime.
 *
 * Pagefind builds its index and its UI bundle into `build/pagefind` during the
 * postbuild step, which means those files do not exist while the app is being
 * bundled, or at all during `npm run dev`. So they are injected into the
 * document as ordinary `<link>` and `<script>` tags: the bundler never sees
 * them, and the prerenderer never tries to resolve them as missing assets.
 *
 * This is also the loading method Pagefind documents — the UI bundle is a
 * classic script that registers `window.PagefindUI`.
 */

const CSS_URL = '/pagefind/pagefind-ui.css';
const JS_URL = '/pagefind/pagefind-ui.js';

/** The part of the Pagefind UI API this site uses. */
export interface PagefindUIInstance {
	triggerSearch?: (term: string) => void;
	destroy?: () => void;
}

export type PagefindUIConstructor = new (options: Record<string, unknown>) => PagefindUIInstance;

function loadStylesheet(href: string): void {
	if (document.querySelector(`link[href="${href}"]`)) return;
	const link = document.createElement('link');
	link.rel = 'stylesheet';
	link.href = href;
	document.head.append(link);
}

function loadScript(src: string): Promise<void> {
	const existing = document.querySelector<HTMLScriptElement>(`script[src="${src}"]`);
	if (existing?.dataset.loaded === 'true') return Promise.resolve();

	return new Promise((resolve, reject) => {
		const script = existing ?? document.createElement('script');
		script.addEventListener(
			'load',
			() => {
				script.dataset.loaded = 'true';
				resolve();
			},
			{ once: true }
		);
		script.addEventListener('error', () => reject(new Error(`Could not load ${src}`)), {
			once: true
		});

		if (!existing) {
			script.src = src;
			document.head.append(script);
		}
	});
}

/** Resolves with the Pagefind UI constructor, or rejects if the index is absent. */
export async function loadPagefindUI(): Promise<PagefindUIConstructor> {
	loadStylesheet(CSS_URL);
	await loadScript(JS_URL);

	const constructor = window.PagefindUI;
	if (!constructor) throw new Error('Pagefind loaded but did not register PagefindUI');
	return constructor;
}
