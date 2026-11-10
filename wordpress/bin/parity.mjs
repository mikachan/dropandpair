#!/usr/bin/env node
/**
 * Parity check: does the WordPress site hold exactly what content.json says?
 *
 *   DP_USER=admin DP_APP_PASSWORD='xxxx xxxx ...' node wordpress/bin/parity.mjs <baseUrl> [content.json]
 *
 * Reads through the REST API with context=edit (so drafts and meta are included),
 * which needs an application password. Credentials come from the environment only.
 * Exits 1 if anything differs. It never writes.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const base = (process.argv[2] || '').replace(/\/+$/, '');
const contentFile = process.argv[3] || path.join(here, '../import/content.json');
const { DP_USER, DP_APP_PASSWORD } = process.env;

if (!base || !DP_USER || !DP_APP_PASSWORD) {
	console.error('Usage: DP_USER=... DP_APP_PASSWORD=... node wordpress/bin/parity.mjs <baseUrl> [content.json]');
	process.exit(2);
}
const auth = 'Basic ' + Buffer.from(`${DP_USER}:${DP_APP_PASSWORD}`).toString('base64');

/** Every page of a collection; REST caps per_page at 100. */
async function fetchAll(route, params = '') {
	const out = [];
	for (let page = 1; ; page++) {
		const url = `${base}/wp-json/wp/v2/${route}?per_page=100&page=${page}&${params}`;
		const res = await fetch(url, { headers: { Authorization: auth } });
		if (res.status === 400 && page > 1) break; // past the last page
		if (!res.ok) throw new Error(`${res.status} ${res.statusText} for ${url}: ${(await res.text()).slice(0, 200)}`);
		const rows = await res.json();
		out.push(...rows);
		if (page >= Number(res.headers.get('x-wp-totalpages') || 1)) break;
	}
	return out;
}

const NAMED = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', mdash: '—', ndash: '–', hellip: '…', lsquo: '‘', rsquo: '’', ldquo: '“', rdquo: '”' };
const decode = (s) =>
	s.replace(/&(?:#(\d+)|#x([0-9a-f]+)|([a-z]+));/gi, (m, d, h, n) =>
		d ? String.fromCodePoint(+d) : h ? String.fromCodePoint(parseInt(h, 16)) : (NAMED[n.toLowerCase()] ?? m)
	);
/** Tags, block comments, entities and whitespace all differ legitimately between Markdown HTML and block markup. */
const text = (html) =>
	decode(String(html ?? '').replace(/<!--[\s\S]*?-->/g, '').replace(/<[^>]*>/g, ''))
		.replace(/\s+/g, ' ')
		.trim();
/** What the plain text can't show: links, images, headings and list items, in order. */
function structure(html) {
	const src = String(html ?? '').replace(/<!--[\s\S]*?-->/g, '');
	const attr = (name) => [...src.matchAll(new RegExp(`<[a-z0-9]+\\b[^>]*?\\s${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)')`, 'gi'))].map((m) => decode(m[1] ?? m[2]));
	const count = (re) => (src.match(re) || []).length;
	return {
		hrefs: attr('href'),
		srcs: attr('src'),
		headings: [1, 2, 3, 4, 5, 6].map((n) => count(new RegExp(`<h${n}\\b`, 'gi'))),
		listItems: count(/<li\b/gi)
	};
}
const slugify = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const mismatches = [];
const miss = (label, field, want, got) =>
	mismatches.push(`${label}: ${field}: expected ${JSON.stringify(want)}, got ${JSON.stringify(got)}`);
/**
 * REST returns 0 for unset numeric meta and "" for unset string meta, so "unset"
 * and "stored as 0" look the same. Strings are compared strictly (content strings
 * are never empty); a stored 0 can't be verified, so main() refuses to run on one.
 */
function eq(label, field, want, got) {
	const unset = got === undefined || got === null || got === '' || got === 0 || got === false;
	let same;
	if (want === undefined || want === null) same = unset;
	else if (typeof want === 'number') same = Number(got) === want;
	else if (typeof want === 'string') same = got === want;
	else same = want === got;
	if (!same) miss(label, field, want, got);
}

class Unverifiable extends Error {}
/** A 0 in content.json can't be told from "unset" through REST, so fail loudly rather than pass silently. */
function assertVerifiable(content) {
	const bad = [];
	for (const r of content.rides) {
		for (const [k, v] of Object.entries(r.stats || {})) if (v === 0) bad.push(`dp_ride:${r.id}: stats.${k} is 0`);
		for (const k of ['opened', 'intensity', 'roughness']) if (r[k] === 0) bad.push(`dp_ride:${r.id}: ${k} is 0`);
	}
	for (const kind of ['parks', 'rides', 'pairings'])
		for (const e of content[kind])
			for (const [k, v] of Object.entries(e)) if (v === '' && k !== 'body') bad.push(`${kind}:${e.id}: ${k} is an empty string`);
	if (bad.length)
		throw new Unverifiable(
			'content.json has values REST cannot tell apart from unset, so parity cannot verify them:\n  ' +
				bad.join('\n  ') + '\nCheck these with `wp post meta get` instead.'
		);
}

async function main() {
const content = JSON.parse(readFileSync(contentFile, 'utf8'));
assertVerifiable(content);

const [parks, rides, pairings, pages, rideTypes, vibes, moments] = await Promise.all([
	fetchAll('dp_park', 'context=edit&status=any'),
	fetchAll('dp_ride', 'context=edit&status=any'),
	fetchAll('dp_pairing', 'context=edit&status=any'),
	fetchAll('pages', 'context=edit&status=any'),
	fetchAll('dp_ride_type', 'hide_empty=false'),
	fetchAll('dp_vibe', 'hide_empty=false'),
	fetchAll('dp_moment', 'hide_empty=false')
]);

const bySlug = (rows) => new Map(rows.map((r) => [r.slug, r]));
const termSlug = (rows) => new Map(rows.map((t) => [t.id, t]));
const typeById = termSlug(rideTypes);
const vibeById = termSlug(vibes);
const momentById = termSlug(moments);
const parkBySlug = bySlug(parks);
const pairingById = new Map(pairings.map((p) => [p.id, p]));

function both(kind, expected, actual) {
	const have = bySlug(actual);
	for (const e of expected) if (!have.has(e.id)) mismatches.push(`${kind}:${e.id}: missing in WordPress`);
	const want = new Set(expected.map((e) => e.id));
	for (const a of actual) if (!want.has(a.slug)) mismatches.push(`${kind}:${a.slug}: in WordPress but not in content.json`);
	return have;
}

function bodyMatches(label, wantHtml, gotHtml) {
	const wantBody = text(wantHtml);
	const gotBody = text(gotHtml);
	if (wantBody !== gotBody) miss(label, 'body text', wantBody.slice(0, 120), gotBody.slice(0, 120));
	const w = structure(wantHtml);
	const g = structure(gotHtml);
	for (const k of Object.keys(w)) if (JSON.stringify(w[k]) !== JSON.stringify(g[k])) miss(label, 'body ' + k, w[k], g[k]);
}

function common(label, e, p, title) {
	eq(label, 'title', title, p.title.raw);
	bodyMatches(label, e.body ?? e.html, p.content.raw);
}

const parkHave = both('dp_park', content.parks, parks);
for (const e of content.parks) {
	const p = parkHave.get(e.id);
	if (!p) continue;
	const label = `dp_park:${e.id}`;
	common(label, e, p, e.name);
	eq(label, 'country', e.country, p.meta.dp_country);
	eq(label, 'region', e.region, p.meta.dp_region);
	eq(label, 'website', e.website, p.meta.dp_website);
}

const pairingHave = both('dp_pairing', content.pairings, pairings);
for (const e of content.pairings) {
	const p = pairingHave.get(e.id);
	if (!p) continue;
	const label = `dp_pairing:${e.id}`;
	common(label, e, p, e.title);
	eq(label, 'description', e.description, p.meta.dp_description);
	eq(label, 'url', e.url, p.meta.dp_url);
	eq(label, 'image', e.image, p.meta.dp_image);
	const got = (p.dp_moment || []).map((id) => momentById.get(id)?.slug);
	if (got.length !== 1 || got[0] !== e.pairingType) miss(label, 'moment', [e.pairingType], got);
}

const rideHave = both('dp_ride', content.rides, rides);
for (const e of content.rides) {
	const p = rideHave.get(e.id);
	if (!p) continue;
	const label = `dp_ride:${e.id}`;
	const m = p.meta;
	common(label, e, p, e.name);
	const park = parkBySlug.get(e.park);
	eq(label, 'park', e.park, [...parkBySlug.values()].find((x) => x.id === m.dp_park_id)?.slug);
	if (!park) miss(label, 'park', e.park, 'no such park in WordPress');
	eq(label, 'opened', e.opened, m.dp_opened);
	eq(label, 'manufacturer', e.manufacturer, m.dp_manufacturer);
	eq(label, 'added', e.added, m.dp_added);
	for (const k of ['height', 'speed', 'length', 'inversions']) eq(label, k, e.stats?.[k], m['dp_' + k]);
	eq(label, 'intensity', e.intensity, m.dp_intensity);
	eq(label, 'roughness', e.roughness, m.dp_roughness);
	eq(label, 'gist', e.gist, m.dp_gist);
	eq(label, 'featured', Boolean(e.featured), Boolean(m.dp_featured));

	const types = (p.dp_ride_type || []).map((id) => typeById.get(id)?.slug);
	if (types.length !== 1 || types[0] !== e.rideType) miss(label, 'ride type', [e.rideType], types);

	// Free-form tags: compare the slug (WordPress slugifies) and the name as written.
	const gotVibes = (p.dp_vibe || []).map((id) => vibeById.get(id));
	const wantVibes = e.vibe.map(slugify).sort();
	const gotSlugs = gotVibes.map((t) => t?.slug).sort();
	const gotNames = gotVibes.map((t) => t?.name).sort();
	if (JSON.stringify(wantVibes) !== JSON.stringify(gotSlugs) || JSON.stringify([...e.vibe].sort()) !== JSON.stringify(gotNames))
		miss(label, 'vibes', e.vibe, gotNames);

	const wantFR = (e.furtherReading || []).map((r) => ({ title: r.title, url: r.url }));
	const gotFR = (m.dp_further_reading || []).map((r) => ({ title: r.title, url: r.url }));
	if (JSON.stringify(wantFR) !== JSON.stringify(gotFR)) miss(label, 'further reading', wantFR, gotFR);

	// Visitor suggestions land here too (they carry suggested_by); the imported links are the ones with no suggester.
	const gotLinks = (m.dp_pairings || []).map((l) => ({
		pairing: pairingById.get(l.pairing_id)?.slug ?? `#${l.pairing_id}`,
		blurb: l.blurb,
		strength: Number(l.strength)
	}));
	const wantLinks = (e.pairings || []).map((l) => ({ pairing: l.pairing, blurb: l.blurb, strength: l.strength }));
	if (JSON.stringify(wantLinks) !== JSON.stringify(gotLinks)) miss(label, 'pairings', wantLinks, gotLinks);
}

if (content.pages?.about) {
	const about = pages.find((p) => p.slug === 'about');
	if (!about) mismatches.push('page:about: missing in WordPress');
	else {
		eq('page:about', 'title', content.pages.about.title, about.title.raw);
		bodyMatches('page:about', content.pages.about.html, about.content.raw);
	}
}

const checked = content.parks.length + content.pairings.length + content.rides.length + (content.pages?.about ? 1 : 0);
for (const line of mismatches) console.log(line);
console.log(`${mismatches.length} mismatches (${checked} items checked against ${base})`);
process.exit(mismatches.length ? 1 : 0);
}

try {
	await main();
} catch (e) {
	console.error(`parity script failed (not a content mismatch): ${e.message}`);
	process.exit(2);
}
