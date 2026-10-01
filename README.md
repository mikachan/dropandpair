# Drop and Pair

Curated pairings for theme park rides. Every ride gets a short gist and a set of things worth putting either side of it: a film, a record, a meal, a walk, or occasionally a hot bath.

A static site. Content is Markdown in this repository, validated at build time and prerendered to plain HTML — there is no database, no CMS and no server.

## Stack

- **SvelteKit** with `@sveltejs/adapter-static`, fully prerendered
- **Svelte 5** (runes) and **TypeScript**
- **Zod** for frontmatter validation, **gray-matter** + **marked** for parsing
- **Plain CSS** with custom properties — no framework
- **Pagefind** for static search, run as a postbuild step
- **Vitest**, **ESLint**, **Prettier**, **svelte-check**

## Commands

| Command           | What it does                                            |
| ----------------- | ------------------------------------------------------- |
| `npm run dev`     | Dev server. Drafts are visible.                         |
| `npm start`       | Same as `npm run dev`.                                  |
| `npm run build`   | Builds to `build/`, then indexes it with Pagefind.      |
| `npm run preview` | Serves `build/` over HTTP. The only way to test search. |
| `npm run check`   | `svelte-check` type checking.                           |
| `npm run lint`    | Prettier check plus ESLint.                             |
| `npm run format`  | Rewrites files to Prettier style.                       |
| `npm test`        | Vitest — the content loader, the graph, and filtering.  |

`npm run build` runs `pagefind --site build` afterwards via npm's `postbuild`
hook, so there is no separate indexing step to remember. Running `vite build`
directly skips it, and search will 404.

`npm run preview` is a small dependency-free static server
(`scripts/preview.js`) rather than `vite preview`. It has to be: `vite preview`
serves the app from SvelteKit's manifest and never sees `build/pagefind`, so
search would always look broken. The script resolves paths the usual way for a
directory of static files — exact file, then `<path>.html`, then `404.html`.

## Project structure

```
content/            the site's actual content — see content/README.md
  rides/*.md
  parks/*.md
  pairings/*.md
src/lib/content/    the content pipeline
  constants.ts        ride and pairing type vocabularies (no dependencies)
  schemas.ts          Zod schemas — the source of truth for frontmatter
  parse.ts            pure: raw files -> validated entries
  graph.ts            pure: entries -> reverse lookups, reference checks
  load.server.ts      the only module that reads the files
  labels.ts           every user-facing label and scale word
  view.ts             narrow, serialisable shapes for pages
src/lib/rides/      URL-driven filtering for /rides
src/lib/components/ RideCard, PairingCard, PairingBadge, IntensityMeter,
                    FilterBar, Header, Footer, Seo
src/routes/         pages, plus sitemap.xml and rss.xml
scripts/preview.js  static server for `npm run preview`
```

The content pipeline is split so the interesting parts are pure functions with
no filesystem and no Vite in them. `parse.ts` and `graph.ts` take data in and
give data out, which is why they can be unit tested directly.

## Adding content

### A ride

Create `content/rides/<slug>.md`. **The file name must match the `id`.**

```markdown
---
id: nemesis # kebab-case, same as the file name
name: Nemesis
park: alton-towers # must match a file in content/parks/
rideType: coaster # coaster | dark-ride | flat-ride | water-ride | drop-tower
opened: 1994 # year
manufacturer: Bolliger & Mabillard
added: 2026-09-29 # optional; orders the RSS feed
intensity: 5 # 1-5, shown as "intensity"
roughness: 2 # 1-5, shown as "comfort"
featured: true # optional; puts it on the front page
draft: false # optional; true hides it from production
stats: # optional, and only where it says something
  height: 13 #   metres
  speed: 80 #   km/h
  length: 716 #   metres
  inversions: 4
vibe: # free text, at least one
  - brutal
  - scenic
gist: >-
  Wet rock and rusted iron, a long hang in the trees.
pairings:
  - pairing: alien-1979 # must match a file in content/pairings/
    blurb: Same register exactly. Watch it the same evening.
    strength: 5 # 1-5, how well it suits *this* ride
furtherReading: # optional; links listed at the bottom of the ride page
  - title: Wikipedia
    url: https://en.wikipedia.org/wiki/Nemesis_Reborn
---

The Markdown body is the long write-up, and is optional.
```

`stats` units are metric and are not converted — write metres and km/h, or omit
the field. Only fill in what is meaningful: a dark ride rarely needs a speed.

### A park

```markdown
---
id: alton-towers
name: Alton Towers
country: United Kingdom
region: Staffordshire, England
website: https://www.altontowers.com # optional
draft: false # optional
---
```

### A pairing

```markdown
---
id: alien-1979
title: Alien (1979)
pairingType:
  watch # before | right-after | wind-down | eat-and-drink
  # do-next | listen | read | watch | later
description: >-
  Ninety per cent waiting, ten per cent consequence, and every frame damp.
url: https://example.com/somewhere # optional
image: /images/poster.jpg # optional; local paths only
draft: false # optional
---
```

`pairingType` values are ordered as you would meet them — before the ride,
straight after, then later — and that order drives the grouping on a ride page
and the filter menus. It is defined once, in `src/lib/content/constants.ts`.

Relationships are only ever written in one direction: a ride lists its
pairings. A pairing's list of rides and a park's list of rides are computed at
build time, so there is nothing to keep in sync by hand.

## How validation works

`npm run build` fails if content is wrong, and tells you where. Two stages:

1. **Frontmatter** (`parse.ts`) — checked against the Zod schemas. Catches
   missing fields, a value outside 1–5, an unknown `rideType`, a remote image,
   a mistyped key, a duplicate `id`, and an `id` that disagrees with its file
   name. Unknown keys are rejected, so `intensitty: 5` is an error rather than
   a field that silently does nothing.
2. **References** (`graph.ts`) — every `park` and every `pairings[].pairing`
   must exist, and a ride cannot list the same pairing twice.

Every problem found is reported together, rather than stopping at the first:

```
Content validation failed — 2 problems:

  content/rides/nemesis.md
    • park: refers to "alton-towerz", but there is no content/parks/alton-towerz.md
    • pairings.1.pairing: refers to "alien-1978", but there is no content/pairings/alien-1978.md
```

References are validated across _all_ content, drafts included, so a typo can
never hide until the day you publish.

## Drafts

Set `draft: true` on any ride, park or pairing that is not ready — in
particular anything whose facts you have not checked. Drafts are visible in
`npm run dev` with a dashed "Draft" marker, and excluded from `npm run build`.

Removing a draft prunes anything that pointed at it: a reference to a draft
pairing disappears from the ride, and a ride in a draft park is dropped too. A
published build therefore cannot contain a dead internal link.

> **The seed content is placeholder.** Several entries are `draft: true` with a
> `TODO: verify` note because their opening year or manufacturer is unverified.
> Check them against a reliable source, then remove the flag and the note.

## The site URL

`src/lib/site.ts` and `static/robots.txt` both carry the site's address, and it
needs to be the real one: canonical tags, Open Graph tags, the sitemap and the
feed all require absolute URLs. Set it in both places before the site goes
anywhere public.

`npm run build` writes plain static files to `build/`, including `404.html`.

## Notes and deliberate choices

- **Fonts are system stacks.** No webfont request, no layout shift, decent
  character everywhere. To swap in a downloaded display serif later, change
  `--font-display` in `src/lib/styles/tokens.css` and add an `@font-face`.
- **Filtering needs JavaScript.** A static site generates one file for
  `/rides`, so the prerendered page is the full list — which is what a visitor
  without JavaScript should see anyway. The controls hide themselves in that
  case, and `/pairings` offers a no-JS way to browse by type.
- **Search needs the build.** Pagefind indexes `build/`, so `npm run dev` has
  no index. `/search` says so and links to the browse pages instead.
- **`{@html}` is used for content bodies.** Markdown is authored by hand in
  this repository and rendered at build time, so it is trusted. That stops
  being true the moment content comes from anywhere else.
