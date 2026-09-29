import matter from 'gray-matter';
import { marked } from 'marked';
import type { z } from 'zod';
import { pairingSchema, parkSchema, rideSchema } from './schemas';
import type { ContentMeta, Pairing, Park, Ride } from './types';

/**
 * Turning Markdown files into validated entries.
 *
 * Deliberately pure: it takes raw file contents in and gives entries out, with
 * no filesystem and no Vite. `load.server.ts` is the only place that knows
 * where the files actually live, which keeps everything interesting here
 * testable in isolation.
 */

export interface RawFile {
	/** Path relative to the repo root, e.g. `content/rides/nemesis.md`. */
	path: string;
	raw: string;
}

export interface Collections {
	rides: Ride[];
	parks: Park[];
	pairings: Pairing[];
}

export interface ContentProblem {
	path: string;
	/** Dotted frontmatter path, e.g. `pairings.0.strength`. Empty for whole-file problems. */
	field: string;
	message: string;
}

/** Thrown for any invalid content, listing every problem rather than just the first. */
export class ContentError extends Error {
	readonly problems: ContentProblem[];

	constructor(problems: ContentProblem[]) {
		super(formatProblems(problems));
		this.name = 'ContentError';
		this.problems = problems;
	}
}

export function formatProblems(problems: ContentProblem[]): string {
	const byPath = new Map<string, ContentProblem[]>();
	for (const problem of problems) {
		const existing = byPath.get(problem.path);
		if (existing) existing.push(problem);
		else byPath.set(problem.path, [problem]);
	}

	const count = problems.length;
	const lines = [`Content validation failed — ${count} problem${count === 1 ? '' : 's'}:`, ''];
	for (const [path, group] of byPath) {
		lines.push(`  ${path}`);
		for (const problem of group) {
			lines.push(`    • ${problem.field ? `${problem.field}: ` : ''}${problem.message}`);
		}
		lines.push('');
	}
	return lines.join('\n');
}

marked.use({ gfm: true, breaks: false });

function renderBody(markdown: string): string {
	const trimmed = markdown.trim();
	return trimmed ? marked.parse(trimmed, { async: false }).trim() : '';
}

/** `content/rides/nemesis.md` -> `nemesis` */
function slugFromPath(path: string): string {
	const file = path.split('/').pop() ?? path;
	return file.replace(/\.md$/, '');
}

function parseEntries<S extends z.ZodType<{ id: string }>>(
	files: RawFile[],
	schema: S,
	problems: ContentProblem[]
): Array<z.output<S> & ContentMeta> {
	const entries: Array<z.output<S> & ContentMeta> = [];
	const seen = new Map<string, string>();

	for (const file of files) {
		let frontmatter: unknown;
		let markdown: string;
		try {
			const parsed = matter(file.raw);
			frontmatter = parsed.data;
			markdown = parsed.content;
		} catch (error) {
			problems.push({
				path: file.path,
				field: '',
				message: `could not read frontmatter — ${(error as Error).message}`
			});
			continue;
		}

		const result = schema.safeParse(frontmatter);
		if (!result.success) {
			for (const issue of result.error.issues) {
				problems.push({
					path: file.path,
					field: issue.path.join('.'),
					message: issue.message
				});
			}
			continue;
		}

		const entry = result.data;
		const expectedId = slugFromPath(file.path);
		if (entry.id !== expectedId) {
			problems.push({
				path: file.path,
				field: 'id',
				message: `is "${entry.id}" but the file name says "${expectedId}" — they must match`
			});
			continue;
		}

		const duplicate = seen.get(entry.id);
		if (duplicate) {
			problems.push({
				path: file.path,
				field: 'id',
				message: `duplicates the id already used by ${duplicate}`
			});
			continue;
		}
		seen.set(entry.id, file.path);

		entries.push({ ...entry, body: renderBody(markdown), sourcePath: file.path });
	}

	return entries;
}

/**
 * Parses every collection, then throws a single {@link ContentError} if
 * anything at all was wrong. Cross-collection references are checked later, in
 * `graph.ts`, once we know which ids exist.
 */
export function parseCollections(input: {
	rides: RawFile[];
	parks: RawFile[];
	pairings: RawFile[];
}): Collections {
	const problems: ContentProblem[] = [];

	const collections: Collections = {
		rides: parseEntries(input.rides, rideSchema, problems),
		parks: parseEntries(input.parks, parkSchema, problems),
		pairings: parseEntries(input.pairings, pairingSchema, problems)
	};

	if (problems.length > 0) throw new ContentError(problems);
	return collections;
}
