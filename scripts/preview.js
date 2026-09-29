/*
 * Serves `build/` the way a static host does.
 *
 * `vite preview` cannot be used for this: it serves the app from SvelteKit's
 * manifest, so it never sees the Pagefind index, which is written into
 * `build/pagefind` after the build finishes. Search would always look broken.
 *
 * Path resolution matches Cloudflare Pages and Netlify: try the exact file,
 * then `<path>.html`, then `<path>/index.html`, and fall back to `404.html`
 * with a 404 status. That makes this the closest local approximation of the
 * deployed site, which is the point of a preview.
 *
 * Usage: node scripts/preview.js [--port 4173]
 */

import { createReadStream, existsSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, resolve, sep } from 'node:path';

const ROOT = resolve('build');

const portFlag = process.argv.indexOf('--port');
const PORT = Number(portFlag !== -1 ? process.argv[portFlag + 1] : (process.env.PORT ?? 4173));

const CONTENT_TYPES = new Map(
	Object.entries({
		'.html': 'text/html; charset=utf-8',
		'.js': 'text/javascript; charset=utf-8',
		'.css': 'text/css; charset=utf-8',
		'.json': 'application/json; charset=utf-8',
		'.xml': 'application/xml; charset=utf-8',
		'.txt': 'text/plain; charset=utf-8',
		'.svg': 'image/svg+xml',
		'.png': 'image/png',
		'.jpg': 'image/jpeg',
		'.jpeg': 'image/jpeg',
		'.webp': 'image/webp',
		'.avif': 'image/avif',
		'.ico': 'image/x-icon',
		'.woff2': 'font/woff2',
		'.wasm': 'application/wasm'
	})
);

function isFile(path) {
	return existsSync(path) && statSync(path).isFile();
}

/** The candidate files a static host would try, in order. */
function resolveFile(pathname) {
	const relative = decodeURIComponent(pathname).replace(/^\/+/, '');
	const target = resolve(ROOT, relative);

	// Refuse anything that escapes the build directory.
	if (target !== ROOT && !target.startsWith(ROOT + sep)) return null;

	const candidates = pathname.endsWith('/')
		? [join(target, 'index.html')]
		: [target, `${target}.html`, join(target, 'index.html')];

	return candidates.find(isFile) ?? null;
}

const server = createServer((request, response) => {
	const { pathname } = new URL(request.url, `http://localhost:${PORT}`);
	const file = resolveFile(pathname);
	const fallback = join(ROOT, '404.html');

	if (!file) {
		const status = 404;
		if (isFile(fallback)) {
			response.writeHead(status, { 'Content-Type': CONTENT_TYPES.get('.html') });
			createReadStream(fallback).pipe(response);
		} else {
			response.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8' });
			response.end('Not found\n');
		}
		console.log(`${status} ${pathname}`);
		return;
	}

	const type = CONTENT_TYPES.get(extname(file)) ?? 'application/octet-stream';
	response.writeHead(200, { 'Content-Type': type });
	createReadStream(file).pipe(response);
});

if (!existsSync(ROOT)) {
	console.error('No build/ directory. Run `npm run build` first.');
	process.exit(1);
}

server.listen(PORT, () => {
	console.log(`Serving build/ at http://localhost:${PORT}`);
});
