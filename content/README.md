# House style

How Drop and Pair sounds, and how to write a gist. The mechanics of
each field are in the root `README.md`; this is about the words.

## The voice

An enthusiast who knows their stuff and is having fun with it. Pairing a ride
with a film or a plate of chips is a slightly daft idea taken seriously — so the
seriousness has to be real. Describe the ride precisely and let the idea do the
comedy on its own.

**Do**

- Commit to an opinion. "The lift hill is the ride" is worth reading.
- Be specific and physical: wet rock, cold steel, the smell of the queue line.
- Let a ride be flawed and loved at the same time. That is most of British
  enthusiasm.
- Write short. A ride write-up is three or four paragraphs, not an essay.
- Use British English and the second person where it helps: _you_ queue, _you_
  walk off.

**Don't**

- Don't borrow wine or whisky vocabulary. No "full-bodied", "cask strength",
  "finish", "vintage" or "notes of". The site is its own thing, not a drinks
  menu with rides on it.
- Don't say "thrilling", "adrenaline-pumping", "must-ride" or "hidden gem".
- Don't rank. Pairings are matches, not scores out of ten.
- Don't explain the joke. No "pairings — get it?".
- Don't state a fact you have not checked. See **Facts** below.

## Sounding like Sarah

The site is written by one person, in the first person, so it should sound like
her talking about rides she loves rather than a copywriter.

**Her voice**

- Plain and warm. Say the thing simply instead of reaching for a clever line.
- First person where it's natural: "one of my favourite rides", "I think".
- Contractions everywhere: _it's_, _you'll_, _don't_.
- Casual British words, used now and then: really, lovely, brilliant,
  properly, massive.
- The odd exclamation mark is fine, roughly one every few pieces, not every
  paragraph.
- Explain why. "Tea's perfect for this because you can't drink it in a hurry"
  beats "Not optional."

**Signs it's drifted into AI copy**

- Uncontracted phrasing: "you are", "it is", "do not", "you will".
- Punchy one-line closers: "That is the point." "That is the entire
  mechanism." "Which is exactly why it works." "That's the whole trick."
- "X, not Y" pairs: "A pot, not a paper cup. A chair, not a wall."
- Fancy phrasing: "the change of register", "a sentence you would not believe
  if it were not true", "let the reputation settle on you".
- Clipped commands with no reason: "Not optional." "Go somewhere with soft
  edges."
- Em dashes used for drama.

If a sentence sounds like it's trying to be quotable, rewrite it the way you'd
say it to a friend in the queue.

## The gist

The `gist` is the one line that does the most work: it appears on cards,
in search results, in the RSS feed and as the page's meta description. Treat it
like the line on a poster.

**Shape.** One or two sentences, around 15–30 words, with contractions.
Lead with the ride's world: its story, its setting, or the one thing it does
that nothing else does. Then, if there's room, the hook for pairing it.

> You're the latest shift sent down a haunted gold mine. The train stops over
> the shaft, the spirits start wailing, and then it lets go.

> A slow turn above Vienna on a wheel that's been going since 1897. Watch The
> Third Man first and you'll spend the whole ride looking down.

**Don't repeat the meters.** The intensity and comfort words are already on
the page, so a gist that ends "Strong, smooth, and…" is just reading them back.
It's also the rhythm of a wine tasting note, which is exactly what this site
isn't. If you do mention how a ride feels, keep it consistent with the meters:
don't call a ride with `roughness: 5` silky.

**Not this:**

> An absolutely incredible ride with amazing inversions and a thrilling drop
> that will leave you breathless! A must-ride for any enthusiast.

## The scales

Two scales describe every ride. Use the words in prose sparingly; the UI
already labels them.

| Field           | Reads as      | Means                                        |
| --------------- | ------------- | -------------------------------------------- |
| `intensity` 1–5 | **Intensity** | How hard it hits. 1 gentle, 5 maximum.       |
| `roughness` 1–5 | **Comfort**   | How you feel walking off. 1 silky, 5 brutal. |

The words for each step live in `src/lib/content/labels.ts`. `opened` is shown
plainly as the year it opened. Older is not worse.

Intensity and comfort are independent, and saying so is often the interesting part: a
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

> Bring a towel, because you'll get off this one soaked. You're also at the
> English seaside, so it's probably windy too.

> Stand on the bridge over the pit first. You'll hear the train before you see
> it, and it really builds up the nerves.

The pairing's own `description` is the general case — what the thing is, for
anyone. The `blurb` is the argument for pairing it with one ride. Don't repeat
one in the other.

## The moments

Every pairing has a moment, and the moments are ordered as you would meet
them. Pick the one that answers _when_, not _what_:

| Type            | When                                        |
| --------------- | ------------------------------------------- |
| `before`        | Before you ride.                            |
| `right-after`   | Straight after, within minutes.             |
| `wind-down`     | Something gentle, after something brutal.   |
| `eat-and-drink` | Once you're off the ride.                   |
| `do-next`       | Where to go while the adrenaline wears off. |
| `listen`        | The queue, the car park, the drive home.    |
| `read`          | The hotel, the flight, the closed season.   |
| `watch`         | A film or show that belongs with the ride.  |
| `later`         | Later, or another day entirely.             |

A film can be a `watch` or a `later`, and a bar can be a `right-after` or an
`eat-and-drink`. Choose by timing. If two fit, pick the one that makes the
ride page read better.

## Vibes

`vibe` is free text, so it is the easiest field to make a mess of. Reuse an
existing word before inventing one — every vibe becomes a filter option, and
`rattly` plus `rattling` plus `rough ride` is three filters for one idea.

In use so far: `apocalyptic`, `brutal`, `dizzying`, `eerie`, `fiery`, `industrial`,
`indoor`, `nostalgic`, `scenic`, `stately`, `weightless`, `whimsical`.

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

## Further reading

`furtherReading` is an optional list of links shown at the bottom of a ride
page. Use it for the sources behind the facts: Wikipedia, RCDB, the park's own
page. If you checked a fact somewhere, that is usually the link to add.

Keep titles short and plain — `Wikipedia`, `RCDB` — and say so in the title if
the page is under a different name, as with a renamed ride.

## Links

`url` is optional, and shows as a "Find it" link. Choose the pairing first and look for a link afterwards. A pairing that exists
because it has a link available is the thing this site is not for.
