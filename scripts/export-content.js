/**
 * Exports the markdown content as JSON for the WordPress importer.
 *
 * Runs the real SvelteKit parser and reference check (loaded through Vite so
 * the TypeScript works untouched), so the import sees exactly what the site
 * builds from, and a broken reference fails here rather than half-importing.
 * Drafts are included: WordPress imports them as draft posts.
 *
 * Usage: node scripts/export-content.js [outFile]
 */
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/**
 * The about page's prose, first copied from src/routes/about/+page.svelte and since
 * reworded for WordPress (static text, so a constant is simpler than parsing Svelte).
 * The moments list comes from code there, so aboutHtml() builds it from the same
 * modules.
 */
const ABOUT_INTRO = `<p>I love getting properly immersed in whatever I'm doing. When I travel, I read up on the local folklore, pick up a local novel and put together a playlist of local music for the journey (rock, ideally).</p>
<p>Rides make that easy, because so many are already based on a film, a book or a legend. I'm also a massive fan of a well-themed ride. Wicker Man at Alton Towers has a whole pre-show before you even get on. Drop and Pair tries to give every ride that feeling, with something to read, watch or listen to beforehand and something to do afterwards. Some rides make it easier than others!</p>
<h2>About me</h2>
<p>I'm a coaster nerd and a software engineer. My favourite park is Alton Towers, and my favourite rides include Wicker Man, Zadra at Energylandia and Helios at Fantasiana.</p>
<h2>The moments</h2>
<p>Every pairing has a moment. They're in the order you'd probably want them, from before you queue to after you've left the park.</p>`;

const ABOUT_THANKS = `<h2>Thank you</h2>
<p>The ride facts and stats come from some brilliant resources, and I'm really grateful to everyone who keeps them going:</p>
<ul>
<li><a href="https://rcdb.com" rel="noopener">RCDB</a>, the Roller Coaster DataBase, for most of the coaster stats and opening dates</li>
<li><a href="https://darkridedatabase.com" rel="noopener">the Dark Ride Database</a>, for the dark rides</li>
<li>Wikipedia and YouTube, for ride histories, and for the films, books and music</li>
<li>the parks' own websites and local tourist boards</li>
</ul>
<p>Each ride page links to its sources under Further reading.</p>`;

const escapeHtml = (text) =>
	text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function aboutHtml({ PAIRING_TYPES, pairingTypeLabels, pairingTypeDescriptions }) {
	const moments = PAIRING_TYPES.map(
		(type) =>
			`<dt>${escapeHtml(pairingTypeLabels[type])}</dt>\n<dd>${escapeHtml(pairingTypeDescriptions[type])}</dd>`
	).join('\n');
	return [
		ABOUT_INTRO,
		`<dl>\n${moments}\n</dl>`,
		ABOUT_THANKS
	].join('\n');
}

function readCollection(rootDir, name) {
	const dir = join(rootDir, 'content', name);
	return readdirSync(dir)
		.filter((file) => file.endsWith('.md'))
		.sort()
		.map((file) => ({
			path: `content/${name}/${file}`,
			raw: readFileSync(join(dir, file), 'utf8')
		}));
}

export async function exportContent(rootDir) {
	const vite = await createServer({
		configFile: false,
		root: rootDir,
		appType: 'custom',
		server: { middlewareMode: true },
		logLevel: 'error'
	});
	try {
		const { parseCollections } = await vite.ssrLoadModule('/src/lib/content/parse.ts');
		const { validateReferences } = await vite.ssrLoadModule('/src/lib/content/graph.ts');
		const collections = parseCollections({
			rides: readCollection(rootDir, 'rides'),
			parks: readCollection(rootDir, 'parks'),
			pairings: readCollection(rootDir, 'pairings')
		});
		validateReferences(collections);
		const { PAIRING_TYPES } = await vite.ssrLoadModule('/src/lib/content/constants.ts');
		const labels = await vite.ssrLoadModule('/src/lib/content/labels.ts');
		return {
			parks: collections.parks,
			rides: collections.rides,
			pairings: collections.pairings,
			pages: { about: { title: 'About', html: aboutHtml({ PAIRING_TYPES, ...labels }) } }
		};
	} finally {
		await vite.close();
	}
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const outFile = resolve(process.argv[2] ?? join(repoRoot, 'wordpress/import/content.json'));
	const data = await exportContent(repoRoot);
	mkdirSync(dirname(outFile), { recursive: true });
	writeFileSync(outFile, JSON.stringify(data, null, 2) + '\n');
	console.log(
		`Wrote ${data.parks.length} parks, ${data.rides.length} rides, ${data.pairings.length} pairings to ${outFile}`
	);
}
