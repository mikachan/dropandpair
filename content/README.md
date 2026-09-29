# House style

How Drop and Pair sounds, and how to write a tasting note. The mechanics of
each field are in the root `README.md`; this is about the words.

## The voice

Sommelier, slightly tongue-in-cheek, never winking at the camera. The joke is
that ride pairings are treated with the seriousness of a wine list — so the
seriousness has to be real. Describe the ride precisely and let the borrowed
vocabulary do the comedy on its own.

**Do**

- Commit to an opinion. "The lift hill is the ride" is worth reading.
- Be specific and physical: wet rock, cold steel, the smell of the queue line.
- Let a ride be flawed and loved at the same time. That is most of British
  enthusiasm.
- Write short. A ride write-up is three or four paragraphs, not an essay.
- Use British English and the second person where it helps: _you_ queue, _you_
  walk off.

**Don't**

- Don't pile up the wine words. One or two per paragraph, then stop. "A
  full-bodied vintage with a long finish and notes of terroir on the nose" is
  parody, not house style.
- Don't say "thrilling", "adrenaline-pumping", "must-ride" or "hidden gem".
- Don't rank. Pairings are matches, not scores out of ten.
- Don't explain the joke. No "pairings — get it?".
- Don't state a fact you have not checked. See **Facts** below.

## Tasting notes

The `tastingNote` is the one line that does the most work: it appears on cards,
in search results, in the RSS feed and as the page's meta description. Treat it
like the back of a bottle.

**Shape.** One or two sentences. A list of sensations, then a verdict. Around
20–35 words.

**A good pattern** — three sensations, then the body and finish:

> Notes of wet rock and rusted iron, a long hang in the trees, then four
> seconds somewhere else entirely. Cask strength, silky finish.

> Salt air, cold steel and a lift hill long enough to have second thoughts on.
> Full-bodied, coarse finish, unmistakably of its vintage.

Naming the body and finish words in the note is optional but effective, because
it matches what the meters on the page say. Keep them consistent: don't write
"silky finish" on a ride with `roughness: 5`.

**Not this:**

> An absolutely incredible ride with amazing inversions and a thrilling drop
> that will leave you breathless! A must-ride for any enthusiast.

## The borrowed vocabulary

Three words carry the metaphor. Use them in prose sparingly; the UI already
labels them.

| Field           | Reads as    | Means                                              |
| --------------- | ----------- | -------------------------------------------------- |
| `intensity` 1–5 | **Body**    | How hard it hits. 1 light-bodied, 5 cask strength. |
| `roughness` 1–5 | **Finish**  | How you feel walking off. 1 silky, 5 abrasive.     |
| `opened`        | **Vintage** | The year it opened. Older is not worse.            |

Body and finish are independent, and saying so is often the interesting part: a
ride can be enormous and still silky, or mild and still rattle your teeth.

`strength` on a pairing is a _match_ rating, not a quality rating. A five means
this pairing belongs with **this ride**, not that the film is a masterpiece.

## Pairing blurbs

The `blurb` on a ride's pairing is the most-read sentence on the site, because
it appears on the ride page and again on the pairing page. It answers one
question: _why this, after this?_

- One or two sentences. Say the reason, not the recommendation.
- Make it specific to the ride. If the blurb would work on any ride, it is not
  finished.
- Give the instruction if there is one: when, where, how soon.

> Not optional. You will finish this ride wet through, and you are at the
> English seaside, where the wind is a participant.

> Stand on the bridge over the pit first. You will hear it before you see it,
> which is the whole trick.

The pairing's own `description` is the general case — what the thing is, for
anyone. The `blurb` is the argument for pairing it with one ride. Don't repeat
one in the other.

## The courses

Pairings go in courses, ordered as you would meet them. Pick the one that
answers _when_, not _what_:

| Type              | When                                          |
| ----------------- | --------------------------------------------- |
| `aperitif`        | Before you ride.                              |
| `chaser`          | Straight after, within minutes.               |
| `palate-cleanser` | Something gentle, after something brutal.     |
| `eat-and-drink`   | Once the restraints are up.                   |
| `do-next`         | Where to walk while the adrenaline wears off. |
| `listen`          | The queue, the car park, the drive home.      |
| `read`            | The hotel, the flight, the closed season.     |
| `watch`           | A film or show that belongs with the ride.    |
| `nightcap`        | Later, or another day entirely.               |

A film can be a `watch` or a `nightcap`, and a bar can be a `chaser` or an
`eat-and-drink`. Choose by timing. If two fit, pick the one that makes the
flight on the ride page read better.

## Vibes

`vibe` is free text, so it is the easiest field to make a mess of. Reuse an
existing word before inventing one — every vibe becomes a filter option, and
`rattly` plus `rattling` plus `rough ride` is three filters for one idea.

In use so far: `brutal`, `drenching`, `eerie`, `industrial`, `nostalgic`,
`rattly`, `scenic`, `seaside`, `stately`, `weightless`, `whimsical`.

Two or three per ride. Adjectives, lowercase, singular.

## Facts

This is the one hard rule, because it is the only part of the site that can be
_wrong_ rather than merely disagreed with.

- **Never write a statistic, year or manufacturer from memory.** Check it, or
  leave the field out. `stats` is optional precisely so it can be omitted.
- If you are unsure of anything in the frontmatter, set `draft: true` with a
  `TODO: verify` comment on that line, and add a visible note at the top of the
  body saying what needs checking. Drafts never reach production.
- Never claim a real-world connection — "this park was in that film", "this is
  the tallest in Europe" — unless you have checked it. A mood pairing needs no
  justification; a factual claim does.
- Opinion is free. "The lift hill is the ride" needs no source. "The lift hill
  is 65 metres" does.

Rides get retracked, renamed, re-profiled and closed. When something changes,
correct the file rather than adding a note on top of the old text.

## Affiliate links

`url` is optional. If it is an affiliate link, set `affiliate: true` — that
labels it in the UI and marks it `rel="nofollow sponsored"`. Never set an
affiliate link without the flag.

Choose the pairing first and look for a link afterwards. A pairing that exists
because it has a link available is the thing this site is not for.
