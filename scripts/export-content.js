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
 * The about page's prose, copied from src/routes/about/+page.svelte. It is
 * static text, so a constant is simpler than parsing Svelte. The moments list
 * is built from code there, so it is not part of this.
 */
const ABOUT_HTML = `<p>Getting as immersed as possible in whatever I'm doing is one of my favourite things. When I travel, I'll often read up on the local folklore, or pick up a local story or bestselling novel, and on the way there I'll usually have a playlist of local music on (especially local rock music).</p>
<p>Rides are an easy fit for this, because so many of them are already based on a film, a book or a legend. I'm also a massive fan of a well-themed ride. Wicker Man at Alton Towers is a great example: there's a whole pre-show before you even get on. Drop and Pair is my way of trying to give every ride that feeling, with things to read, watch or listen to in anticipation, and things to do afterwards. Some rides make that easier than others!</p>
<h2>About me</h2>
<p>I'm a coaster nerd and a software engineer. My favourite park is Alton Towers, and my favourite rides include Wicker Man at Alton Towers, Zadra at Energylandia and Helios at Fantasiana.</p>
<h2>The moments</h2>
<p>Every pairing has a moment. They're listed in the order you'd likely want to experience them, from before you queue to after you've left the park.</p>
<h2>Thank you</h2>
<p>The ride facts and stats here come from some brilliant resources, and I'm really grateful to everyone who keeps them going:</p>
<ul>
<li><a href="https://rcdb.com" rel="noopener">RCDB</a>, the Roller Coaster DataBase, for most of the coaster stats and opening dates</li>
<li><a href="https://darkridedatabase.com" rel="noopener">the Dark Ride Database</a>, for the dark rides</li>
<li>Wikipedia and YouTube, for ride histories, and for the films, books and music</li>
<li>the parks' own websites and local tourist boards</li>
</ul>
<p>Every ride page links to the sources used for it, under Further reading.</p>`;

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
		return {
			parks: collections.parks,
			rides: collections.rides,
			pairings: collections.pairings,
			pages: { about: { title: 'About', html: ABOUT_HTML } }
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
