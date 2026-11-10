import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { exportContent } from './export-content.js';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');

describe('exportContent', () => {
	it('exports every collection, drafts included', async () => {
		const data = await exportContent(repoRoot);
		expect(data.parks).toHaveLength(16);
		expect(data.rides).toHaveLength(30);
		expect(data.pairings).toHaveLength(18);
	});

	it('keeps frontmatter fields and renders bodies as HTML', async () => {
		const data = await exportContent(repoRoot);
		const hyperion = data.rides.find((ride) => ride.id === 'hyperion');
		expect(hyperion.park).toBe('energylandia');
		expect(hyperion.stats.height).toBe(77);
		expect(hyperion.roughness).toBe(3);
		expect(hyperion.pairings).toHaveLength(3);
		expect(hyperion.pairings[0]).toMatchObject({ pairing: 'hyperion-dan-simmons', strength: 3 });
		for (const ride of data.rides) expect(ride.body.startsWith('<p>')).toBe(true);
	});

	it('includes the about page prose', async () => {
		const data = await exportContent(repoRoot);
		expect(data.pages.about.title).toBe('About');
		expect(data.pages.about.html).toContain('<h2>Thank you</h2>');
	});
});
