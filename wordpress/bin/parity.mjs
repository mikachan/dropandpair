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
const slugify = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

const mismatches = [];
const miss = (label, field, want, got) =>
	mismatches.push(`${label}: ${field}: expected ${JSON.stringify(want)}, got ${JSON.stringify(got)}`);
/** An unset optional value comes back from REST as 0, "", false or null. */
const blank = (v) => v === undefined || v === null || v === '' || v === 0 || v === false;
function eq(label, field, want, got) {
	const same = blank(want) && blank(got) ? true : typeof want === 'number' ? Number(got) === want : want === got;
	if (!same) miss(label, field, want, got);
}

const content = JSON.parse(readFileSync(contentFile, 'utf8'));

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

function common(label, e, p, title) {
	eq(label, 'title', title, decode(p.title.raw));
	const wantBody = text(e.body ?? e.html);
	const gotBody = text(p.content.raw);
	if (wantBody !== gotBody) miss(label, 'body text', wantBody.slice(0, 120), gotBody.slice(0, 120));
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

	// Free-form tags: WordPress slugifies the name, so accept either form.
	const gotVibes = (p.dp_vibe || []).map((id) => vibeById.get(id));
	const wantVibes = e.vibe.map(slugify).sort();
	const gotSlugs = gotVibes.map((t) => t?.slug).sort();
	if (JSON.stringify(wantVibes) !== JSON.stringify(gotSlugs)) miss(label, 'vibes', e.vibe, gotVibes.map((t) => t?.name));

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
		eq('page:about', 'title', content.pages.about.title, decode(about.title.raw));
		const want = text(content.pages.about.html);
		const got = text(about.content.raw);
		if (want !== got) miss('page:about', 'body text', want.slice(0, 120), got.slice(0, 120));
	}
}

const checked = content.parks.length + content.pairings.length + content.rides.length + (content.pages?.about ? 1 : 0);
for (const line of mismatches) console.log(line);
console.log(`${mismatches.length} mismatches (${checked} items checked against ${base})`);
process.exit(mismatches.length ? 1 : 0);
