# muhindo-app — LMS Mastery Plan (v2)

**Independent architecture review + advanced build plan for the learning platform.**

Scope: everything a student touches (learning, assessment, progress) and everything you need
to monitor them. This document is the successor to the LMS sections of `PROJECT_PLAN.md`
(§4, §9, §10) — that plan built the v1 skeleton; this plan turns it into a serious,
error-free, delightful LMS. Reviewed against the actual code as of 2026‑07‑26.

Idea sources researched and borrowed from: **Google Classroom** (stream, assignment
turn-in workflow, due dates, grading + rubrics), **Moodle** (quiz engine, question banks,
attempt lifecycle, activity completion, restrict-access/prerequisites, gradebook, badges),
**Udemy** (player UX, resume playback, curriculum sidebar, timestamped notes, Q&A,
reviews, completion nudges).

---

## 1. Current state — what actually exists (audited)

The v1 LMS shipped in the last build pass is a clean, thin skeleton:

| Piece | File(s) | State |
|---|---|---|
| Catalogue | `CourseCatalogueController`, `courses/{index,show}.blade.php` | Published courses list + detail; free self-enroll works; paid checkout is a "coming soon" flash message |
| Data model | `Course`, `CourseModule`, `Lesson`, `LessonMaterial`, `Enrollment`, `LessonProgress`, `Certificate` | Course → Module → Lesson → Material; enrollment unique per (user, course); progress row per (enrollment, lesson) |
| Player | `Student/LearningController`, `learn/{index,course,lesson}.blade.php` | Sidebar curriculum + iframe video + text content + "Mark complete & continue" full-page POST |
| Progress | `LessonProgress.completed_at`, `Enrollment::progressPercent()` | Binary per-lesson completion only; `watch_seconds` column exists but **nothing ever writes it** |
| Certificate | `issueCertificate()` on 100%, DomPDF streamed | Auto-issued, numbered via `VerificationCode`; `pdf_path` never populated; no public verification page |
| Admin | `Admin/{Course,CourseModule,Lesson,LessonMaterial,Enrollment}Controller` | CRUD + manual enroll; enrollment list; **no per-student progress view** |
| Dashboards | `DashboardService`, `roles/student.blade.php` | Counts only (enrolled, completed); admin sees enrollment totals |
| API | `Api/V1/{Course,Enrollment}Controller` | Catalogue, my-enrollments, enroll, complete-lesson |

What does **not** exist yet, at all: quizzes, assignments, any assessment, grades,
announcements, Q&A/discussions, notes, watch telemetry, resume position, drip/prerequisites,
completion rules beyond "clicked the button", learning analytics, at-risk detection,
reviews/ratings, coupons, paid checkout wiring, enrollment expiry, AJAX in the learn flow.

---

## 2. Loophole & defect audit (ranked)

These are concrete holes in the current implementation. Each gets closed by a numbered
work item in §8. Severity: 🔴 integrity/security, 🟠 correctness, 🟡 quality/UX.

**L1 🔴 Certificates attest nothing.** `complete()` marks a lesson done on any POST —
a student can click through an entire course in 60 seconds and receive a signed
certificate. There is no minimum watch time, no assessment gate, no sequencing. A
certificate from "Learn It With Muhindo" is currently a click counter. → Close with
completion rules (§4.3), quiz gates (§5), and certificate criteria (§4.6).

**L2 🔴 Enrollment status is never checked in the player.** `enrollmentFor()` only does
`firstOrFail()` on (user, course). A `pending` (unpaid), `cancelled`, or future
`expired` enrollment grants full lesson access, materials, and completion posting.
One-line fix today; formalized in EnrollmentPolicy (§4.2).

**L3 🔴 No verification path for certificates.** `QrService` exists, the HMS ancestor
had verifiable documents, but `certificate_no` resolves nowhere. Anyone can forge a
PDF. → Public `/verify/{certificate_no}` page + QR on the PDF (§4.6).

**L4 🟠 `watch_seconds` is dead schema.** The column exists but the player embeds a bare
`<iframe>` — no YouTube IFrame API, no heartbeat, no position tracking. You cannot answer
"did they watch it?", "where did they stop?", or "resume at 12:34". → Player telemetry (§6.2).

**L5 🟠 `is_free_preview` is dead schema.** Flagged lessons are not viewable by
non-enrolled visitors anywhere — the catalogue shows titles only and `/learn` requires
enrollment. The Udemy-style "preview this lesson" conversion lever is unwired. (§7.2)

**L6 🟠 Paid enrollment dead-ends.** `enroll()` flashes "contact me directly" for priced
courses while the billing machinery (Invoice, Flutterwave gateway, webhook) is already
running for client invoices in the same codebase. The revenue path is the least finished
path. → Checkout wiring (§7.1).

**L7 🟠 Destructive cascades on content edits.** `lessons` cascade-deletes from modules,
`lesson_progress` cascade-deletes from lessons — deleting/restructuring a module silently
destroys student progress history and can un-complete finished courses. Lessons/modules
need soft deletes + progress preserved (courses already soft-delete). (§4.7)

**L8 🟠 Race/exception path on double-enroll.** The `(user_id, course_id)` unique index
protects the DB, but a double-click on the enroll button throws an uncaught
`QueryException` (500) instead of the friendly redirect. Wrap in upsert/`firstOrCreate`. (§8, P0)

**L9 🟡 N+1 queries across the learn surfaces.** `learn.index` and the student dashboard
call `progressPercent()` per card: each runs `lessonCount()` (hasManyThrough COUNT) +
a progress COUNT. 10 enrollments ≈ 21+ queries. Same shape in admin enrollments list.
→ `withCount`/cached counters + a denormalized `progress_percent` on enrollments (§6.1).

**L10 🟡 The player is 2005-era full-page-reload.** Completing a lesson re-renders
everything; there is no AJAX anywhere in `/learn`; `axios` is installed and unused.
Every interaction below (§7.3) becomes async.

**L11 🟡 Lesson content is `nl2br(e(...))` plain text.** No rich text, no code blocks
(you teach programming!), no images, no embeds. → Markdown pipeline with server-side
sanitized rendering + syntax highlighting (§7.4).

**L12 🟡 No "continue where you left off".** Nothing records last accessed lesson or
timestamp; `learn.course` always redirects to lesson #1. Enrollment needs
`last_lesson_id` + `last_accessed_at` — these two columns also power all of the
at-risk analytics in §6.

**L13 🟡 No indexes for the queries you'll run.** `lesson_progress` has no index on
`(lesson_id, completed_at)`; `enrollments` none on `status`/`last_accessed_at`;
fine at 10 students, painful at 1,000. (§8, P0)

**L14 🟡 API surface can drift from web rules.** `Api/V1/EnrollmentController::completeLesson`
duplicates the web completion logic today, and will silently miss every rule added later
(sequencing, gates) unless completion moves into one service used by both. → `ProgressService` (§4.4).

---

## 3. What to borrow, feature by feature

| Source | Feature to borrow | Where it lands here |
|---|---|---|
| Moodle | Quiz engine: question bank per course, attempt lifecycle (in-progress → submitted → graded), grading methods (highest/average/first/last), per-question feedback, shuffle | §5 quiz module |
| Moodle | Activity completion + restrict access (lesson B locked until lesson A complete / quiz ≥ X%) | §4.3 completion rules |
| Moodle | Gradebook aggregating quiz + assignment marks into a course grade | §5.4 |
| Moodle | Badges / competencies | §6.5 gamification (later phase) |
| Google Classroom | Assignment turn-in flow: assigned → turned in → returned with grade + comments; resubmission | §5.3 assignments |
| Google Classroom | Stream/announcements per course with email fan-out | §7.5 |
| Google Classroom | Due dates + "missing/turned in late" states feeding the monitoring views | §6.3 |
| Udemy | Player: resume position, playback speed, autoplay-next, keyboard shortcuts | §7.3 |
| Udemy | Curriculum sidebar with per-module progress, lecture durations, ✓ states | §7.3 |
| Udemy | Timestamped personal notes; Q&A per lecture; course reviews | §7.6, §6.5 |
| Udemy | Free preview lessons as the conversion lever; coupons | §7.2, §7.1 |
| All three | Instructor analytics: per-student drill-down, cohort funnel, engagement minutes | §6.3–6.4 |

Design rule adopted from all three: **the student experience is one page per course**
(player + tabs: Overview / Q&A / Notes / Announcements / Reviews), and **the instructor
experience is one page per course** (Curriculum / Students / Assessments / Analytics tabs).
Avoid scattering the LMS across a dozen admin menu items as it grows.

---

## 4. Core architecture upgrades (foundation before features)

### 4.1 One service layer for learning logic

New `app/Services/Learning/` (mirrors the existing `BillingService` convention):

- `ProgressService` — the **only** writer of `lesson_progress`, enrollment percent,
  completion, certificate triggering. Web controller, API controller, and any future
  import job all call it. Kills L14.
- `QuizService` — attempt lifecycle + auto-grading (§5.2).
- `AssignmentService` — submissions, grading, return flow.
- `GradebookService` — aggregates into a course grade.
- `LearningEventRecorder` — single funnel for telemetry events (§6.2).
- `CertificateService` — issue, render→store PDF, verify. (Salvage the `_legacy`
  CertificateService pattern noted in PROJECT_PLAN §5.)

All completion writes wrapped in DB transactions; certificate issuance made idempotent
(`firstOrCreate` on enrollment_id) so a double-submit can't issue two certificates.

### 4.2 Policies, not inline checks

`EnrollmentPolicy@access` (status must be `active` or `completed`, not expired),
`LessonPolicy@view` (enrolled + unlocked per §4.3, or `is_free_preview`),
`QuizAttemptPolicy`, `SubmissionPolicy`. Register in `AppServiceProvider`; the same
policies guard web + API (they already share this pattern for projects/invoices).
Closes L2 permanently instead of once.

### 4.3 Completion rules engine (Moodle's "activity completion", simplified)

Per lesson, a `completion_rule` enum:

- `manual` — current behaviour (default, backwards compatible)
- `min_watch` — requires `watch_seconds ≥ completion_threshold%` of `duration_seconds`
  (server-verified from telemetry, §6.2 — not from the client's claim)
- `quiz_pass` — requires a passing attempt on the lesson's quiz
- `submission` — requires an assignment submission (optionally: a *graded-pass* one)

Per course, `progression` enum: `free` (navigate anywhere) | `sequential` (lesson N+1
locked until N complete — Moodle restrict-access, single-chain version). The sidebar
renders locked items with a padlock; `LessonPolicy` enforces it server-side (never
trust the UI). Closes the heart of L1.

### 4.4 Data model changes to existing tables

```
enrollments      + last_lesson_id (nullable FK), last_accessed_at (index),
                 + progress_percent (tinyint, denormalized, updated by ProgressService),
                 + expires_at (nullable — access windows), + total_watch_seconds (int)
lessons          + duration_seconds (rename/replace duration_minutes),
                 + completion_rule (enum), completion_threshold (tinyint, default 80),
                 + content_format ('markdown'|'plain', default plain), + softDeletes
course_modules   + softDeletes
lesson_progress  + started_at, last_position_seconds (resume point), attempts summary
                   columns stay OUT of here (quizzes own their attempts),
                 + index (lesson_id, completed_at)
courses          + progression (enum free|sequential), + certificate_requires ('lessons'|
                   'lessons_and_quizzes'), + published_at, + enrollment_limit (nullable)
certificates     + verify UUID (public token), pdf actually stored to pdf_path
```

Migration discipline: additive migrations only; `duration_minutes` backfilled ×60 into
`duration_seconds`, then dropped in a later cleanup migration.

### 4.5 Events & listeners (decouple side-effects)

Laravel events: `LessonCompleted`, `CourseCompleted`, `QuizAttemptSubmitted`,
`AssignmentSubmitted`, `SubmissionGraded`, `EnrollmentCreated`. Listeners handle:
database notifications (existing `notifications` table + bell UI already built),
email (existing mail infra), certificate issuance, streak/badge updates, and the
learning-events log. Controllers stay thin; queue the listeners (Horizon is installed).

### 4.6 Certificates that mean something

- Issue only when `certificate_requires` is satisfied (all lessons complete **and**,
  if the course has quizzes marked `counts_toward_certificate`, average ≥ pass mark).
- Generate the PDF once, store to `pdf_path` (private disk), stream from storage after.
- QR code (existing `QrService`) → `https://…/verify/{uuid}` public page: shows student
  name, course, issue date, validity — the anti-forgery loop. Closes L3.
- Optional: LinkedIn "Add to profile" deep link on the student's certificate card (free
  marketing, Udemy does this).

### 4.7 Content-edit safety

Soft-delete modules/lessons; deleting a lesson keeps progress rows (FK →
`nullOnDelete` on a new `lesson_title_snapshot`, or simply rely on soft deletes so the
FK target survives). Recompute `progress_percent` for affected enrollments on any
lesson add/remove (queued job) — adding a lesson to a finished course must *not*
retroactively revoke certificates: completed enrollments freeze.

---

## 5. Assessment: quizzes (auto-graded) + assignments

The single biggest capability gap. Modeled on Moodle's quiz engine + Classroom's
assignment flow, cut down to what one instructor actually needs.

### 5.1 Schema (new tables)

```
quizzes            id, lesson_id (nullable — lesson quiz) , course_id (course-level/final),
                   title, description, time_limit_minutes (nullable), max_attempts
                   (nullable=∞), pass_percent (default 70), grading_method
                   (highest|latest|average|first), shuffle_questions bool,
                   shuffle_options bool, questions_per_attempt (nullable — pool draw),
                   one_question_per_page bool, feedback_mode
                   (immediate|after_submit|after_close|none),
                   counts_toward_certificate bool, is_published, available_from/until,
                   softDeletes, timestamps

questions          id, quiz_id, type (mcq_single|mcq_multi|true_false|fill_blank|
                   numeric|matching|ordering|short_text|essay),
                   prompt (markdown), explanation (markdown, shown per feedback_mode),
                   points (default 1), sort_order, meta JSON (type-specific config:
                   numeric tolerance, fill_blank accepted answers + case_sensitive flag,
                   matching pairs, ordering sequence), timestamps

question_options   id, question_id, label (markdown), is_correct bool, sort_order,
                   match_key (nullable, for matching type)

quiz_attempts      id, uuid, quiz_id, enrollment_id, attempt_no, status
                   (in_progress|submitted|graded|abandoned), started_at, submitted_at,
                   graded_at, score_points, max_points, score_percent, passed bool,
                   time_spent_seconds, question_order JSON (frozen shuffle),
                   integrity JSON (tab blurs, ip, user_agent), timestamps
                   UNIQUE (quiz_id, enrollment_id, attempt_no)

attempt_answers    id, quiz_attempt_id, question_id, answer JSON (shape per type),
                   is_correct (nullable until graded), points_awarded, auto_graded bool,
                   grader_feedback (nullable), answered_at
                   UNIQUE (quiz_attempt_id, question_id)

assignments        id, course_id, lesson_id (nullable), title, instructions (markdown),
                   due_at (nullable), points (default 100), allow_late bool,
                   late_penalty_percent (nullable), max_file_mb, allowed_types
                   (csv: pdf,zip,txt,link,text), resubmit_until_graded bool,
                   is_published, softDeletes, timestamps

assignment_submissions
                   id, uuid, assignment_id, enrollment_id, attempt_no, body (nullable
                   text answer), link_url (nullable), file_path/name/size/mime (nullable),
                   status (draft|submitted|returned), submitted_at, is_late bool,
                   points_awarded (nullable), feedback (markdown), graded_by, graded_at
                   UNIQUE (assignment_id, enrollment_id, attempt_no)
```

Files go on the **private** disk streamed through a policy check — exactly the pattern
`ProjectDocumentController` already implements; reuse `DocumentService`.

### 5.2 Auto-grading engine (`QuizService`)

Attempt lifecycle (all server-authoritative):

1. **start(quiz, enrollment)** — policy check (published, availability window, attempts
   left, enrollment active). Creates attempt `in_progress`, freezes the shuffled
   question/option order and pool draw into `question_order`, stamps `started_at`.
   Re-entering an unfinished attempt resumes it (never a second parallel attempt).
2. **answer(attempt, question, payload)** — AJAX autosave per question (Moodle-style),
   upsert into `attempt_answers`. The student can lose power and lose nothing.
3. **submit(attempt)** — validates timer server-side: reject if
   `now > started_at + time_limit + 30s grace`. Grades synchronously (it's cheap):

   | Type | Auto-grade rule |
   |---|---|
   | mcq_single / true_false | selected option `is_correct` |
   | mcq_multi | default **partial credit**: (correct picked − wrong picked)/total correct, floor 0 |
   | fill_blank | normalized (trim/casefold/whitespace-collapse) match against accepted answers list |
   | numeric | `abs(answer − expected) ≤ tolerance` |
   | matching | fraction of pairs correct |
   | ordering | all-or-nothing by default; option: pairwise-adjacency partial credit |
   | short_text | exact/keyword list match → auto; else flagged for review |
   | essay | never auto-graded → attempt status `submitted` until instructor grades |

   If every question auto-graded → status `graded`, compute percent, `passed`,
   fire `QuizAttemptSubmitted`. Essay/short-text-review quizzes land in the
   **grading queue** (§6.3) and grade later via `gradeManual()`.
4. **Feedback** per `feedback_mode`: immediate (after each question — practice mode),
   after_submit (review page with per-question ✓/✗ + explanations), after_close, none
   (exam mode: score only).

Anti-cheat toolkit (pragmatic, not oppressive): shuffle questions + options per attempt,
question pools (draw N of M so neighbours get different quizzes), one-question-per-page
option, server timer, attempt limits, copy-paste-disable OFF by default (it only annoys
honest students), and an `integrity` JSON that logs tab-blur count + focus time via the
player's visibility listener — surfaced to you as a signal, never an automatic penalty.

### 5.3 Assignments (Classroom's loop)

Student: sees assignment (with due date, points) on the lesson page or course Assignments
tab → submits text/link/file (draft-save supported) → status `submitted`, `is_late`
computed server-side. You: grading queue shows ungraded submissions oldest-first →
open a submission (file streams inline where previewable) → points + markdown feedback →
**Return** → student notified, sees grade + feedback; resubmission allowed per
`resubmit_until_graded`/`allow_late`. Every state change fires notifications through §4.5.

### 5.4 Gradebook

`GradebookService` computes per enrollment: each quiz's counted grade (per its
`grading_method`), each assignment's points (late penalty applied), → weighted course
grade (weights default equal; optional per-item weight column later). Surfaces:

- Student: "Grades" tab — item list, score, feedback links, current course grade.
- You: per-course grade matrix (students × items), CSV export, and the certificate
  gate reads it (§4.6).

---

## 6. Student monitoring & learning analytics

The "how do I know what my students are doing" layer — the part you specifically asked
to think deeply about.

### 6.1 Denormalized fast-path (dashboard numbers)

`enrollments.progress_percent`, `total_watch_seconds`, `last_accessed_at`,
`last_lesson_id` — maintained by `ProgressService` on every event. All list views and
dashboards read these columns; no N+1, closes L9/L12.

### 6.2 Event stream (the truth layer)

One append-only table (a pragmatic xAPI-lite):

```
learning_events   id, enrollment_id, lesson_id (nullable), subject_type/subject_id
                  (quiz attempt, submission…), event (enum: lesson.viewed,
                  video.play, video.pause, video.heartbeat, video.ended,
                  lesson.completed, quiz.started, quiz.submitted, material.downloaded,
                  note.created, question.asked), value JSON (position, seconds…),
                  created_at (index), INDEX (enrollment_id, created_at)
```

Fed by:
- **Player heartbeat**: YouTube IFrame API (and `<video>` events for self-hosted later)
  → every 15s of *actual playing time* POST `{lesson, position, seconds_delta}` to an
  AJAX endpoint (beacon on unload). This is what makes `watch_seconds`,
  `last_position_seconds` (resume!), and `min_watch` completion (§4.3) real. Closes L4.
- Server-side hooks for everything else (view, complete, download, quiz events).

Retention: raw events pruned after ~12 months by a scheduled command; the aggregates
live forever on enrollments/progress rows. Volume at your scale is a non-issue, but the
prune job means it never becomes one.

### 6.3 Instructor monitoring surfaces (build in this order)

1. **Course → Students tab** (the workhorse): one row per enrollment — student, %,
   grade-to-date, total watch time, last active (“3 days ago”, red if > 14),
   current lesson, quiz avg, missing assignments. Sortable/filterable (reuse the
   existing Livewire `WithTable` concern). Row click → per-student drill-down.
2. **Per-student drill-down**: timeline of `learning_events`, per-lesson
   watched-vs-duration bars, every attempt + submission with scores, notes you keep on
   the student (private), buttons: message/nudge, extend access, reset quiz attempts.
3. **Grading queue** (global, cross-course): ungraded essay questions + submissions,
   oldest first, with due-date context. This is your daily inbox.
4. **Course analytics tab**: enrollment funnel (enrolled → started → 25/50/75% →
   completed → certified), per-lesson drop-off chart (where do people quit? — the
   single most actionable instructor chart Udemy gives), quiz item analysis
   (per-question correct-rate; a question everyone fails is a bad question or a bad
   lesson), watch-time histogram. Charts via the existing `components/dash/*`
   sparkline/bars components — no new chart library needed.
5. **Owner dashboard widgets**: active students this week, minutes watched,
   completion rate, at-risk count, revenue per course (once §7.1 lands).

### 6.4 At-risk detection (rules first, no ML pretence)

Nightly scheduled command tags each active enrollment:

- `inactive` — `last_accessed_at` > 14 days (7 for short courses)
- `stalled` — progress unchanged 3 weeks despite logins
- `struggling` — quiz average < pass mark, or 2+ consecutive failed attempts
- `missing_work` — assignment past due, no submission

Effects: badge on the Students tab, owner dashboard counter, and an optional weekly
digest email to you ("5 students at risk: …"). One-click **nudge** sends the student a
friendly templated email ("You're 60% through Laravel Basics — the next lesson is
12 minutes"). Udemy's re-engagement emails, self-hosted. Later (Phase 4+): streaks and
badges for the positive side of the same signal.

### 6.5 Student-facing progress (motivation loop)

Course card ring (% complete), per-module progress in the sidebar, "resume where you
left off" hero button (uses `last_lesson_id` + `last_position_seconds`), grades tab,
certificate progress hint ("2 lessons + final quiz left"), optional weekly streak
counter. Deliberately later-phase: leaderboards (unhealthy for tiny cohorts).

---

## 7. Commerce, content & UI/UX perfection

### 7.1 Finish the money path (closes L6)

Wire course checkout through the machinery that already exists for client invoices:
`enroll()` on a priced course → create `pending` enrollment + Invoice (billable = user)
→ `GatewayPaymentService::start()` → Flutterwave → webhook (already implemented)
verifies → `BillingService` marks paid → listener activates enrollment + notification
+ receipt. Idempotent on webhook retries (existing gateway_logs dedup). Add: coupons
(`coupons`: code, percent/amount, max_uses, expires_at, course scope) applied at
invoice creation — Udemy's core growth lever, nearly free to build on this stack.
Refund path: admin cancels enrollment → access revoked, invoice credited.

### 7.2 Free preview (closes L5)

Catalogue course page renders `is_free_preview` lessons' player for guests (no
enrollment), with a sticky "Enroll to continue" card. This is the #1 conversion feature
of every commercial LMS.

### 7.3 The player, rebuilt (Udemy-grade, AJAX everywhere) — closes L10

One Alpine.js component per page, `axios` (already installed) + JSON endpoints,
CSRF via the existing meta token, and the existing toast host for feedback:

- **Complete without reload**: POST → JSON `{progress_percent, next_lesson_url,
  certificate?}` → optimistic ✓ in sidebar, animated progress bar, auto-advance card
  ("Next: Eloquent Relationships — starting in 5s ⏸"), confetti + certificate modal at
  100%. Errors roll back the optimistic state and toast.
- **YouTube IFrame API** wrapper: resume at `last_position_seconds`, playback speed
  control, autoplay-next toggle, heartbeat telemetry (§6.2), `min_watch` auto-completes
  the lesson when threshold crossed (button becomes "Completed ✓" live).
- **Keyboard**: space play/pause, ←/→ ±10s, ↑/↓ prev/next lesson, `m` mark complete.
- **Sidebar**: per-module accordion with counts (3/5 ✓, 42 min), locked padlocks
  (sequential mode), durations, active-lesson highlight, mobile slide-over (reuse
  `components/ui/slideover`).
- **Quiz runner**: autosave per answer (debounced, "Saved ✓" indicator), server-synced
  countdown timer, one-question-per-page option, review screen, animated result.
- **Notes tab**: timestamped notes (`lesson_notes`: enrollment_id, lesson_id,
  seconds, body) — click a note → seek player. Autosaved.
- **Q&A tab**: `discussions` (course_id, lesson_id nullable, user_id, parent_id, body,
  is_instructor_answer, resolved_at) — per-lesson threads, you get a notification and
  an "Instructor" badge on replies. Doubles as your teaching feedback loop.
- **Announcements tab**: `announcements` (course_id, title, body markdown, published_at)
  → notification + optional email to all active enrollments (Classroom's stream).
- **Reviews**: `course_reviews` (enrollment_id unique, rating 1–5, body, is_published)
  — prompted once at ≥50% progress; average shown on catalogue cards. Moderated
  (you publish).

Perceived-performance rules for every AJAX surface: optimistic UI where reversible,
skeleton loaders where not, every mutation idempotent server-side, every response
< 300ms p95 (these are count/upsert queries — the indexes in §4.4/§5.1 guarantee it),
and graceful degradation: every AJAX form still has a real `action` and works without JS.

### 7.4 Content authoring (closes L11)

`content_format=markdown` lessons rendered via `league/commonmark` (server-side,
sanitized, no raw HTML passthrough) + Shiki/highlight.js for code blocks, images
uploadable into lesson content (private-disk, streamed). Admin editor: split-pane
markdown editor with live preview (SimpleMDE/EasyMDE class of widget, Alpine-wrapped).
Existing plain-text lessons keep rendering exactly as today (`plain` default).

### 7.5 Admin curriculum UX

Course builder page: drag-and-drop reorder of modules/lessons (SortableJS + one
AJAX reorder endpoint writing `sort_order` in a transaction), inline add-lesson,
publish toggles per lesson, duration auto-fetch for YouTube URLs (oEmbed), quiz/
assignment attach buttons inline in the curriculum tree. Bulk enroll (paste emails →
accounts created with the existing WelcomeCredentials mail flow).

### 7.6 Accessibility & mobile (non-negotiables)

Keyboard-navigable player and quiz runner, focus states, `aria-live` on progress/save
indicators, captions field on lessons (YouTube captions pass through), color-contrast
check on the navy/gold theme in both light/dark, all tap targets ≥ 44px, player layout
verified at 360px. Quiz timer announced to screen readers at 5-min/1-min marks.

---

## 8. Execution roadmap (phased, each phase shippable)

**P0 — Hardening (do first, ~a day of work, no new features)**
1. Enrollment status check in `enrollmentFor()` + `EnrollmentPolicy` (L2)
2. `firstOrCreate` on enroll (L8); throttle enroll + contact routes
3. Soft deletes on modules/lessons (L7)
4. Indexes: `lesson_progress(lesson_id, completed_at)`, `enrollments(status)`,
   `enrollments(last_accessed_at)` (L13)
5. `withCount`-based progress on list views (kills the worst N+1s) (L9)
6. Certificate idempotency (`firstOrCreate`) + store PDF + `/verify/{uuid}` page + QR (L3)
7. Extract `ProgressService`; web + API call it (L14)
8. Feature tests for every one of the above (the suite + CI script already exist)

**P1 — Monitoring core (the "see everything" release)**
Enrollment fast-path columns (§6.1) + backfill migration • `learning_events` +
server-side recording • Students tab + per-student drill-down (§6.3.1–2) •
resume/continue UX (§6.5) • at-risk nightly command + dashboard counter (§6.4)

**P2 — Player & AJAX (the "feels professional" release)**
YouTube IFrame API + heartbeat + resume (§7.3) • AJAX completion + sidebar states •
markdown lessons (§7.4) • free preview (§7.2) • completion rules `manual`/`min_watch`
+ sequential progression (§4.3) • events/listeners/notifications (§4.5)

**P3 — Assessment (the "real school" release)**
Quiz schema + `QuizService` + auto-grading (§5.1–5.2) • quiz runner UI • assignments +
submissions + grading queue (§5.3, §6.3.3) • gradebook (§5.4) • certificate criteria
tighten (§4.6 → closes L1 fully) • quiz item analysis (§6.3.4)

**P4 — Commerce & community**
Flutterwave course checkout + coupons + refunds (§7.1) • announcements + Q&A + notes +
reviews (§7.3 tabs) • bulk enroll + drag-drop builder (§7.5) • course analytics tab
funnel/drop-off (§6.3.4) • nudge emails + weekly instructor digest

**P5 — Polish & scale (ongoing)**
Badges/streaks (§6.5) • enrollment expiry windows • self-hosted video option (signed
URLs) • API v1 parity for every new module (mobile app readiness) • accessibility
audit pass (§7.6) • load-test the heartbeat endpoint • prune job for events

**Definition of done, every phase**: policies enforce it server-side · feature tests
cover the happy path + the abuse path (the matching L-item) · `composer ci` green
(pint, phpstan, empty-file check, secrets scan, tests) · works without JS ·
works at 360px · no query count regressions on the learn surfaces
(assert with `expectsDatabaseQueryCount` in tests).

---

## 9. Error-proofing checklist (the "no room for error" list)

Integrity: server-side validation for every rule the UI implies (locks, timers,
attempt limits, due dates, enrollment status) · idempotent completion, certificate
issuance, webhook handling, quiz submit (double-click safe) · transactions around all
multi-row writes · private-disk + policy-streamed for every student/instructor file ·
signed/UUID public tokens only (never sequential IDs) on verification and gateway
surfaces · throttle auth, enroll, contact, heartbeat, and quiz-answer endpoints ·
activity-log (already installed) on grade changes and enrollment mutations — grades
are auditable.

Correctness: one `ProgressService` code path for web + API · frozen shuffle order per
attempt (regrade-safe) · timezone-safe due dates (store UTC, render in
`Africa/Kampala`) · money stays in the existing bcmath/decimal-string convention ·
completed enrollments freeze when curricula change.

Quality gates: phpstan clean on every new service · a factory + seeder for every new
model (demo course with quiz + assignment seeded for local dev) · OpenAPI spec updated
as API grows (controller already exists) · Telescope in dev to watch query counts on
the player.

---

*Prepared as an independent architecture review, 2026‑07‑26. Companion to
`PROJECT_PLAN.md` — that document describes how v1 was assembled; this one describes
how it becomes excellent.*
