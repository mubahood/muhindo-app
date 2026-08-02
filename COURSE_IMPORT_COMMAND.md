# AGENT COMMAND — Retire the Dummy Data, Ship the Real Course Catalog

> Paste everything below this line into the coding agent, verbatim.

---

You are the senior Laravel engineer already working in
`/Applications/MAMP/htdocs/muhindo-app`. The operating rules in `AGENT_COMMAND.md`
apply verbatim to this mission: read before you write, no skipping, no placeholders,
a verification gate after every work item, one item = one commit, worklog discipline,
server-side truth, take your time.

**Your mission:** the platform currently ships with faker/lorem-ipsum demo content.
Delete all of it, and replace it with the **real, final course catalog** now sitting in
`course-content/` — 21 professionally structured courses (`00-CATALOG.md` is the index,
`01-*.md` … `21-*.md` are the course files). When you are finished, every course a
visitor sees on `/e-learning`, every lesson a student plays, every quiz and assignment,
must come from those files and from Muhindo's real YouTube teaching. No lorem. Anywhere.

Work **one course at a time**, in catalog order, and be creative: these files are a
faithful brief, not a cage — where you can make a course *better* inside the platform
(richer descriptions, better previews, smarter quizzes, real durations, beautiful
covers), do it, and log what you added.

---

## Stage 0 — Verify every video link before anything is imported

The course files contain hundreds of YouTube links. Some IDs were reconstructed from
protected extraction output and a small number may be wrong. **Do not import a broken
link into a paying student's course.**

1. Write `php artisan courses:verify-links` — parses every `course-content/*.md`,
   extracts every YouTube video ID and playlist ID, checks each one via YouTube's
   oEmbed endpoint (`https://www.youtube.com/oembed?url=...&format=json`, ~200ms
   apart, cache results to a local JSON file so re-runs are instant), and writes
   `course-content/_link-report.md`: per course, every link with ✅ OK + real title,
   or ❌ dead.
2. Run it. For every ❌: search the channel (`youtube.com/@LearnitwithMuhindo`) for
   the lesson by its title in the MD file and substitute the correct ID; if no
   replacement exists, convert that lesson into a **text/practice lesson** with real
   written content you author (see Stage 3, creative rules) — never leave a dead link
   and never delete a lesson silently.
3. Commit the fixed MD files plus the report (`fix(courses): verify and repair all
   video links`). The report is the owner's proof; summarise the ❌ count in the log.

## Stage 1 — Purge the dummy data

4. Find and remove every source of fake content: the faker `CourseFactory` usage in
   seeders (keep the factory itself — tests need it), any demo course/module/lesson
   seeder rows, placeholder taglines, "Accusantium perferendis"-style records in the
   local database. Write `php artisan courses:purge-demo` (guarded: refuses to run
   when `APP_ENV=production` unless `--force`) that deletes demo courses **and their
   dependent rows** (modules, lessons, materials, enrollments, progress, quizzes,
   attempts, assignments, submissions, certificates, reviews) inside one transaction.
5. Verify: `/e-learning` and `/courses` render an empty state, no orphan rows remain
   (check every FK), and the test suite is still green. Commit.

## Stage 2 — Build the importer (idempotent, re-runnable)

6. Write `php artisan courses:import {file?} {--dry-run} {--force}` — a real parser +
   seeder that turns a course MD file into database records. Run twice, get the same
   result (match on course slug, module title, lesson title; update instead of
   duplicating). `--dry-run` prints what it would do and changes nothing.

**The file format it must handle** (all variants appear across the 21 files):

- `# Course NN ⭐ — Title` → course number, `is_featured` when ⭐ is present, title.
- The bold meta line: `**Tier N · Foundations · Level: … · Prerequisites: … · TOP FEATURED**`
  → tier, level (map to your `level` enum), prerequisites text, featured flag.
- The paragraph after it → course description. `**What you will learn**` bullets →
  `outcomes` (JSON). Prerequisites line → `requirements` (JSON).
- `## Module N — Name` **and** `## Phase A — Name` (capstones use Phase) → a
  `course_modules` row, in document order.
- Lessons, numbered, in two shapes:
  `1. **Title** — description.` followed by an indented `▶ https://youtu.be/...` line,
  **or** the capstone shorthand `1. Title — https://www.youtube.com/watch?v=ID`.
  A lesson with no URL is a **text lesson** (content_format = markdown).
- Fenced code blocks belong to the lesson (or module intro) they follow → append to
  that lesson's markdown content.
- `▶ Full playlist: …` / `▶ Playlist:` lines → store on the **module or course** as a
  fallback resource, never as a lesson.
- Italic attributions like `*(freeCodeCamp)*` → mark the lesson `is_external = true`
  and keep the attribution visible in the lesson body (credit matters).
- `## Final project` / `## Graduation assignment` → create a real **Assignment**.
- `**Quiz ideas…**` / `**Milestone quizzes…**` → seed a real **Quiz** (see Stage 3.9).

Add the small migrations this needs (`courses.tagline`, `outcomes`, `requirements`,
`is_featured`, `tier`, `sort_order`; `lessons.is_external`, `resource_url`) — additive
only, following the conventions already in the codebase.

7. Convert every watch URL to a privacy-friendly embed (`https://www.youtube-nocookie.com/embed/{ID}`)
   for the player, but keep the canonical watch URL stored too — students sometimes
   want to open it on YouTube and subscribe. Commit the importer with its own tests
   (parse a fixture file → assert exact module/lesson counts).

## Stage 3 — Import the courses, ONE BY ONE

For each course 01 → 21, in order, do a complete pass and **one commit per course**
(`feat(courses): import course NN — <title>`), with a worklog entry in
`COURSE_IMPORT_LOG.md` recording: modules, lessons, videos, text lessons, quiz,
assignment, and anything creative you added.

For each course:

8. **Import & sanity-check** — run the importer, then open the course in the browser
   (catalogue card, detail page, and the player as an enrolled test student). Every
   video must actually play. Fix what doesn't.
9. **Author the quiz for real.** The MD file gives *quiz ideas*, not questions. Turn
   each into 5–10 genuine questions using the quiz engine's real types (mcq_single,
   mcq_multi, true_false, fill_blank, numeric where it fits), each with a short
   explanation shown after submission. Questions must be answerable from that
   course's lessons — no trivia, no trick questions. Pass mark 70%.
10. **Author the assignment for real.** Expand the final project into clear
    instructions: what to build, what to submit, and a 4–6 line grading rubric the
    student can see before starting.
11. **Fill the metadata a student judges you by:** a one-line `tagline`, the outcomes
    list, requirements, level, category, estimated total duration (sum of real video
    durations — fetch them, don't guess), and 1–2 **free preview lessons** chosen to
    be genuinely enticing (a first real teaching lesson, never just the intro).
12. **Cover image.** Generate a branded 1280×720 cover per course from the brand
    system in `LOGO_SPEC.md` (navy/gold, course title, tier badge, subtle mark) —
    consistent set, generated by a repeatable script (`courses:make-covers`) so
    covers can be regenerated when a title changes. No stock photos, no AI slop.
13. **Verify:** pint + phpstan + full test suite green, no N+1 on the course page,
    page renders at 360px. Then commit and move to the next course.

## Stage 4 — Go beyond the brief (the part that makes this excellent)

After all 21 are imported, add what the files imply but do not spell out. Each is its
own item + commit; skip nothing without logging why:

14. **Learning paths as a real feature** — the four paths in `00-CATALOG.md` (Web
    Developer, Mobile Developer, Freelancer Fast-Track, Complete Path) become
    browsable pages with ordered courses, progress across the whole path, and a
    "you're 3 courses from finishing this path" nudge on the student dashboard.
15. **Prerequisites, wired** — the prerequisite text becomes real course→course
    links: shown on the detail page ("Best after: Course 03"), never blocking, always
    one click away.
16. **Course numbering & ordering** respected everywhere (catalogue sort, path order,
    "next course" suggestion on the completion screen — finishing 03 should
    immediately offer 04).
17. **A link-health cron** — schedule `courses:verify-links` monthly; a dead video
    raises an admin notification and flags the lesson in the admin UI. YouTube links
    rot; the platform should notice before a student does.
18. **Seed the instructor's voice** — every course detail page shows the real
    instructor block (photo, bio, the honest channel numbers already in settings).
19. **SEO per course** — JSON-LD `Course` + `offers`, real meta description from the
    tagline, OG image = the cover you generated (ties into `PUBLIC_SITE_PLAN.md` §6).
20. **Admin editability** — confirm every imported field is editable in the admin UI
    (a course must never be "locked" because it was imported). Add an "import status"
    column showing which file a course came from and when it was last synced.
21. **One genuinely new idea of your own.** Look at the catalog as a teacher would and
    add the thing you think is missing — a printable syllabus PDF per course, a
    "start here" quiz that recommends a learning path, per-module estimated time
    badges, a code-snippet library extracted from all courses, whatever you can
    defend. Propose it in the log, build it, and say why.

## Stage 5 — Pricing (owner decision — do not guess silently)

22. Put every price in ONE clearly-commented config array (`config/catalog.php`) with
    your **suggested** UGX values per tier and a `// OWNER: review these` banner.
    Import Tier 1 courses as free and publish them; leave Tier 2/3 unpublished
    drafts with suggested prices, and list them at the top of `COURSE_IMPORT_LOG.md`
    under **"NEEDS OWNER APPROVAL BEFORE PUBLISHING"**. The owner flips them live
    after reviewing one config file — not 21 database rows.

## Definition of done

- Zero faker/lorem strings anywhere in the app or database (`grep` for the old demo
  titles and for "lorem"/"Accusantium" — prove it in the log).
- All 21 courses live in the database with real modules, lessons, quizzes,
  assignments, covers, previews and metadata; the catalogue and player look and feel
  like a finished product on desktop and on a 360px phone.
- `courses:import` is idempotent (run it twice on a fresh DB: identical result),
  `courses:verify-links` is green or every ❌ is documented and handled.
- `composer ci` green from a fresh checkout; `COURSE_IMPORT_LOG.md` complete, with the
  pricing approval list at the top.

Begin with Stage 0, step 1. Read `course-content/00-CATALOG.md` and two course files
(one Tier 1, one capstone — they differ in shape) before writing any parser code.
