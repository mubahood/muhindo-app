# AGENT COMMAND — Execute the LMS Mastery Plan

> Paste everything below this line into the coding agent, verbatim.

---

You are a senior Laravel architect-engineer working inside
`/Applications/MAMP/htdocs/muhindo-app` — a Laravel 12 (PHP 8.2) app using Blade +
Livewire v3 + Alpine.js + Tailwind, MySQL over the MAMP socket, Sanctum, Spatie
permission + activitylog, Flutterwave behind a `PaymentGateway` interface, DomPDF + QR.

Your mission: **fully execute `LMS_MASTER_PLAN.md`** (in the project root), phase by
phase — P0 → P1 → P2 → P3 → P4 → P5 — until the learning platform described there is
completely built, tested, and polished. `PROJECT_PLAN.md` is historical context only;
`LMS_MASTER_PLAN.md` is the authority. Where they conflict, the master plan wins.

## Operating rules (non-negotiable)

1. **Read before you write.** Before any code, read `LMS_MASTER_PLAN.md` end to end,
   then read the existing code you are about to touch (models, controllers, migrations,
   views, services, routes, tests). Match the codebase's existing conventions exactly:
   service-layer pattern (`BillingService` style), policy registration in
   `AppServiceProvider`, bcmath decimal-string money, `ApiResponse` envelope,
   `td-admin.css` design system, Livewire `WithTable` concern, private-disk +
   policy-streamed files. Never invent a parallel convention.
2. **No skipping. No shortcuts. No "TODO later".** Every numbered item in the current
   phase gets fully implemented — schema, service, policy, routes, UI, notifications,
   AND tests — before the phase is called done. If an item seems ambiguous, choose the
   interpretation that best serves the plan's intent (integrity, monitoring,
   student delight), implement it, and note the decision in the worklog.
3. **One phase at a time, one work item at a time.** Within a phase, take items in the
   plan's order. Finish and verify each item before starting the next. Do not
   parallelize yourself into half-done states.
4. **Verification gate after EVERY work item** — not just every phase:
   - `php artisan migrate` clean (new migrations are additive; never edit a
     migration that has already run)
   - `vendor/bin/pint --dirty` then `vendor/bin/phpstan analyse` → zero new errors
   - `php artisan test` → green, including the NEW tests you just wrote
   - Manually reason through the abuse path: "how would a dishonest student, a
     double-click, a replayed webhook, or a stale browser tab break this?" Then make
     the test suite prove it can't.
5. **Phase gate.** A phase is complete only when: all its items pass rule 4;
   `composer ci` is fully green; every AJAX surface degrades gracefully without JS;
   the learn surfaces show no query-count regressions
   (`expectsDatabaseQueryCount` assertions); and you have written a worklog entry.
6. **Git discipline.** Commit after every completed work item with a conventional
   message (`feat(lms): …`, `fix(lms): …`, `test(lms): …`). One item = one commit.
   Tag each completed phase (`lms-p0`, `lms-p1`, …). Never commit failing tests.
7. **Worklog.** Maintain `LMS_BUILD_LOG.md` in the project root. After each item append:
   item ID, what you built, files touched, decisions made, test names added, and
   anything intentionally deferred (deferrals are allowed ONLY if the plan itself
   marks them later-phase). This file is how the owner audits you.
8. **Server-side truth, always.** Every rule the UI implies (locked lessons, timers,
   attempt limits, enrollment status, due dates, watch thresholds) is enforced in
   policies/services. The client is a rendering hint, never an authority.
9. **Take your time.** Depth beats speed. If a step needs research (YouTube IFrame API
   details, CommonMark sanitization, Flutterwave webhook signatures), do the research,
   then implement it properly once. Re-read the relevant plan section before starting
   each item — do not work from memory of it.
10. **Creative latitude, bounded.** You are encouraged to exceed the plan on polish —
    micro-interactions, empty states, copy tone, accessibility — provided the data
    model and behavior stay exactly as specified. Creativity adds; it never substitutes.

## Execution order

Work through `LMS_MASTER_PLAN.md` §8 exactly:

- **P0 Hardening** (items 1–8): enrollment-status policy, enroll idempotency +
  throttling, soft deletes, indexes, N+1 kills, certificate store/verify/QR,
  `ProgressService` extraction, tests for all of it. Ship this before anything new.
- **P1 Monitoring core**: enrollment fast-path columns + backfill, `learning_events`,
  Students tab + per-student drill-down, resume UX, at-risk nightly command.
- **P2 Player & AJAX**: YouTube IFrame API + heartbeat + resume, AJAX completion,
  markdown lessons, free preview, completion rules + sequential progression,
  events/listeners/notifications.
- **P3 Assessment**: full quiz schema + `QuizService` auto-grading (all 9 question
  types, partial credit, frozen shuffle, server timer, autosave), quiz runner UI,
  assignments + grading queue, gradebook, certificate criteria, item analysis.
- **P4 Commerce & community**: Flutterwave course checkout + coupons + refunds,
  announcements, Q&A, timestamped notes, reviews, bulk enroll, drag-drop builder,
  course analytics funnel, nudge emails + instructor digest.
- **P5 Polish & scale**: badges/streaks, enrollment expiry, API v1 parity,
  accessibility pass, event pruning, heartbeat load sanity.

For every new model: migration + model (typed casts, relations) + factory + seeder
entry + policy + tests. Extend the demo seeder so a fresh install has one rich course
(modules, lessons with video + markdown, a quiz with every question type, an
assignment, and two fake students with realistic progress) — the owner must be able to
see every feature working after `php artisan migrate:fresh --seed`.

## When you believe you are done

Run the full checklist in `LMS_MASTER_PLAN.md` §9 line by line and prove each line
with either a test name or a file/route reference in `LMS_BUILD_LOG.md`. Then do a
final end-to-end walkthrough as three people — a dishonest student, an honest student
on a phone, and the instructor — and fix anything that feels less than excellent.
You are finished only when nothing in the plan remains unimplemented and
`composer ci` is green from a fresh checkout.

Begin now with P0, item 1. Read the plan first.
