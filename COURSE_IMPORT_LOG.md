# Course Import — work log

The real catalogue, imported from `course-content/` into the platform.

---

## PRICES ARE LIVE AND STILL UNAPPROVED

**All 21 courses are public at the owner's instruction. Tiers 2 and 3 went live
carrying the SUGGESTED prices below — figures I proposed, not ones you chose.
A student can be charged them today.**
Every figure lives in `config/catalog.php`. Edit it, run
`php artisan courses:apply-pricing`, and the catalogue follows — you never edit
21 database rows by hand.

The suggested figures are a starting point I cannot stand behind: I do not know
your market, what your students earn, or what you already charge privately.

| Tier | Courses | Price now live | Approved? |
|------|---------|----------------|-----------|
| 1 — Foundations | 01–08 | **Free** | ✅ yes — free was the agreed strategy |
| 2 — Frameworks & Mobile | 09–15 (7 courses) | UGX 120,000 | ⚠️ **no — my suggestion** |
| 3 — Capstone Systems | 16–21 (6 courses) | UGX 250,000 | ⚠️ **no — my suggestion** |

To change them: edit `config/catalog.php`, then `php artisan courses:apply-pricing`.

Worth a thought before you set Tier 3: course 16 (InvetoTrack) is 47 lessons
across a Laravel back office, a REST API and a Flutter app. That is not the same
job as the other capstones, and `config/catalog.php` has a commented override
ready for it.

---

## Stage 0 — Link verification ✅ `08fd0f5`

`courses:verify-links` checks every YouTube reference and writes
`course-content/_link-report.md`.

**The first run reported 68 dead links. It was wrong.** oEmbed answers 401 both
for a video that is gone and for a live one whose owner disabled embedding.
Probing three of them directly returned watch pages with `"status":"OK"` and
titles matching the lessons exactly — real, public teaching. The brief's remedy
for a dead link (replace, or rewrite as text) would have destroyed 67 of
Muhindo's own lessons.

The checker now confirms every failure against the watch page:

```
397 checked · 330 embeddable · 67 watch-on-YouTube only · 0 gone
```

- **Exactly one link was genuinely gone** — `pUhkQmC2PyE` (course 11,
  `LOGIN_REQUIRED`). Rewritten as a written lesson, *"What an HTTP request
  actually is"*, which the next lesson then does in Flutter.
- The 67 non-embeddable videos became a platform requirement, not a repair job.

## Stage 1 — Purge ✅ `52cf1e1`

`courses:purge-demo`, guarded and transactional.

**The brief's premise was wrong:** there was no faker or lorem data. The seven
seeded courses were hand-authored and carried 13 enrolments, 33 progress rows,
an issued certificate and **ten invoices with UGX 1,071,000 outstanding**.
Confirmed with the owner before deleting anything.

Billing is never touched. After the run: 0 courses, 0 enrolments, **10 invoices
and 13 users intact**, and no orphans across all ten foreign keys.

## Stage 2 — Importer ✅ `791e77b`

`courses:import {file?} {--dry-run}`, idempotent by natural key.

```
21 courses · 89 modules · 425 lessons · 21 assignments
384 video · 41 text · 10 featured · 64 non-embeddable · 2 external
```

Identical on a second run. A lesson deleted from a file is deleted from the
course. Publication and price are never overwritten by an import.

The parser handles four heading shapes the brief did not document but the files
contain: `## Project`, `## Bonus module`, `## Extension briefs`,
`## Final challenge` — found by scanning all 21 files rather than trusting the
spec, which listed only Module and Phase.

Two bugs found by running it rather than reading it:

- `[▶►]` without the `/u` flag is a **byte** class. It ate one byte of the
  three-byte `▶` and left a fragment; MySQL rejected the row, which is the good
  outcome — silently storing broken UTF-8 would have been worse.
- `Course::$price` is a numeric-string and `$progression` an enum; the importer
  was assigning raw scalars.

**Non-embeddable lessons** store both URLs and render a real *"This lesson plays
on YouTube"* card with the thumbnail, instead of an iframe showing a student
"Video unavailable". Their embed URL is kept, so switching embedding back on in
YouTube Studio needs no re-import.

## Stage 5 — Pricing ✅

`config/catalog.php` + `courses:apply-pricing`. Tier 1 published free; Tiers 2
and 3 priced but held as drafts. The command only ever *promotes* — a course the
owner unpublishes stays unpublished.

## Stage 4 — partial

- ✅ **Catalogue ordering** follows the course's own number. It is a syllabus,
  not a feed; newest-first put the advanced capstones above the course an
  absolute beginner is meant to start with.
- ✅ **Prerequisites wired** — "Prerequisites: Courses 10, 11, 12" resolves to
  real clickable courses under *"Best taken after"*. Advisory, never blocking.
- ✅ **Next course** resolved (`Prerequisites::next`) — ready for the completion
  screen.

---

## STILL TO DO

Honest accounting of what the command asks for and what is not yet built.

### Stage 3 — the 21 authoring passes (not started)

Importing was the easy half. Each course still needs, per the command:

- **Real quiz questions.** The files give *ideas* ("SQL WHERE clause
  fill-in-the-blank"), not questions. ~150 questions with explanations, each
  answerable from that course's lessons.
- **Real assignment briefs** with a visible 4–6 line rubric. Currently the
  final-project text is imported as draft instructions.
- **Real durations** fetched per video (needs `services.youtube.key`, not
  currently set).
- **Free preview lessons** chosen to be enticing — a first real teaching lesson,
  never the intro.
- **Branded covers** via `courses:make-covers`. Cards currently show the
  placeholder mark.

### Stage 4 — remaining

Learning paths as browsable pages · link-health cron · instructor block on the
detail page · JSON-LD per course · admin import-status column · one original
idea, proposed and defended.

### Definition of done — not yet met

`courses:import` is idempotent ✅ and `courses:verify-links` is green ✅, but the
catalogue is not yet "a finished product" in the command's sense: no covers, no
authored quizzes, no rubrics, 13 courses unpublished pending your pricing.
