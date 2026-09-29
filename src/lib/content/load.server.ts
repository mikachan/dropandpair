import { dev } from '$app/environment';
import { buildGraph, type ContentGraph } from './graph';
import { parseCollections, type RawFile } from './parse';

/**
 * The only module that knows where content files live.
 *
 * `import.meta.glob` reads them at build time, so no filesystem access ships
 * in the bundle and editing a Markdown file hot-reloads the dev server. The
 * `.server.ts` suffix makes SvelteKit refuse any accidental import from client
 * code, which keeps `marked`, `gray-matter` and every word of body text out of
 * the browser bundle.
 *
 * Invalid content throws while this module is evaluating, which fails the build.
 */

const asRaw = { query: '?raw', import: 'default', eager: true } as const;

const rideFiles = import.meta.glob('/content/rides/*.md', asRaw) as Record<string, string>;
const parkFiles = import.meta.glob('/content/parks/*.md', asRaw) as Record<string, string>;
const pairingFiles = import.meta.glob('/content/pairings/*.md', asRaw) as Record<string, string>;

/** Sorted so validation problems are reported in a stable, file-order sequence. */
function toRawFiles(modules: Record<string, string>): RawFile[] {
	return Object.entries(modules)
		.map(([path, raw]) => ({ path: path.replace(/^\//, ''), raw }))
		.sort((a, b) => a.path.localeCompare(b.path, 'en'));
}

/** Drafts are visible while writing and excluded from production builds. */
export const graph: ContentGraph = buildGraph(
	parseCollections({
		rides: toRawFiles(rideFiles),
		parks: toRawFiles(parkFiles),
		pairings: toRawFiles(pairingFiles)
	}),
	{ includeDrafts: dev }
);
