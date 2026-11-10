import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { beforeAll, describe, expect, it } from 'vitest';
import { exportContent } from './export-content.js';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');

describe('exportContent', () => {
	let data;
	beforeAll(async () => {
		data = await exportContent(repoRoot);
	});

	it('exports every collection, drafts included', async () => {
		expect(data.parks).toHaveLength(16);
		expect(data.rides).toHaveLength(30);
		expect(data.pairings).toHaveLength(18);
	});

	it('keeps frontmatter fields and renders bodies as HTML', async () => {
		const hyperion = data.rides.find((ride) => ride.id === 'hyperion');
		expect(hyperion.park).toBe('energylandia');
		expect(hyperion.stats.height).toBe(77);
		expect(hyperion.roughness).toBe(3);
		expect(hyperion.pairings).toHaveLength(3);
		expect(hyperion.pairings[0]).toMatchObject({ pairing: 'hyperion-dan-simmons', strength: 3 });
		for (const ride of data.rides) expect(ride.body.startsWith('<p>')).toBe(true);
	});

	it('includes the about page prose', async () => {
		expect(data.pages.about.title).toBe('About');
		expect(data.pages.about.html).toContain('<h2>Thank you</h2>');
		const html = data.pages.about.html;
		expect(html).toContain('Before the queue and after the drop.');
		const labels = [...html.matchAll(/<dt>(.*?)<\/dt>/g)].map((m) => m[1]);
		expect(labels).toEqual([
			'Before',
			'Right after',
			'Wind down',
			'Eat and drink',
			'Do next',
			'Listen',
			'Read',
			'Watch',
			'Later'
		]);
		expect(html).toContain(
			'<dd>For the hotel, the flight, or the long wait over the winter closed season.</dd>'
		);
	});
});
