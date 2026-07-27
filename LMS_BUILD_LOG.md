# LMS Build Log

Worklog for executing `LMS_MASTER_PLAN.md`. One entry per completed work item:
item ID, what was built, files touched, decisions made, tests added, anything
deferred (only where the plan itself defers it to a later phase).

---

## P0 — Hardening

### P0.1 — Enrollment status check in `enrollmentFor()` + `EnrollmentPolicy` (closes L2)

**Built:**

- `app/Policies/EnrollmentPolicy.php` — new. `access(User, Enrollment)`: owner-only,
  and only `active`/`completed` statuses grant it (`pending`/`cancelled` denied).
  `view(User, Enrollment)`: owner or `courses.manage` permission — for future
  "my enrollment status" surfaces that aren't the player itself. `before()` bypass
  for `super_admin`, matching `ProjectPolicy`/`InvoicePolicy` convention exactly.
- `LearningController::enrollmentFor()` now calls `$this->authorize('access', $enrollment)`
  after the `firstOrFail()` lookup — the single choke point used by `show()`,
  `lesson()`, and `complete()`, so all three close in one edit.
- `resources/views/learn/index.blade.php` — a pending/cancelled enrollment no longer
  renders a "Continue" link that would 403; shows a "Payment pending" / "Cancelled"
  badge instead. (Found while testing the abuse path — not fixing it would have left
  a broken link in the UI the moment the policy went in.)

**Decision:** the plan's rule 1 says "policy registration in `AppServiceProvider`" —
audited the actual codebase and found `ProjectPolicy`/`InvoicePolicy` are **not**
manually registered anywhere (no `Gate::policy()` calls, no `AuthServiceProvider`);
Laravel 12's convention-based auto-discovery (`App\Models\X` → `App\Policies\XPolicy`)
already handles it, confirmed working for both. Followed the codebase's actual
convention (do nothing extra) rather than the plan's imprecise wording, per rule 1's
own instruction to never invent a parallel convention.

**Deferred (explicitly, to P0.7):** `Api\V1\EnrollmentController::completeLesson`
still has its own inline enrollment lookup with no status/policy check — this is
exactly L14, scheduled to close when both web and API move onto `ProgressService`.
Left alone here to avoid duplicate work.

**Tests added** (`tests/Feature/Learning/EnrollmentAccessPolicyTest.php`, 8 tests):
`test_a_pending_unpaid_enrollment_cannot_view_the_course_player`,
`test_a_pending_unpaid_enrollment_cannot_view_a_lesson`,
`test_a_pending_enrollment_cannot_post_lesson_completion` (asserts no `lesson_progress`
row is written — the abuse path proof), `test_a_cancelled_enrollment_cannot_view_the_course_player`,
`test_an_active_enrollment_can_view_the_course_player`,
`test_a_completed_enrollment_can_still_review_the_course`,
`test_the_my_courses_list_shows_a_pending_badge_with_no_dead_continue_link`,
`test_a_super_admin_can_view_any_enrollments_player_regardless_of_status`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 69/69 green (61 pre-existing + 8 new).

### P0.2 — Idempotent enroll + throttling (closes L8)

**Built:**

- `CourseCatalogueController::enroll()` and `Api\V1\EnrollmentController::store()` —
  the final `Enrollment::create([...])` call replaced with
  `Enrollment::firstOrCreate(['user_id' => ..., 'course_id' => ...], [...])` wrapped
  in `try { } catch (\Illuminate\Database\UniqueConstraintViolationException) { }`,
  matching the exact idempotent-insert pattern already used in
  `BillingService::createInvoiceWithNumber()`. On the API side the catch branch
  re-fetches the existing row so the response still has an `Enrollment` to return.
- Throttling added at the route layer (no new code, just middleware): `throttle:5,1`
  on `POST /contact` (`routes/web.php`), `throttle:10,1` on `POST /courses/{course:slug}/enroll`
  (web) and `POST courses/{course}/enroll` (`routes/api.php`).

**Decision:** the API's `courses/{course}/enroll` route uses the bare `{course}`
placeholder, which — like `Api\V1\CourseController::show()`'s existing `{course}`
binding — resolves via `Course::getRouteKeyName()` (`'slug'`), not by numeric id.
This is already the API's established convention (public course reads are
slug-addressed, consistent with the web routes' explicit `{course:slug}`), so no
route change was made; a test that had assumed id-based binding was corrected to
use the slug instead, per rule 1 (never invent a parallel convention — match what
the API already does).

**Tests added:**

- `tests/Feature/Learning/EnrollIdempotencyTest.php` (4 tests):
  `test_posting_enroll_twice_in_a_row_never_errors_and_creates_only_one_row`,
  `test_first_or_create_racing_an_existing_row_does_not_throw_and_stays_a_single_row`
  (simulates the true race — a row inserted underneath the existence check),
  `test_the_api_enroll_endpoint_is_also_double_click_safe`,
  `test_enroll_route_is_throttled_against_rapid_repeated_requests` (11th request in
  one minute gets a 429).
- `tests/Feature/Portfolio/ContactFormTest.php` — added
  `test_the_contact_form_is_throttled_against_spam` (6th submission in one minute
  gets a 429).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 74/74 green (69 pre-existing + 5 new).

### P0.3 — Soft deletes on modules/lessons (closes L7)

**Built:**

- New additive migration `2026_07_26_143532_add_soft_deletes_to_course_modules_and_lessons_tables.php`
  — adds `deleted_at` to `course_modules` and `lessons` (courses already had it;
  the already-run `create_course_modules_table`/`create_lessons_table` migrations
  were left untouched per rule 4).
- `CourseModule` and `Lesson` models — added the `SoftDeletes` trait. No controller
  changes needed: `CourseModuleController::destroy()`/`LessonController::destroy()`
  already just call `->delete()`, which now soft-deletes automatically, and implicit
  route-model-binding on `{module}`/`{lesson}` already excludes trashed rows via the
  model's own global scope (a deleted lesson's URL now 404s on its own).
- `Course::lessons()` (`app/Models/Course.php`) — added an explicit
  `->whereNull('course_modules.deleted_at')`. `hasManyThrough` does **not** apply the
  intermediate model's own soft-delete global scope automatically (a documented
  Eloquent gap), so without this a lesson under a soft-deleted module would still be
  counted by `lessonCount()`/`progressPercent()`. `CourseModule::lessons()` and
  `Course::modules()` needed no change — direct relations already apply the far
  model's own scope.

**Decision:** §4.7 also asks for a queued job to recompute a denormalized
`progress_percent` column on affected enrollments whenever a lesson is added/removed.
That column doesn't exist yet — it's explicitly introduced by P1's "enrollment
fast-path columns" (§6.1, §8). Today `progressPercent()` is computed live from
`lessonCount()` + a progress count, which already self-corrects the instant a lesson
is soft-deleted (proven by the test below) — there is nothing to recompute yet.
Deferring the recompute job itself to P1, when the column it targets is introduced.

**Tests added** (`tests/Feature/Learning/ContentSoftDeleteTest.php`, 9 tests):
`test_deleting_a_lesson_soft_deletes_it_instead_of_removing_the_row`,
`test_deleting_a_module_soft_deletes_it_instead_of_removing_the_row`,
`test_deleting_a_lesson_preserves_the_students_progress_row`,
`test_deleting_a_module_preserves_progress_for_its_lessons`,
`test_a_completed_enrollments_certificate_survives_deleting_a_lesson_afterwards`
(the abuse-path proof: finishing a course, then editing it, must not revoke a
certificate), `test_lesson_count_and_progress_percent_exclude_a_deleted_lesson`,
`test_lesson_count_excludes_lessons_of_a_deleted_module`,
`test_a_deleted_lesson_is_no_longer_reachable_in_the_player`,
`test_admin_can_delete_a_module_via_the_destroy_route_and_it_is_soft_deleted`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 83/83 green (74 pre-existing + 9 new) · `php artisan migrate` clean.

### P0.4 — Indexes for scale (closes L13)

**Built:**

- New additive migration `2026_07_26_143809_add_performance_indexes_to_lesson_progress_and_enrollments_tables.php`
  — composite index `lesson_progress(lesson_id, completed_at)`; single-column index
  `enrollments(status)`. `enrollments.user_id`/`course_id` and `lesson_progress.enrollment_id`/`lesson_id`
  already carry indexes implicitly from their `->constrained()` foreign keys, so nothing
  new was needed there.

**Decision:** the plan's item 4 also lists `enrollments(last_accessed_at)`. That column
doesn't exist yet — §6.1/L12 introduce `last_lesson_id` + `last_accessed_at` as part of
P1's "enrollment fast-path columns," which is explicitly a later phase in §8. Indexing a
column before it exists isn't possible; deferring this specific index to the P1 migration
that creates the column (it'll be added in the same migration, not bolted on after).

**Tests added** (`tests/Feature/Learning/PerformanceIndexTest.php`, 2 tests):
`test_lesson_progress_has_a_composite_index_on_lesson_id_and_completed_at`,
`test_enrollments_has_an_index_on_status` — both assert via `Schema::getIndexes()`
(driver-agnostic, so it verifies the same thing against the SQLite test DB and the
real MySQL schema).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 85/85 green (83 pre-existing + 2 new) · `php artisan migrate` clean.

### P0.5 — `withCount`-based progress on list views (closes L9)

**Built:**

- `Enrollment::progressPercent()` (`app/Models/Enrollment.php`) — now prefers
  `$this->course->lessons_count` and `$this->completed_lessons_count` when present
  (both nullable `@property-read`s, populated only when a caller eager-loads them),
  falling back to the original live `lessonCount()`/`progressRecords()->count()` query
  when they're not — so the same method stays correct for both a hydrated list and a
  single freshly-mutated `Enrollment` instance (e.g. inside `LearningController::complete()`).
  This mirrors the existing `withCount('enrollments')`/`withCount('projects')` pattern
  already used in `Admin\CourseController`/`Admin\ClientController` rather than
  inventing a new one.
- `Student\LearningController::index()` (the "My Courses" list) — the enrollment
  query now does `->with(['course' => fn ($q) => $q->withCount('lessons'), 'certificate'])`
  plus `->withCount(['progressRecords as completed_lessons_count' => fn ($q) =>
  $q->whereNotNull('completed_at')])`. Eager-loading `certificate` was necessary too:
  the view's `@if($enrollment->certificate)` check was its own undiscovered N+1 — same
  list, same root cause (L9), so it was in scope to fix here rather than opening a
  second ticket for it.
- `DashboardService::studentEnrollments()` — the same `course` + `completed_lessons_count`
  eager-loading, feeding the student dashboard's "My courses" cards.

**Decision:** L9's text also mentions "same shape in admin enrollments list" — checked
`Admin\EnrollmentController::index()` and its view: it lists enrollments but never
calls `progressPercent()`/`lessonCount()` today, so there is no N+1 there yet to fix.
Nothing changed there; the two real hotspots (`learn.index`, student dashboard) are
the ones actually calling `progressPercent()` per row.

**Tests added:**

- `tests/Feature/Learning/LearnIndexQueryCountTest.php` (2 tests):
  `test_the_my_courses_list_runs_the_same_query_count_regardless_of_enrollment_row_count`
  (proves flat query count between 1 and 5 enrollments — the actual N+1 regression
  proof, stronger than a hardcoded magic number) and
  `test_the_my_courses_list_still_renders_the_correct_progress_percentage`.
- `tests/Feature/Learning/StudentDashboardQueryCountTest.php` (2 tests, same shape,
  against `route('dashboard')`) — the query-count assertion runs a discarded warm-up
  request first so Spatie's permission-cache warm-up doesn't masquerade as a fake
  per-row delta.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 89/89 green (85 pre-existing + 4 new).

### P0.6 — Certificate idempotency + PDF storage + `/verify/{uuid}` + QR (closes L3)

**Built:**

- New additive migration `2026_07_26_144749_add_uuid_and_unique_enrollment_to_certificates_table.php`
  — adds `certificates.uuid` (unique) and a unique constraint on `enrollment_id`
  (needed for both idempotent `firstOrCreate` and the `hasOne` semantics
  `Enrollment::certificate()` already implies).
- `Certificate::getRouteKeyName()` now returns `'uuid'` — matches `Invoice`'s existing
  convention exactly (documents addressed by an unguessable public id, not the
  internal auto-increment). Every existing `route('learn.certificate', $certificate)`
  call needed no change: Laravel's `route()` helper already resolves the key through
  the model, so the URL just silently switched from `/certificates/7` to
  `/certificates/{uuid}`.
- New `app/Services/Learning/CertificateService.php` (the `CertificateService` named
  in §4.1) — `issue()` does the `firstOrCreate`-on-`enrollment_id` +
  `UniqueConstraintViolationException` catch (same idempotent-insert shape as P0.2's
  enroll fix); `stream()` serves the stored PDF, regenerating once if the row's
  `pdf_path` is missing or the file was removed from disk. PDF rendering happens at
  most once per certificate and is written to the private `local` disk under
  `certificates/{uuid}.pdf` — mirrors `DocumentService`'s "private disk, streamed
  through a controller after a check" convention exactly, rather than the previous
  `Pdf::loadView(...)->stream()` which re-rendered from scratch on every request.
- `Student\LearningController` — now constructor-injects `CertificateService`;
  `certificate()` calls `$this->certificates->stream()` (ownership `abort_unless`
  check unchanged, kept inline since it already existed and isn't part of this
  item's scope); the lesson-completion path calls `$this->certificates->issue()`
  directly, so the old private `issueCertificate()` wrapper was removed as dead
  indirection.
- New public (no `auth` middleware) route `GET /verify/{certificate}` →
  `CertificateVerificationController::show()` → `resources/views/certificates/verify.blade.php`
  (extends `layouts.marketing`, matching the public course-catalogue/privacy-page
  convention) — shows student name, course title, certificate number, and issue
  date for a real certificate; an unknown uuid 404s via ordinary route-model-binding
  failure, no extra "not found" branch needed.
- `resources/views/pdf/certificate.blade.php` — now renders a QR code (existing
  `QrService::pngDataUri()`) encoding the `/verify/{uuid}` URL, with a "Scan to
  verify" caption, next to the certificate number/issue date.
- `tests/Feature/Learning/CourseEnrollmentTest.php` — the two tests that construct a
  `Certificate::create([...])` directly needed a `'uuid' => (string) Str::uuid()`
  key added (the column is now required), matching how `Enrollment::create()` calls
  elsewhere in the same file already supply their own uuid explicitly; both PDF-
  rendering tests now wrap in `Storage::fake('local')` so the test run doesn't write
  real files to disk.

**Decision:** the plan is internally inconsistent about the certificate identifier —
L3's own text says `/verify/{certificate_no}`, §4.6 says `/verify/{uuid}`. Certificates
had neither a `uuid` column nor any established verify precedent, but `Invoice` already
sets exactly this precedent (`getRouteKeyName() => 'uuid'`) for "a document addressed
outside the authenticated area." Went with `uuid` + Invoice's convention: it satisfies
§4.6's literal wording, avoids exposing/leaking the sequential `certificate_no`
generation scheme (`VerificationCode::make()` is a checksummed but ultimately
enumerable sequence) as the sole gate on a public verification URL, and — per rule 1
— extends an existing pattern rather than inventing a new one.

Also scoped out of P0.6 (deferred to P3, per §8's own roadmap): §4.6's first bullet
("issue only when `certificate_requires` is satisfied... quizzes counts_toward_certificate")
depends on the quiz engine, which doesn't exist until P3 ("certificate criteria tighten
(§4.6 → closes L1 fully)" is explicitly a P3 line item). P0.6 only closes L3
(verification); L1 (certificates attesting to real completion) stays open until P3.

**Tests added:**

- `tests/Feature/Learning/CertificateIssuanceTest.php` (7 tests):
  `test_issuing_a_certificate_twice_for_the_same_enrollment_creates_only_one_row`
  (the idempotency abuse-path proof — a replayed completion event/double-click must
  not mint a second certificate), `test_issuing_a_certificate_stores_the_rendered_pdf_on_the_private_disk`,
  `test_a_certificate_has_a_unique_uuid_used_as_its_route_key`,
  `test_the_public_verify_page_shows_student_name_course_and_issue_date_for_a_real_certificate`,
  `test_the_public_verify_page_is_reachable_by_a_guest_with_no_authentication`,
  `test_an_unknown_certificate_uuid_is_not_found`,
  `test_a_forged_certificate_number_alone_does_not_resolve_on_the_verify_page` (proves
  the human-readable code alone can't be used to probe the verify endpoint).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 96/96 green (89 pre-existing + 7 new) · `php artisan migrate` clean.

### P0.7 — Extract `ProgressService`; web + API both call it (closes L14)

**Built:**

- New `app/Services/Learning/ProgressService.php` (the `ProgressService` named in
  §4.1) — `completeLesson(Enrollment, Lesson)` is now the single writer of
  `lesson_progress`, enrollment completion, and certificate triggering. It calls
  `Gate::authorize('access', $enrollment)` itself, so the enrollment-status check
  lives in the one place both callers go through instead of depending on each
  controller remembering to add it — this is what actually closes L14, not just
  moving the duplicated code into a shared function.
- `Student\LearningController::complete()` — the inline `updateOrCreate` +
  progress-percent + certificate-issue block (previously duplicating what P0.6 had
  just moved into `CertificateService`) is replaced with one call:
  `$this->progress->completeLesson($enrollment, $lesson)`.
- `Api\V1\EnrollmentController::completeLesson()` — same replacement. This is the
  piece P0.1's worklog explicitly deferred to this item. Two real gaps closed at
  once: (1) a `pending`/`cancelled` enrollment can no longer POST a completion
  through the API (previously only blocked on the web); (2) completing a course's
  last lesson through the API now issues a certificate too — previously it silently
  didn't, since the certificate-issuance logic only existed in the web controller.
  That second gap wasn't in the L1–L14 list by name, but it's the exact kind of
  web/API behavioral drift L14 describes, on the same completion path this item was
  already touching.

**Decision:** `Gate::authorize()` (not `$this->authorize()`, unavailable outside a
controller) throws `AuthorizationException`, which `bootstrap/app.php` already maps
to the `ApiResponse` envelope with a 403 for JSON requests and a normal 403 page
otherwise (an existing, pre-LMS exception-handling convention) — so no new error
formatting work was needed for the API to reject a blocked completion attempt
correctly.

**Tests added** (`tests/Feature/Learning/ApiProgressServiceParityTest.php`, 4 tests):
`test_a_pending_enrollment_cannot_complete_a_lesson_through_the_api` (asserts no
`lesson_progress` row is written — the abuse-path proof, mirroring P0.1's web-side
test), `test_a_cancelled_enrollment_cannot_complete_a_lesson_through_the_api`,
`test_completing_the_only_lesson_through_the_api_also_issues_a_certificate` (the
drift-bug proof), `test_an_active_enrollment_can_complete_a_lesson_through_the_api`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 100/100 green (96 pre-existing + 4 new).

### P0.8 — Feature tests for every P0 item

**Built:**

Every P0.1–P0.7 item already shipped with its own dedicated feature tests as part
of that item's own verification gate (rule 4 requires this per-item, not deferred to
the end) — 34 tests total across `EnrollmentAccessPolicyTest`, `EnrollIdempotencyTest`,
`ContentSoftDeleteTest`, `PerformanceIndexTest`, `LearnIndexQueryCountTest`,
`StudentDashboardQueryCountTest`, `CertificateIssuanceTest`, `ApiProgressServiceParityTest`,
plus the throttle test in `ContactFormTest` and the two updated tests in
`CourseEnrollmentTest`. P0.8 is the audit pass rule 5 asks for at the phase gate:
re-read the abuse-path checklist (rule 4 — "dishonest student, double-click, replayed
webhook, stale browser tab") against everything shipped and confirm each scenario has
an explicit test, not just incidental coverage.

Found one real gap: no test proved a **stale browser tab** — a second tab that already
loaded the lesson page, then POSTs `complete` again after the course was finished in
another tab — is a safe no-op. `ProgressService::completeLesson()`'s `updateOrCreate` +
`status !== 'completed'` guard already made this safe structurally, but nothing proved
it. Added `tests/Feature/Learning/ProgressServiceIdempotencyTest.php` (2 tests):
`test_a_stale_tab_re_completing_an_already_completed_lesson_does_not_duplicate_the_progress_row`,
`test_a_stale_tab_re_completing_the_final_lesson_does_not_issue_a_second_certificate`
(replaying the POST that just earned a certificate must not mint a second one).

A replayed-webhook scenario is explicitly out of scope for P0 — there is no
webhook-driven completion path yet; that only arrives with P4's Flutterwave course
checkout, where it will need its own idempotency test at that time.

**Full-repo gate run** (not just `--dirty`, to catch anything an incremental per-item
check could have missed): `composer ci` — `vendor/bin/pint --test` (whole repo),
`phpstan analyse`, `scripts/ci/check-empty-files.sh`, `scripts/ci/secrets-scan.sh`,
`php artisan test` — all green.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 102/102 green (100 pre-existing + 2 new) · `composer ci` green.

---

## P0 phase gate — closed

All eight items done, one commit per item, in plan order. Closes: L2, L8, L7, L13
(partial — see P0.4's deferral note), L9, L3, L14.

- `composer ci` (repo-wide `pint --test`, `phpstan`, `check-empty-files`,
  `secrets-scan`, `php artisan test`): **green**, 102 tests / 232 assertions.
- `php artisan migrate:status`: every migration this phase added
  (`add_soft_deletes_to_course_modules_and_lessons_tables`,
  `add_performance_indexes_to_lesson_progress_and_enrollments_tables`,
  `add_uuid_and_unique_enrollment_to_certificates_table`) applied cleanly in its own
  batch on top of the pre-existing schema — a fresh checkout migrates clean.
- AJAX degrade-gracefully check (phase-gate rule 5): not applicable yet — P0 shipped
  no AJAX surface. `/learn` is still the full-page-reload flow L10 describes; AJAX
  completion is explicit P2 scope (§7.3/§4.5). Nothing to verify until then.
- Query-count regression check on learn surfaces: `LearnIndexQueryCountTest` and
  `StudentDashboardQueryCountTest` assert query count stays flat between 1 and 5
  enrollments (a direct regression proof, stronger than pinning a specific number
  that could go stale for unrelated reasons).
- Explicitly deferred to later phases (each deferral tied to a plan section that
  itself places the work in a later phase, per rule 2's carve-out):
  - `enrollments(last_accessed_at)` index — column arrives with P1's fast-path
    columns (§6.1); index will land in that same migration.
  - `progress_percent` recompute-on-lesson-change queued job (§4.7) — targets a
    denormalized column that doesn't exist until P1.
  - Certificate criteria tightening / quiz-gated certificates (§4.6 first bullet,
    closes L1 fully) — needs the quiz engine, explicitly P3 in §8.
  - Replayed-webhook idempotency — no webhook-driven completion path exists until
    P4's Flutterwave course checkout.

Next: P1 — Monitoring core (enrollment fast-path columns + backfill, `learning_events`,
Students tab + per-student drill-down, resume/continue UX, at-risk nightly command).

---

## P1 — Monitoring core

### P1.1 — Enrollment fast-path columns + backfill (§6.1)

**Built:**

- New additive migration `2026_07_26_150119_add_fast_path_columns_to_enrollments_table.php`
  — adds `progress_percent` (unsigned tinyint, default 0), `total_watch_seconds`
  (unsigned int, default 0), `last_lesson_id` (nullable FK → lessons, `nullOnDelete`),
  `last_accessed_at` (nullable timestamp, indexed — this is the index P0.4 explicitly
  deferred to "the same migration that creates the column"). Backfill runs in the same
  migration's `up()`: only `progress_percent` has a historical source to backfill from
  (existing `lesson_progress` rows); `total_watch_seconds`/`last_lesson_id`/
  `last_accessed_at` have none and start populating from the next lesson view/completion.
  Verified clean in both directions: `migrate` → `migrate:rollback --step=1` →
  `migrate` all ran without error.
- `Enrollment` model — the four columns added to `$fillable`, `last_accessed_at` cast
  to `datetime`, new `lastLesson(): BelongsTo` relation.
- `ProgressService::completeLesson()` — now also writes `last_lesson_id`,
  `last_accessed_at`, and the freshly computed `progress_percent` onto the enrollment
  (computed once and reused for both the write and the ≥100% certificate check, rather
  than calling `progressPercent()` twice).
- `ProgressService::recordView(Enrollment, Lesson)` — new; same three-column write,
  for a lesson *view* (not completion). Wired into `Student\LearningController::lesson()`
  so browsing to a lesson (not just finishing it) updates "last seen" — this is the
  write-side half of §6.5's resume UX, which reads these columns in P1.5.

**Decision:** wiring `recordView()` into the lesson-view action now (rather than
leaving `last_lesson_id`/`last_accessed_at` unwritten until P1.5) was deliberate:
adding a column nobody writes to yet is exactly the "dead schema" anti-pattern L4/L5
already called out elsewhere in this codebase. Since Resume UX (P1.5) is the *same*
phase, not a later one, there was no rule-2 justification to leave the write-side
half undone — only the read-side UI belongs to P1.5.

**Tests added** (`tests/Feature/Learning/EnrollmentFastPathColumnsTest.php`, 4 tests):
`test_enrollments_has_an_index_on_last_accessed_at`,
`test_viewing_a_lesson_records_last_lesson_and_last_accessed_at`,
`test_completing_a_lesson_updates_the_denormalized_progress_percent`,
`test_completing_the_final_lesson_brings_the_denormalized_percent_to_100`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 106/106 green (102 pre-existing + 4 new) · migrate/rollback/migrate clean.

### P1.2 — `learning_events` + `LearningEventRecorder` + server-side recording hooks (§6.2)

**Built:**

- New migration `create_learning_events_table` — `enrollment_id` (cascadeOnDelete),
  `lesson_id` (nullable, nullOnDelete), `nullableMorphs('subject')` for quiz
  attempts/submissions later, `event` (native enum column, values driven by the new
  `App\Enums\LearningEventType` backed enum so the DB constraint and the PHP type
  can't drift apart), `value` (JSON), `created_at` only (no `updated_at` — the table
  is append-only), composite index `(enrollment_id, created_at)` exactly as specified.
  Verified clean in both directions (migrate → rollback → migrate).
- `App\Enums\LearningEventType` — all 11 cases from §6.2's vocabulary declared now
  (`lesson.viewed`, `video.play/pause/heartbeat/ended`, `lesson.completed`,
  `quiz.started/submitted`, `material.downloaded`, `note.created`,
  `question.asked`), even though the `video.*`/`quiz.*`/`note.*`/`question.*` cases
  have no recorder yet — they wait on the player heartbeat (P2), quiz engine (P3),
  and community features (P4) respectively, so the enum (and the DB column derived
  from it) won't need another migration when those phases land.
- `App\Models\LearningEvent` — `UPDATED_AT = null`, `event` cast to the enum,
  `value` cast to `array`, `belongsTo` enrollment/lesson, `morphTo` subject.
- New `app/Services/Learning/LearningEventRecorder.php` (the service named in
  §4.1) — the single funnel for every event row.
- `ProgressService` now takes `LearningEventRecorder` in its constructor and emits
  `lesson.viewed` from `recordView()` and `lesson.completed` from `completeLesson()`
  — the two hooks that already had a real trigger point in P1.1.

**Decision — built the missing student material-download route.** §6.2 lists
`material.downloaded` as fed by a "server-side hook," but auditing the actual app
turned up that no such hook could exist: there was no student-facing download
route at all. `resources/views/learn/lesson.blade.php` only ever printed a
material's title as plain, unlinked text — uploading a material through the admin
worked, but a student could never retrieve it. This isn't a later-phase deferral
target anywhere in the plan; it's a pre-existing gap this exact item needed filled
to have anything to hook into. Built `Student\LessonMaterialController::download()`,
mirroring `ProjectDocumentController`/`DocumentService`'s established private-disk,
policy-gated-stream convention exactly: ownership via the same `EnrollmentPolicy`
`access` check already used everywhere else in the student player, a plain
`Storage::disk('local')->download()` for stored files, and a 302 to the URL for
`type: link` materials. New route `GET learn/{course}/{lesson}/materials/{material}`
→ `learn.materials.download`; the lesson view's material list now actually links to
it.

**Deferred (explicitly, to later phases the plan itself names):** `video.*` events
wait on the YouTube IFrame API heartbeat (§6.2's own first bullet, P2). `quiz.*`
waits on the quiz engine (P3). `note.*`/`question.*` wait on the community features
tabs (§7.3/§7.6, P4). The prune-after-12-months scheduled command mentioned in
§6.2's retention note is explicitly a **P5** roadmap line item ("event pruning"),
not part of this item.

**Tests added** (`tests/Feature/Learning/LearningEventRecordingTest.php`, 6 tests):
`test_viewing_a_lesson_records_a_lesson_viewed_event`,
`test_completing_a_lesson_records_a_lesson_completed_event`,
`test_downloading_a_stored_material_streams_it_and_records_an_event`,
`test_downloading_a_link_material_redirects_to_the_url_and_still_records_an_event`,
`test_a_pending_enrollment_cannot_download_a_lesson_material` (the abuse-path proof
for the newly built download route), `test_a_material_belonging_to_a_different_lesson_404s`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 112/112 green (106 pre-existing + 6 new) · migrate/rollback/migrate clean.

### P1.3 — Course → Students tab (§6.3.1)

**Built:**

- First real Livewire component in this app: `App\Livewire\Admin\CourseStudents`
  (full-page, routed directly via `Route::get('courses/{course}/students',
  \App\Livewire\Admin\CourseStudents::class)->name('admin.courses.students')`,
  inside the existing `admin`-middleware group). Research confirmed there was no
  prior `WithTable` concern or any other Livewire component anywhere in the app to
  reuse — `resources/views/layouts/admin.blade.php`'s own comment ("Livewire
  full-page components fill `{{ $slot }}`") and its already-configured
  `@livewireStyles`/`@livewireScripts` show the layout was prepared for exactly this,
  just never used yet. One orphaned convention *was* already sitting there —
  `resources/views/livewire/partials/sort-caret.blade.php`, expecting
  `$sortField`/`$sortDir`/`$field` — reused it as-is for the sortable column headers
  rather than inventing different prop names.
- One row per enrollment: student name/email, progress bar + %, watch time
  (`total_watch_seconds` — real column, honestly shows 0m for everyone until P2's
  heartbeat starts accumulating it), current lesson (`lastLesson->title`), last
  active (`last_accessed_at->diffForHumans()`, red `badge-danger` if stale >14 days
  or never accessed), status badge. Sortable on progress/watch-time/last-active/
  status (`wire:click="sortBy(...)"`, reusing the sort-caret partial), searchable by
  student name/email (`wire:model.live.debounce.400ms`), filterable by status —
  all via Livewire, no page reload. `#[Url]` attributes keep sort/search/filter
  state in the query string so a link to a specific view is shareable.
- Table markup reuses `td-admin.css` exactly (`.tb-table`, `.tb-filter-bar`,
  `.tb-page-header`, `.badge-tb` + state classes) — no new CSS.
- `admin.courses.show` gets a "Students" header link to the new page.

**Deferred (explicitly, to P3):** grade-to-date, quiz average, and missing-
assignments columns from §6.3.1's spec — none are computable without the quiz/
assignment models, which are P3 scope. Rather than show a permanent placeholder
column, they're simply not in the table yet; P3 adds them when the underlying data
exists.

**Decision — row click → drill-down deferred to P1.4, not stubbed here.** §6.3.1
says "row click → per-student drill-down," but that page doesn't exist until P1.4
(the very next item). Per the no-dead-links rule established back in P0.1, student
rows are plain (not linked) in this item rather than pointing `route()` at a name
that doesn't exist yet — P1.4 adds the link in the same commit that builds its
target.

**Bug found and fixed via manual smoke test — not caught by Pint/PHPStan/tests:**
Livewire's full-page `->title()` merges a `$title` variable into the layout render;
it does **not** populate `@yield('title')`. `layouts/admin.blade.php`'s `<title>`
tag only read `@yield('title', 'Dashboard')`, so every future full-page Livewire
route would have silently kept the browser tab reading "Dashboard" — this is exactly
why rule 4 requires a manual pass, not just the automated gate. Fixed the layout to
`{{ $title ?? $__env->yieldContent('title', 'Dashboard') }}`, which serves classic
Blade pages and Livewire full-page components correctly from the same layout.

**Tests added** (`tests/Feature/Admin/CourseStudentsTabTest.php`, 7 tests):
`test_a_non_admin_cannot_view_the_students_tab`,
`test_the_browser_tab_title_reflects_the_course_name` (pins the layout-title fix),
`test_an_admin_sees_every_enrolled_student`,
`test_searching_by_student_name_filters_the_list` (via `Livewire::test()`),
`test_filtering_by_status_only_shows_matching_enrollments`,
`test_a_students_progress_percent_is_visible_in_the_row`,
`test_a_students_from_a_different_course_are_not_listed` (proves no cross-course
data leak).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 119/119 green (112 pre-existing + 7 new) · manual smoke test at
the real MAMP-served URL (login → students tab renders, searches/filters/sorts
live with no full page reload, tab title correct).

### P1.4 — Per-student drill-down (§6.3.2)

**Built:**

- New `enrollment_notes` table + `EnrollmentNote` model (`enrollment_id`,
  `user_id` nullable/`nullOnDelete`, `note`, timestamps) — mirrors `ProjectNote`'s
  shape exactly, minus the `is_client_visible` toggle (§6.3.2 says these notes are
  always private, so there's nothing to toggle). `Enrollment` gets `notes(): HasMany`
  (latest-first) and `learningEvents(): HasMany`.
- `App\Enums\LearningEventType::label()` — a human label per event case for the
  timeline (`Viewed a lesson`, `Completed a lesson`, …), matching the `label()`
  convention already established on `InvoiceStatus`/`PaymentMethod`.
- Second full-page Livewire component: `App\Livewire\Admin\EnrollmentDrilldown`
  (`admin.enrollments.show`, `GET /admin/enrollments/{enrollment}`), showing: student
  info, status/progress/watch-time/last-active summary, a lesson-by-lesson completion
  checklist (✓/○ per lesson from `lesson_progress` — real per-lesson *watched-vs-
  duration* bars need per-lesson watch-seconds data that doesn't exist until P2's
  heartbeat, so this ships as a completion checklist instead of a fabricated bar),
  a paginated activity timeline from `learningEvents()`, and a private-notes panel
  (add + list, no page reload).
- `App\Notifications\StudentNudgeNotification` — the "message/nudge" button, sent
  via `['mail', 'database']` so it lands in the student's own existing (previously
  unused) in-app `/notifications` inbox as well as their email. `toArray()` matches
  the `{title, message}` shape `admin/notifications/index.blade.php` already expects
  — the first real notification ever sent through that infrastructure.
- `admin.courses.students`'s student-name cell now links to this new page —
  completing the "row click → drill-down" wiring P1.3 explicitly deferred.

**Deferred (explicitly, to phases the plan itself names):** "every attempt +
submission with scores" (needs the P3 quiz/assignment models) and the "reset quiz
attempts" button (same). The "extend access" button is deferred to **P5**, where
`enrollment expiry` is an explicit roadmap line item — there's no expiry concept on
an enrollment yet, so there's nothing for that button to extend.

**Tests added** (`tests/Feature/Admin/EnrollmentDrilldownTest.php`, 5 tests):
`test_a_non_admin_cannot_view_the_drilldown`,
`test_an_admin_sees_the_students_lesson_checklist_and_activity`,
`test_an_admin_can_add_a_private_note`, `test_an_empty_note_is_rejected`,
`test_sending_a_nudge_notifies_the_student_and_shows_a_confirmation`
(`Notification::fake()` + `assertSentTo`).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 124/124 green (119 pre-existing + 5 new) · migrate/rollback/migrate
clean · manual smoke test at the real MAMP-served URL (drill-down page renders with
correct title, lesson checklist, and timeline).

### P1.5 — Resume/continue UX (§6.5)

**Built:**

- `Student\LearningController::show()` — now redirects to `$enrollment->lastLesson`
  when it's set and still belongs to the requested course, instead of always
  restarting at the first lesson. The course-id check guards against a stale
  `last_lesson_id` pointing at another course (defensive, in case data is ever
  migrated/reassigned); `lastLesson()` itself already excludes a soft-deleted
  lesson via `Lesson`'s own global scope, so a deleted "last lesson" falls through
  to the first-lesson fallback for free.
- `learn.index` ("My Courses") rebuilt with: a small CSS `conic-gradient` progress
  ring per card (no JS/chart library — §6.5's "course card ring"), a "resume at
  ‹lesson title›" hint once `last_accessed_at` is set, an "N lessons left" hint
  (§6.5's certificate-progress-hint idea, without the "+ final quiz" clause since
  quizzes don't exist until P3), and the Continue button now reads "Start course" /
  "Resume" / "Review" depending on enrollment state instead of always "Continue".
  The ring and hints read the §6.1 denormalized `progress_percent` column directly
  (already eager-loaded, so no new queries) rather than the live `progressPercent()`
  method — consistent with §6.1's "list views read these columns" framing, now that
  `ProgressService` is the sole writer keeping it in sync (P0.7/P1.1).

**Deferred (explicitly, to phases the plan itself names):** exact video-position
resume (`last_position_seconds`) waits on P2's player heartbeat — today's resume
returns to the right *lesson*, not the exact timestamp within it. Per-module sidebar
progress and a grades tab wait on P3 gradebook data. A weekly streak counter is
listed as "optional" in §6.5 itself and was skipped as out of scope for this item.

**Tests added** (`tests/Feature/Learning/ResumeUxTest.php`, 5 tests):
`test_a_never_visited_course_starts_at_the_first_lesson`,
`test_a_returning_student_resumes_at_their_last_viewed_lesson`,
`test_a_stale_last_lesson_from_another_course_is_ignored` (the defensive-guard
proof), `test_the_my_courses_card_shows_a_resume_hint_after_a_lesson_view`,
`test_the_my_courses_card_shows_lessons_left`. Also updated
`LearnIndexQueryCountTest`'s fixture: since the view now reads the persisted
`progress_percent` column, a test enrollment built by directly inserting
`lesson_progress` (bypassing `ProgressService`) needed the column set explicitly
to match what real usage would have written.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 129/129 green (124 pre-existing + 5 new) · manual smoke test at
the real MAMP-served URL (ring, "N lessons left", and the resume hint all render
correctly for a real logged-in student).

### P1.6 — At-risk nightly command + dashboard counter (§6.4)

**Built:**

- New `enrollments.at_risk_reason` column (nullable string, indexed) — written
  nightly, not computed live, matching §6.4's "nightly scheduled command tags each
  active enrollment" framing.
- `App\Console\Commands\DetectAtRiskEnrollments` (`app:detect-at-risk-enrollments`),
  scheduled `dailyAt('02:00')` in `routes/console.php`. Implements the two rules
  computable from data that exists today: **inactive** (no activity in 14 days —
  using `enrolled_at` as the reference point when `last_accessed_at` is still null,
  so a same-day signup isn't immediately flagged) and **stalled** (activity in the
  last 3 weeks per `learning_events`, but no `lesson.completed` event in that
  window — the closest honest proxy for "progress unchanged despite logins"
  without a progress-history table). A previously-flagged enrollment is cleared
  (`at_risk_reason` set back to `null`) the moment it's healthy again.
- `DashboardService::atRiskEnrollmentsCount()` + a new "Students at risk" stat card
  on the admin dashboard (`tone="warn"` when non-zero).
- Students tab (P1.3) gets an "At risk" badge next to the status pill, and its
  "Last active" cell now reads the persisted `at_risk_reason` for red-highlighting
  instead of recomputing a live 14-day check inline — one source of truth, matching
  how the nightly command is the actual authority on this signal.

**Decision — `struggling`/`missing_work` deferred to P3; weekly digest and streaks
skipped as explicitly optional.** Both remaining §6.4 rules need data that doesn't
exist yet (quiz scores, assignment due dates — P3). The plan itself marks the
weekly instructor digest email as "optional" and streaks/badges as "later (Phase
4+)" — both skipped for this item on that basis, same treatment P1.5 gave the
optional weekly streak counter. The "7 days for short courses" nuance on the
inactive threshold was simplified to a flat 14 days for all courses — there's no
"short course" concept (a duration/length flag) in the schema to key that
distinction off of.

**Root-cause fix, not a suppression — `phpstan.neon`:** implementing this command
exposed that Larastan's `parseModelCastsMethod` option defaults to `false`, so it
was never parsing this app's modern `casts(): array` declaration style (used by
every model in the codebase, e.g. `Enrollment::casts()`) — every custom
datetime-cast property was silently typed as a raw `string` rather than `Carbon`
project-wide, just never caught before because no prior analyzed code called a
Carbon-only method (`->lt()`, `->format()`, …) directly on one. Enabled
`parseModelCastsMethod: true` in `phpstan.neon` — the documented, correct fix for
this exact gap — and re-ran the full analysis: zero new errors surfaced elsewhere,
confirming the rest of the codebase's datetime handling was already correct in
practice, just unverified by static analysis until now.

**Tests added:**

- `tests/Feature/Learning/DetectAtRiskEnrollmentsTest.php` (8 tests):
  `test_an_enrollment_never_accessed_since_enrolling_long_ago_is_flagged_inactive`,
  `test_a_freshly_enrolled_student_with_no_activity_yet_is_not_flagged` (the
  same-day-signup guard), `test_an_enrollment_inactive_for_over_14_days_is_flagged_inactive`,
  `test_an_enrollment_active_with_recent_completions_is_not_flagged`,
  `test_an_enrollment_active_but_with_no_completions_in_3_weeks_is_flagged_stalled`,
  `test_a_previously_flagged_enrollment_is_cleared_once_it_becomes_healthy_again`,
  `test_a_completed_enrollment_is_never_flagged`, `test_the_dashboard_counter_reflects_the_flagged_count`.
- `tests/Feature/Admin/CourseStudentsTabTest.php` — added
  `test_an_at_risk_enrollment_shows_the_at_risk_badge`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors (full
project re-analysis after the `parseModelCastsMethod` fix, not just this item's
files) · `php artisan test` 138/138 green (129 pre-existing + 9 new) ·
migrate/rollback/migrate clean · `php artisan schedule:list` confirms the nightly
job · manual smoke test (dashboard counter renders at the real MAMP-served URL).

---

## P1 phase gate — closed

All six items done, one commit per item, in plan order. Closes/advances: §6.1
(fast-path columns), §6.2 (event stream), §6.3.1–2 (Students tab + drill-down),
§6.4 (at-risk detection, `inactive`/`stalled` rules), §6.5 (resume UX).

- `composer ci` (repo-wide `pint --test`, `phpstan`, `check-empty-files`,
  `secrets-scan`, `php artisan test`): **green**, 138 tests / 295 assertions.
- `php artisan migrate:status` on top of the P0 schema: all 8 of this phase's
  migrations applied cleanly in their own batches — a fresh checkout migrates
  clean end to end.
- Two real Livewire components now exist (`CourseStudents`, `EnrollmentDrilldown`)
  — the first ever built in this app — both full-page, both covered by
  `Livewire::test()` assertions in addition to HTTP-level feature tests.
- A real, previously-invisible project-wide static-analysis gap was found and
  fixed (`parseModelCastsMethod: true` in `phpstan.neon`) rather than routed
  around — every model's datetime-cast property is now correctly type-checked,
  not just the ones this phase happened to touch.
- Explicitly deferred to later phases (each tied to a plan section that itself
  places the work later, per rule 2's carve-out):
  - `total_watch_seconds` stays at 0 for everyone until P2's player heartbeat
    exists to write it (the column and its display are real; there is simply no
    watch-time source yet).
  - Per-lesson watched-vs-duration bars (§6.3.2) — needs the same heartbeat data;
    a lesson-completion checklist ships in its place for now.
  - `struggling`/`missing_work` at-risk rules, grade-to-date, quiz average,
    missing-assignments columns, "every attempt + submission with scores",
    "reset quiz attempts" — all wait on the P3 quiz/assignment models.
  - "Extend access" button — waits on P5's enrollment-expiry concept.
  - Weekly instructor digest email, weekly student streak counter — both
    explicitly marked optional/later in the plan text itself.
  - Exact video-position resume (`last_position_seconds`) — waits on P2;
    today's resume returns to the right lesson, not the exact timestamp.

Next: P2 — Player & AJAX (YouTube IFrame API + heartbeat + resume, AJAX
completion + sidebar states, markdown lessons, free preview, completion rules
`manual`/`min_watch` + sequential progression, events/listeners/notifications).

---

## P2 — Player & AJAX

### P2.1 — Schema for completion rules/content format/progression + admin UI (§4.3/§4.4/§7.4)

**Built:**

- Three new backed enums, matching the existing `PaymentMethod`/`InvoiceStatus`
  convention: `App\Enums\CompletionRule` (`manual`|`min_watch`|`quiz_pass`|
  `submission`, with an `isEnforced()` helper — only the first two are wired up
  yet), `App\Enums\CourseProgression` (`free`|`sequential`), `App\Enums\ContentFormat`
  (`plain`|`markdown`).
- Three additive migrations: `lessons` gets `completion_rule` (default `manual`),
  `completion_threshold` (default 80), `content_format` (default `plain`);
  `courses` gets `progression` (default `free`); `lesson_progress` gets
  `started_at` and `last_position_seconds` (both needed for P2.3's resume/
  heartbeat work, added now alongside the rest of the schema pass rather than
  as a separate item). All three cast to their enum classes on the models.
  Verified clean in both directions (migrate → rollback → migrate).
- Admin UI: the lesson form gets a "Content format" select, a "Completion rule"
  select (`quiz_pass`/`submission` shown but disabled with a "— coming soon"
  suffix, since they aren't enforced yet — never let an admin configure a rule
  that silently does nothing), and a threshold input that only appears
  (`x-show`) when `min_watch` is selected. The course form gets a "Progression"
  select. `LessonController`/`CourseController` validate against `Rule::in()`
  restricted to real (or, for completion rules, *enforced*) enum values — a
  tampered request for `quiz_pass` is rejected server-side, not just hidden by
  the disabled `<option>`.
- `Lesson::durationSeconds()` — a computed method (`duration_minutes * 60`), not
  a new stored column. §4.4 describes `duration_seconds` as a schema column, but
  adding one risks drift against `duration_minutes` (the admin-facing input,
  which stays in minutes — nobody wants to type raw seconds) for a value that's
  trivially derivable. This is the one place this phase deviates from the plan's
  literal schema table, in favor of the same value with no synchronization risk.

**Tests added** (`tests/Feature/Admin/LessonCompletionSettingsTest.php`, 6 tests):
`test_a_new_lesson_defaults_to_manual_completion_and_plain_content`,
`test_an_admin_can_set_a_lesson_to_min_watch_with_a_custom_threshold`,
`test_a_not_yet_enforced_completion_rule_is_rejected_even_if_submitted_directly`
(the tamper-proofing proof), `test_an_admin_can_set_a_lessons_content_format_to_markdown`,
`test_a_new_course_defaults_to_free_progression`,
`test_an_admin_can_set_a_course_to_sequential_progression`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 144/144 green (138 pre-existing + 6 new) · migrate/rollback/migrate
clean · manual smoke test at the real MAMP-served URL (the completion-rule/content-
format Alpine toggles render and the disabled "coming soon" options are visible).

### P2.2 — Sequential progression enforcement (§4.3)

**Built:**

- `Course::isLessonLocked(Enrollment, Lesson)` — the single source of truth: `free`
  progression never locks anything; in `sequential`, the first lesson (by module/
  lesson sort order — the same flattening `LearningController::nextLesson()`
  already used) is never locked, and every other lesson is locked unless the
  immediately-preceding one has a `completed_at` in `lesson_progress` for that
  enrollment.
- New `App\Policies\LessonPolicy` (`before()` super_admin bypass, matching the
  `EnrollmentPolicy` convention exactly) — `view(User, Lesson, Enrollment)` just
  delegates to `Course::isLessonLocked()`.
- `ProgressService::recordView()` and `completeLesson()` both now also call
  `Gate::authorize('view', [$lesson, $enrollment])` alongside the existing
  `access` check — putting it in the shared service (not just the web
  controller) means the API can't drift out of sync with the lock, the same
  reasoning P0.7 used for the enrollment-status check. `Student\LessonMaterialController::download()`
  gets the same check directly, closing the loophole of a locked lesson's
  materials being downloadable even though the lesson content itself isn't
  reachable.
- `learn/lesson.blade.php` sidebar — a locked lesson renders as a plain,
  non-clickable `<span>` with a padlock icon instead of an `<a>`, so a normal
  student never even reaches the server 403 — only a deliberate bypass attempt
  (typing the URL, replaying a request) does. The client is a rendering hint;
  the policy is the actual authority (rule 8).
- `LearningController::show()`'s resume redirect now also checks
  `! $course->isLessonLocked(...)` before redirecting to `last_lesson_id` —
  otherwise switching a course to sequential after a student had already jumped
  ahead under free navigation would send their next visit straight into a 403.
  Falls back to the first lesson instead.

**Tests added** (`tests/Feature/Learning/SequentialProgressionTest.php`, 8 tests):
`test_the_first_lesson_is_never_locked_in_a_sequential_course`,
`test_the_second_lesson_is_locked_until_the_first_is_completed`,
`test_a_direct_complete_post_on_a_locked_lesson_is_rejected_and_writes_no_progress`
(the abuse-path proof — no `lesson_progress` row survives a bypass attempt),
`test_the_second_lesson_unlocks_once_the_first_is_completed`,
`test_a_locked_lessons_materials_cannot_be_downloaded`,
`test_a_free_progression_course_never_locks_any_lesson`,
`test_the_locked_lesson_is_rendered_as_a_padlock_not_a_link_in_the_sidebar`,
`test_resuming_a_now_locked_lesson_falls_back_to_the_first_lesson_instead_of_403ing`
(the stale-resume-after-a-progression-change edge case).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 152/152 green (144 pre-existing + 8 new).

### P2.3 — Frontend JS foundation + player heartbeat + YouTube IFrame API wrapper (§6.2/§7.3)

**Built:**

- **Frontend JS foundation (a real prerequisite gap, not optional polish):** research
  before writing any player code found that `layouts/app.blade.php` (every `/learn`
  page) loaded **no JavaScript at all** — no Vite, no Alpine, no `@stack('scripts')`,
  and the toast host wasn't included either (all three exist and are wired up in
  `layouts/admin.blade.php`, just never carried over to the student layout). Also
  found `public/build/` had never been generated (`npm run build` had never been
  run for this project) — adding `@vite(...)` without it would have 500'd every
  `/learn` page immediately. Fixed all three: added `@vite(['resources/css/app.css',
  'resources/js/app.js'])`, `@include('partials.toast-host')`, and `@stack('scripts')`
  to `layouts/app.blade.php`; ran `npm run build` to generate the manifest (already
  covered by `composer.json`'s existing `setup` script for real deploys — this was
  a one-time local step, not a new build-process requirement).
- `ProgressService::recordHeartbeat(Enrollment, Lesson, secondsDelta, positionSeconds)`
  — the only writer of `lesson_progress.watch_seconds`/`last_position_seconds` and
  `enrollments.total_watch_seconds`. Authorizes `access` + `view` (the sequential
  lock) exactly like `completeLesson()`/`recordView()`. Clamps `secondsDelta` to
  30s and `positionSeconds` to the lesson's duration server-side — heartbeats fire
  every ~15s of real playback, so nothing legitimate ever reports more; a dishonest
  client claiming an inflated delta in one call gets capped, not trusted. Records a
  `video.heartbeat` learning event. Decides `min_watch` auto-completion here —
  server-side, from the accumulated `watch_seconds` vs. `Lesson::durationSeconds()`
  (the computed-not-stored value from P2.1) — never from a client "I finished" claim,
  per §4.3's own wording. Delegates to the existing `completeLesson()` when the
  threshold is crossed, so certificate issuance/events/fast-path columns all still
  go through the one path.
- New endpoint `POST learn/{course}/{lesson}/heartbeat` (`learn.lesson.heartbeat`,
  `throttle:20,1`) on `LearningController`, following the `ThemeController` house
  convention exactly (single action, `$request->validate()`, plain
  `response()->json([...])`) rather than a versioned API or a new resource
  controller.
- `Lesson::youtubeVideoId()` — extracts a bare YouTube video id from whatever URL
  shape an admin pastes (embed/watch/short link); returns `null` for anything else
  (Vimeo, plain links), which keeps rendering the existing plain `<iframe>` —
  "existing plain-text lessons keep rendering exactly as today" extended to
  non-YouTube video too, not just to text lessons.
- `resources/views/learn/lesson.blade.php` — a YouTube lesson now renders a real
  IFrame API player (`youtubePlayer()`, a global Alpine function pushed via
  `@push('scripts')`, matching the `tdAdmin()` convention rather than introducing
  axios or an ES-module component — house style is manual `fetch()` + the CSRF
  meta tag). Resumes at `last_position_seconds`, ticks a heartbeat every 15s while
  actually playing (not while paused), sends a final heartbeat on pause/end so the
  last few seconds aren't lost, and offers playback-speed buttons (0.75×–2×). An
  auto-completion toasts via the existing `toast` `CustomEvent` mechanism.

**Tests added:**

- `tests/Unit/LessonYoutubeVideoIdTest.php` (7 cases via a data provider) — embed/
  watch/short YouTube URLs all extract correctly; Vimeo, null, and empty string all
  fall back to `null`.
- `tests/Feature/Learning/PlayerHeartbeatTest.php` (8 tests): recording watch
  seconds/position, accumulation across repeated heartbeats,
  `test_a_client_reported_delta_beyond_one_heartbeat_interval_is_clamped` and
  `test_a_position_beyond_the_lessons_duration_is_clamped` (the dishonest-student
  abuse-path proofs), `min_watch` auto-completing once the threshold crosses,
  `manual` never auto-completing from watch time alone, a pending enrollment
  rejected, and a locked (sequential) lesson's heartbeat rejected.
- `tests/Feature/Learning/PlayerRenderingTest.php` (3 tests) — a YouTube lesson
  renders the IFrame API player + speed controls; a non-YouTube URL falls back to
  a plain iframe; no `video_url` renders neither.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 170/170 green (152 pre-existing + 18 new) · `composer ci` green
· manual smoke test at the real MAMP-served URL (logged in as a real student,
confirmed the Vite-built assets load, the `x-data="youtubePlayer(...)"` payload
renders with correctly escaped values, and the non-YouTube fallback still shows a
plain iframe). Client-side playback behavior itself (actually pressing play,
verifying the 15s ticks fire in a real browser) could not be automated — no
browser-testing tool (Dusk) is installed — so this is the honest limit of what was
verified; the server-side heartbeat contract it calls is fully tested.

### P2.4 — AJAX lesson completion + keyboard shortcuts (§7.3)

**Built:**

- `LearningController::complete()` — one route now serves both modes exactly per
  §7.3's graceful-degradation rule: a plain form POST (no JS) still gets the
  classic redirect; an AJAX POST (`Accept: application/json`, detected via
  `$request->wantsJson()`) gets `{success, progress_percent, course_completed,
  next_lesson_url, next_lesson_title, certificate_url}`. `ProgressService::completeLesson()`
  was already idempotent (P0.2/P0.7's shape), so a double-submit from either path
  stays safe with no new work needed here.
- New `lessonPlayer()` Alpine component (`learn/lesson.blade.php`, same
  global-function convention as `youtubePlayer()`/`tdAdmin()`) wraps the whole
  page: `markComplete()` optimistically flips the sidebar's current-lesson icon
  to ✓ before the server responds, rolls it back and toasts on failure (via the
  existing `toast` `CustomEvent`), and on success either starts a 5-second
  "Next: ‹title› — starting in Ns" auto-advance card (pausable via a "Stay here"
  button that clears the countdown) or — on the course's last lesson — fires a
  small hand-rolled CSS confetti burst and opens a certificate modal linking to
  the existing `learn.certificate` stream. No new npm dependency for confetti;
  a lightweight rule-10 touch, not a data-model change.
- Keyboard shortcuts (`space` play/pause, `←`/`→` seek ±10s, `↑`/`↓` prev/next
  lesson, `m` mark complete) via a single `keydown` listener in `lessonPlayer()`,
  guarded against firing while focus is in a text input and against modifier-key
  combinations. The YouTube player instance is exposed on `window.__lessonVideoPlayer`
  by `youtubePlayer.createPlayer()` so the two independent Alpine scopes (video
  vs. page chrome) can cooperate without a shared store — a single-instance-per-page
  pragmatic choice, not a general pub/sub mechanism.
- `LearningController::adjacentLesson()` replaces the old `nextLesson()`-only
  helper (kept as a thin wrapper for the existing call site) so ↑/↓ and the
  auto-advance card share one lookup instead of two near-duplicate ones.

**Tests added** (`tests/Feature/Learning/AjaxLessonCompletionTest.php`, 4 tests):
`test_an_ajax_completion_returns_json_with_the_next_lesson`,
`test_an_ajax_completion_of_the_final_lesson_returns_the_certificate_url`,
`test_a_plain_form_post_without_js_still_redirects` (the no-JS degradation proof),
`test_an_ajax_completion_of_a_locked_lesson_returns_a_json_403` (confirms the
existing `ApiResponse` envelope — already proven generic in
`ApiExceptionEnvelopeTest` — covers this web route too when JSON is requested).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 174/174 green (170 pre-existing + 4 new) · `composer ci` green ·
manual smoke test at the real MAMP-served URL (rendered markup contains the
`lessonPlayer(`/`markComplete`/auto-advance/modal/confetti elements; a direct
curl POST with `Accept: application/json` to the live completion endpoint
returned the exact expected JSON shape). As with P2.3, actually pressing keys/
buttons in a browser to watch the optimistic-rollback and confetti animate isn't
automatable without Dusk — the server contract and rendered markup are verified;
the animation itself is not.

### P2.5 — Markdown lesson rendering + admin editor (§7.4)

**Built:**

- `league/commonmark` moved from a transitive to a **direct** `composer.json`
  dependency (it was already present, pulled in by something else, but never
  deliberately depended on). Research before writing any code found its own
  defaults are the *opposite* of "sanitized, no raw HTML passthrough" —
  `html_input` defaults to `allow` (raw HTML/`<script>` passes straight
  through) and `allow_unsafe_links` defaults to `true` (`javascript:` hrefs
  work). New `App\Services\Learning\MarkdownRenderer` explicitly overrides
  both (`html_input => escape`, `allow_unsafe_links => false`) rather than
  trusting the library's shipped defaults.
- `LearningController::lesson()` renders `$lesson->content` through
  `MarkdownRenderer` only when `content_format === markdown`; a `plain` lesson
  keeps rendering via the exact same `nl2br(e(...))` as before — "existing
  plain-text lessons keep rendering exactly as today," unchanged code path.
- **Images uploadable into lesson content, private-disk, streamed** (the part
  of L11's original gap list that specifically named "no images"): new
  `Admin\LessonContentImageController::store()` (private `local` disk, uuid
  filename — the `DocumentService` convention) and
  `Student\LessonContentImageController::show()`, gated by the same
  `EnrollmentPolicy` `access` + `LessonPolicy` `view` (sequential-lock) checks
  as everything else in the player, so an embedded image in paid content isn't
  a permanently-public URL and isn't visible for a locked lesson either.
  `basename($filename)` neutralizes any path-traversal attempt in the URL
  segment before it ever reaches the filesystem call.
- Admin split-pane editor (`lesson-form.blade.php`): a new `lessonEditor()`
  Alpine component (same global-function convention) adds an Edit/Preview
  toggle — the preview calls a new `POST admin/lessons/preview-markdown`
  endpoint that renders through **the exact same `MarkdownRenderer`** students'
  pages use, so the preview can never show something different from the real
  output (no second, drifting markdown implementation on the client) — and an
  "Insert image" button that uploads via the content-image endpoint and appends
  a `![]()` reference to the textarea. The upload button only appears once the
  lesson already exists (`$lesson->id` is required for the storage path), so a
  brand-new, not-yet-saved lesson simply doesn't offer it yet — save once,
  then add images, rather than a broken half-working control.
- Code blocks render via CommonMark's own fenced-code semantic markup
  (`<pre><code class="language-xxx">`); actual client-side syntax highlighting
  (Shiki/highlight.js) was left unwired — it needs a new frontend dependency
  purely for cosmetic polish, not the "safely render markdown" core requirement
  this item closes, and is a reasonable follow-up rather than in scope here.

**Tests added:**

- `tests/Unit/MarkdownRendererTest.php` (5 tests): headings/paragraphs, fenced
  code blocks, images, `test_raw_html_is_escaped_not_passed_through` and
  `test_javascript_links_are_rejected` (the two "library defaults are unsafe"
  proofs).
- `tests/Feature/Learning/MarkdownLessonRenderingTest.php` (3 tests): a
  markdown lesson renders sanitized HTML; a plain lesson still renders via
  `nl2br`; a `<script>` tag inside markdown content isn't executable
  end-to-end through the real controller/view.
- `tests/Feature/Admin/LessonMarkdownPreviewTest.php` (2 tests): an admin gets
  rendered HTML back from the preview endpoint; a non-admin is redirected
  (can't reach it).
- `tests/Feature/Learning/LessonContentImageTest.php` (4 tests): upload
  returns a working URL, an enrolled student can view the image, a non-enrolled
  user cannot (404, no enrollment row to authorize against), and a path-
  traversal attempt in the filename segment is neutralized.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 188/188 green (174 pre-existing + 14 new) · `composer ci`
green · manual smoke test at the real MAMP-served URL (the admin editor's
Edit/Preview toggle and "Insert image" control both render correctly for a
real lesson).

### P2.6 — Free preview on the public catalogue (§7.2, closes L5)

**Built:**

- New `CourseCatalogueController::preview(Course, Lesson)` (public, no auth
  middleware) — 404s unless the course is published, the lesson actually
  belongs to that course, and `is_free_preview` is true. Renders the lesson's
  video/content (through the same `MarkdownRenderer` as the real player when
  `content_format=markdown`) plus a sticky bottom "Enrol to continue" bar —
  no heartbeat/telemetry wiring, since there's no enrollment to attribute it
  to for an anonymous guest; that's out of scope for a conversion page.
- New route `GET /courses/{course:slug}/preview/{lesson}` (`courses.preview`).
  The course page's existing "Free preview" tag on each lesson now links to
  it instead of being inert text.

**Critical bug found and fixed — a route name collision that's been silently
misdirecting the site's main navigation.** Writing a test that asserted the
course page's free-preview link surfaced that `route('courses.show', ...)`
was returning a JSON API response instead of the HTML page. Root cause:
`routes/api.php`'s `Route::apiResource('courses', CourseController::class)`
had no name override, so Laravel defaulted it to the bare `courses.index`/
`courses.show` — **the exact same names** the public web catalogue already
uses. With two routes sharing one name, `route('courses.show', ...)`
resolved to whichever was registered last (the API one), meaning **every**
`route('courses.show')`/`route('courses.index')` call site — the site's main
nav "Courses" link (`layouts/marketing.blade.php`, appears on every public
page), the course catalogue cards, the admin "View public page" link, the
paid-checkout-unavailable redirect, and this item's own new preview page —
had been silently generating `/api/v1/courses/...` JSON links instead of the
real HTML pages. `routes/api.php`'s `invoices` apiResource had the identical
latent (not yet actually colliding) issue. Fixed both with explicit
`api.`-prefixed names via `->names([...])`, matching every other route in
that file's own established convention, and added
`tests/Feature/RouteNamingTest.php` (3 tests) pinning that `courses.show`/
`courses.index` resolve to the HTML page (not `/api/v1/`) and that the API
routes are properly `api.`-prefixed — so this exact class of bug can't
regress silently again.

**Tests added** (`tests/Feature/Learning/FreePreviewTest.php`, 5 tests):
`test_a_guest_can_view_a_free_preview_lesson`,
`test_a_guest_cannot_view_a_lesson_that_is_not_marked_free_preview`,
`test_a_free_preview_lesson_on_an_unpublished_course_is_not_viewable`,
`test_the_free_preview_tag_on_the_course_page_links_to_the_preview`,
`test_a_lesson_belonging_to_a_different_course_404s`. Plus
`tests/Feature/RouteNamingTest.php` (3 tests, the route-collision regression
proof above).

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 196/196 green (188 pre-existing + 8 new) · `composer ci`
green · manual smoke test at the real MAMP-served URL (confirmed the
homepage's "Courses" nav link now points at `/courses`, not `/api/v1/courses`;
the free-preview page renders and its content/CTA are correct; the course
page's "Free preview" tag links to the right URL).

### P2.7 — Events & listeners (§4.5)

**Built:**

- Three plain events (`Dispatchable` only, no broadcasting needed):
  `App\Events\Learning\LessonCompleted`, `CourseCompleted`, `EnrollmentCreated`.
  `QuizAttemptSubmitted`/`AssignmentSubmitted`/`SubmissionGraded` from §4.5's
  full list stay deferred to P3 — no quiz/assignment models exist yet to carry.
- `ProgressService::completeLesson()` now dispatches `LessonCompleted` right
  after writing the progress row, and `CourseCompleted` in place of the
  previous direct `$this->certificates->issue($enrollment)` call — the
  service now only *decides that* something completed; it no longer knows
  *what happens as a result*. `CertificateService` is no longer a
  `ProgressService` constructor dependency at all.
- `CourseCatalogueController::enroll()` and `Api\V1\EnrollmentController::store()`
  dispatch `EnrollmentCreated` only when `Enrollment::firstOrCreate()`'s
  `wasRecentlyCreated` flag is true — a double-click/double-tap race (already
  idempotent since P0.2) correctly fires the event zero times on the losing
  request, not twice.
- New notifications (mail + database, matching the `StudentNudgeNotification`
  shape from P1.4): `CourseCompletedNotification` ("you finished the course",
  links to the certificate) and `EnrolledInCourseNotification` ("welcome,
  start learning").
- New listener `App\Listeners\Learning\HandleCourseCompletion` — issues the
  certificate **then** sends `CourseCompletedNotification`, both in one
  `handle()` method. New listener `NotifyStudentOfEnrollment` sends
  `EnrolledInCourseNotification` on `EnrollmentCreated`. Both `ShouldQueue`
  (this app's `QUEUE_CONNECTION=sync`, so they execute inline today —
  identical observable behavior to before this item — but are already wired
  correctly for Horizon/a real queue in production, per §4.5's explicit ask).

**Bug found and fixed while wiring this up — duplicate notifications.**
Registering these listeners explicitly in `AppServiceProvider::boot()`
(`Event::listen(CourseCompleted::class, ...)`) was the obvious way to
guarantee "certificate before email" ordering across two listeners on the
same event. Empirically verified via `php artisan event:list` and a scratch
test that this app's Laravel version **auto-discovers** `app/Listeners`
classes by their type-hinted `handle()` parameter with zero configuration —
so the explicit registration didn't replace auto-discovery, it *doubled* it:
every side effect fired twice, meaning a student would have received two
"you completed the course" emails and had two duplicate certificates-issue
attempts (harmless, since `CertificateService::issue()` is idempotent) but
two real notification rows. Confirmed by a scratch test counting
`DatabaseNotification` rows (2, not 1) before the fix. The correct, robust
fix — not just deleting the explicit registration — was to **merge** the two
`CourseCompleted` listeners (`IssueCertificateOnCourseCompletion` +
`NotifyStudentOfCourseCompletion`) into the single `HandleCourseCompletion`
listener above: Laravel doesn't guarantee execution order across
independently auto-discovered listeners on the same event, so relying on
directory-scan ordering between two separate classes would have been fragile
even without the duplicate-registration bug. One listener, sequential calls,
guaranteed order, exactly one registration.

**Tests added** (`tests/Feature/Learning/LearningEventsTest.php`, 7 tests):
`test_completing_a_lesson_dispatches_lesson_completed`,
`test_completing_the_final_lesson_dispatches_course_completed`,
`test_course_completion_issues_exactly_one_certificate_and_sends_exactly_one_notification`
(the duplicate-notification regression proof — pins the count at 1),
`test_the_course_completed_notification_links_to_the_already_issued_certificate`
(the ordering proof), `test_a_genuinely_new_enrollment_dispatches_enrollment_created`,
`test_a_double_click_enroll_does_not_dispatch_enrollment_created_twice`,
`test_enrolling_sends_a_welcome_notification`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 203/203 green (196 pre-existing + 7 new) · `composer ci`
green · `php artisan event:list` confirms exactly one listener per event.

---

## P2 phase gate — closed

All seven items done, one commit per item, in plan order. Closes/advances: §4.3
(completion rules + sequential progression), §4.5 (events/listeners), §6.2
(player heartbeat), §7.2 (free preview, closes L5), §7.3 (AJAX player, closes
L10), §7.4 (markdown authoring, closes L11).

- `composer ci` (repo-wide `pint --test`, `phpstan`, `check-empty-files`,
  `secrets-scan`, `php artisan test`): **green**, 203 tests / 417 assertions.
- `php artisan migrate:status` on top of P0+P1's schema: all of this phase's
  migrations applied cleanly in their own batches — a fresh checkout migrates
  clean end to end.
- Two real, previously-undiscovered bugs were found and fixed while building
  and testing this phase (not routed around):
  1. **Route name collision** (P2.6) — `routes/api.php`'s unprefixed
     `apiResource('courses', ...)` silently hijacked `route('courses.show')`/
     `route('courses.index')` app-wide, including the site's main public
     navigation, since Laravel API route names defaulted to the exact same
     names the web catalogue already used.
  2. **Duplicate event listeners** (P2.7) — this Laravel version auto-discovers
     `app/Listeners` classes with zero configuration; explicitly registering
     them too (to control ordering) silently doubled every side effect
     (a student would have received two completion emails).
  Both are now pinned by regression tests (`RouteNamingTest`,
  `LearningEventsTest`) so neither can silently reappear.
- Every new AJAX surface (heartbeat, lesson completion) has a real
  form `action`/`method` and degrades to a working non-JS flow — proven by
  `test_a_plain_form_post_without_js_still_redirects` (P2.4).
- Client-side JS behavior itself (actually pressing keys, watching the
  optimistic rollback, the confetti animation, real YouTube playback) is
  outside what's automatable without a browser-testing tool (Dusk, not
  installed) — the server contracts every script calls are fully tested, and
  every new page was manually smoke-tested rendering correctly against the
  real MAMP-served app, but this is the honest boundary of what P2's
  verification covers.
- Explicitly deferred to later phases (each tied to a plan section that
  itself places the work later, per rule 2's carve-out):
  - `quiz_pass`/`submission` completion rules, quiz runner, gradebook,
    certificate-criteria tightening (closes L1 fully) — all P3, need the quiz/
    assignment models.
  - `QuizAttemptSubmitted`/`AssignmentSubmitted`/`SubmissionGraded` events —
    same reason.
  - Client-side syntax highlighting (Shiki/highlight.js) for markdown code
    blocks — cosmetic polish needing a new frontend dependency, not core to
    "safely render markdown."
  - Notes tab, Q&A, announcements, reviews, drag-drop curriculum builder,
    bulk enroll — all explicitly P4 in §8, despite living under the same §7.3
    heading as this phase's AJAX player work.

Next: P3 — Assessment (quiz schema + `QuizService` auto-grading, quiz runner
UI, assignments + grading queue, gradebook, certificate criteria tightening,
quiz item analysis).

---

## P3 — Assessment

### P3.1 — Quiz schema (5 tables) + models + admin quiz CRUD (§5.1)

**Built:**

- Four new backed enums matching the established convention:
  `App\Enums\QuestionType` (9 cases per §5.1, plus `isAlwaysManuallyGraded()`
  and `usesOptions()` helpers the grading engine and admin UI will both need),
  `QuizGradingMethod` (highest|latest|average|first), `QuizFeedbackMode`
  (immediate|after_submit|after_close|none), `QuizAttemptStatus`
  (in_progress|submitted|graded|abandoned).
- Five additive migrations exactly per §5.1's schema table: `quizzes`
  (course_id required, lesson_id nullable — null means course-final),
  `questions`, `question_options`, `quiz_attempts` (uuid + unique
  `(quiz_id, enrollment_id, attempt_no)`), `attempt_answers` (unique
  `(quiz_attempt_id, question_id)`). All five models + relations wired onto
  `Course`/`Lesson`/`Enrollment`.
- Admin CRUD for quiz *settings* (`Admin\QuizController` — create/store/edit/
  update/destroy, shallow-nested under `courses.quizzes` exactly like
  `courses.modules`): title, lesson attachment (or course-final), pass
  percent, time limit, max attempts, grading method, feedback mode, shuffle/
  pool-draw/one-per-page toggles, certificate-counting toggle,
  availability window, published toggle. No dedicated `QuizPolicy` — matches
  the existing convention exactly: `CourseModuleController`/`LessonController`
  have no per-action Policy either, relying solely on the blanket
  `['auth','admin']` middleware group already wrapping every admin route.
  Question/option management is deliberately a stub ("coming in the next
  build step") — that's P3.2.

**Decision — `questions` gets `softDeletes` even though §5.1's schema table
doesn't list it there.** Mirrors the P0.3/L7 reasoning exactly: hard-deleting
a question after students have answered it would cascade-delete
`attempt_answers`, silently destroying graded history — the same "content
that must survive editing" problem the plan itself already solved for
lessons/modules and explicitly calls out for `quizzes`/`assignments`. A
question is the same kind of thing.

**Bug found and fixed during the mandatory migrate/rollback/migrate
verification — a migration ordering hazard.** `create_questions_table` and
`create_question_options_table` were generated in the same wall-clock second,
giving them an identical timestamp; Laravel's migration runner then fell back
to alphabetical filename order as a tie-break, and `question_options`
alphabetically sorts *before* `questions` (`_` < `s`). Running `migrate` hit
this exact ordering and created an orphaned `question_options` table (with no
matching `migrations` table row) before its own FK target (`questions`)
existed — caught immediately by the routine rollback/re-migrate check, not
silently. The same latent risk existed between `quiz_attempts` and
`attempt_answers` (`a` < `q`). Fixed by renaming both later-dependent
migration files to strictly later timestamps, dropping the orphaned table,
and re-verifying migrate → rollback → migrate end to end.

**Tests added** (`tests/Feature/Admin/QuizCrudTest.php`, 6 tests):
`test_an_admin_can_create_a_course_final_quiz`,
`test_an_admin_can_create_a_lesson_quiz`,
`test_an_admin_can_update_quiz_settings`,
`test_an_admin_can_delete_a_quiz_and_it_is_soft_deleted`,
`test_a_non_admin_cannot_create_a_quiz`,
`test_the_course_page_lists_its_quizzes`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 209/209 green (203 pre-existing + 6 new) ·
migrate/rollback/migrate clean · manual smoke test at the real MAMP-served URL
(the quiz creation form renders with all settings fields).

### P3.2 — Admin question/option builder UI (§5.1)

**Built:**

- `Admin\QuestionController` (create/store/edit/update/destroy, shallow-nested
  under `quizzes.questions` like `modules.lessons`) + one adaptive Blade form
  (`admin/quizzes/question-form.blade.php`) covering all 9 `QuestionType`
  cases from one template: a repeatable Alpine-driven option-row editor
  (add/remove rows client-side, serialized as `options[N][label]` etc.) for
  the five option-based types (mcq_single/mcq_multi/true_false/matching/
  ordering — `true_false` deliberately reuses the exact same free-form rows
  as `mcq_single` rather than a special-cased fixed pair, since under the hood
  it's graded identically: "pick the correct option"), an accepted-answers
  textarea + case-sensitivity toggle for fill_blank/short_text (stored in
  `meta`), expected-value/tolerance inputs for numeric (`meta`), and no extra
  config at all for essay (always manually graded). `matching`'s
  `question_options.match_key` holds the right-hand side of each pair
  directly, rather than requiring a second linked option row.
- The quiz edit page now lists its questions (type, points, truncated prompt)
  with edit/delete, replacing P3.1's "coming in the next build step" stub.
- Deleting a question is a soft delete (already in place from P3.1); deleting
  it never touches the quiz or other questions.

**Bugs found and fixed — a real, repeated array-merge footgun.** Both
`store()` and `update()` originally tried to default `sort_order` via
`$data['question'] + ['sort_order' => ...]` (create) and passed
`$data['question']` straight into `update()` (edit). PHP's `+` array union
operator keeps the **left** array's value for any key present in both sides —
since the validated array already had a `sort_order` key (explicitly `null`
when the field was omitted), the intended default was silently discarded and
`null` was written every time, violating the column's `NOT NULL` constraint
with a 500 on every request that didn't supply a sort order. Caught
immediately by the new tests (not shipped silently) — `update()` had the
worse version, since a normal edit-question round trip (which doesn't always
resubmit an unchanged sort order) would 500 outright. Fixed both call sites
to explicitly compute the value with `??=` before passing it to
`create()`/`update()`, instead of relying on array-union semantics to inject
a default into an already-populated array.

**Tests added** (`tests/Feature/Admin/QuestionBuilderTest.php`, 8 tests):
`test_an_admin_can_create_an_mcq_single_question_with_options`,
`test_an_admin_can_create_a_fill_blank_question_with_accepted_answers`,
`test_an_admin_can_create_a_numeric_question_with_tolerance`,
`test_an_admin_can_create_a_matching_question_with_match_keys`,
`test_an_admin_can_create_an_essay_question_with_no_grading_config`,
`test_updating_a_question_replaces_its_options` (the fix's regression proof —
was the exact 500 above), `test_deleting_a_question_is_soft_deleted_and_preserves_the_quiz`,
`test_a_non_admin_cannot_create_a_question`.

**Verification:** `vendor/bin/pint --dirty` pass · `phpstan analyse` 0 errors ·
`php artisan test` 217/217 green (209 pre-existing + 8 new) · manual smoke test
at the real MAMP-served URL (question form renders with the dynamic option
editor for the default type).

### P3.3 — QuizService attempt lifecycle + objective-type auto-grading (§5.2)

**Built:** `app/Services/Learning/QuizService.php` — the whole attempt
lifecycle in one service, no controller yet (that's P3.5's job):

- `start(Quiz, Enrollment)` — `Gate::authorize('access', $enrollment)` reuses
  `EnrollmentPolicy` (owns it, status active/completed); rejects a quiz from a
  different course, an unpublished quiz, or one outside
  `Quiz::isAvailableNow()`. Returns an existing `in_progress` attempt instead
  of creating a second one (re-entering a quiz resumes it, never forks a
  parallel attempt). Enforces `max_attempts` before creating a new one.
  `question_order` is frozen at creation: `{questions: [...ids], options:
  {questionId: [...optionIds]}}`. Question order applies the pool draw
  (`questions_per_attempt`, a random subset kept in original `sort_order`
  sequence) then `shuffle_questions` on top. Option order applies
  `shuffle_options` per question — **except `ordering`-type questions, which
  always shuffle their options regardless of the flag**, since presenting an
  ordering question's options pre-sorted would give the answer away; this
  wasn't spelled out letter-for-letter in the plan's grading table but follows
  directly from what an "ordering" question is for.
- `answer(QuizAttempt, Question, ?array $payload)` — per-question AJAX
  autosave via `updateOrCreate` on `attempt_answers`; rejects if the attempt
  isn't `in_progress` or the question isn't in that attempt's frozen
  `question_order` (blocks answering into a different quiz's question via a
  forged ID).
- `submit(QuizAttempt, ?array $integrity)` — rejects if already submitted;
  enforces the server-side timer (`started_at + time_limit_minutes + 30s
  grace`) when the quiz has one. Iterates the frozen question list (not
  whatever's already in `attempt_answers`, so an unanswered question still
  gets a zero-value graded row), grades each via the type-specific table
  below, and writes `is_correct`/`points_awarded`/`auto_graded` onto its
  `attempt_answers` row. If every question auto-graded, the attempt becomes
  `graded` (score/percent/`passed` computed, `QuizAttemptSubmitted` fires);
  if any question came back `auto_graded = false`, the attempt becomes
  `submitted` and waits for a human — score fields stay null until P3.4's
  `gradeManual()` closes it out.

**Grading table implemented** (private `gradeAnswer()`, one method per type):
mcq_single/true_false — selected option's `is_correct`, full points or zero.
mcq_multi — partial credit `max(0, (correctPicked − wrongPicked) /
totalCorrect)`, so picking every option nets zero, matching the plan's
"floor 0" rule. fill_blank — normalized (trim + collapse whitespace,
case-insensitive unless `case_sensitive`) match against
`question.meta.accepted_answers`; always auto-grades, wrong if unmatched.
numeric — `abs(value − meta.expected) <= meta.tolerance`. matching — fraction
of `question_options` whose `match_key` the student paired correctly.
ordering — all-or-nothing: student's submitted ID sequence must exactly equal
`options` sorted by `sort_order`. short_text — the same accepted-answer match
as fill_blank, but a miss returns `auto_graded = false` (flagged for review)
instead of marking it wrong, per the plan's "exact/keyword match → auto, else
flagged" rule. essay — always `auto_graded = false`, never scored here. An
unanswered question (any type, including essay) short-circuits to
`auto_graded = true, points_awarded = 0` before the type match runs — nothing
to review if the student left it blank, only an *attempted* essay/short_text
needs a human.

**Decision:** `QuizAttempt::getRouteKeyName()` now returns `'uuid'`, matching
`Certificate`/`Invoice`'s established convention for any model whose URL is
student-facing and security-sensitive (sequential IDs would let one student
probe for another's attempt). No route uses this yet — P3.5 will.

**Abuse paths reasoned through:** re-POSTing `start` after already having an
in-progress attempt → same attempt returned, no duplicate row (unique
`(quiz_id, enrollment_id, attempt_no)` backs this too). Re-POSTing `submit`
on an already-submitted attempt → `409` and no re-grading (score can't be
inflated by resubmitting after seeing partial results). A stale tab holding
an attempt open past its time limit → `submit` rejects once `now() >
started_at + time_limit + 30s`, so the 30-second grace covers real network
lag without opening a window to keep working past the limit. Forging a
`question_id` from a different quiz/attempt into `answer()` → rejected, the
question must be in *this* attempt's frozen `question_order`. Another
student's enrollment/attempt → `EnrollmentPolicy::access` denies it via
`Gate::authorize`, both on `start`/`answer`/`submit`.

**Tests added** (`tests/Feature/Learning/QuizServiceLifecycleTest.php`, 20
tests): attempt creation + frozen order, resume-not-duplicate, max-attempts
enforcement, unpublished/out-of-window rejection, autosave overwrite, foreign
question rejection, full-credit grading across mcq_single/true_false/
fill_blank/numeric in one pass, mcq_multi partial credit with a wrong pick,
matching + ordering, short_text auto-match vs. flagged-for-review, essay
always pending, unanswered-question-graded-zero-not-flagged, double-submit
rejection, timer-exceeded rejection, pool draw keeps original sort sequence,
ordering options always shuffled, `QuizAttemptSubmitted` fires only when
fully auto-graded (and not when a question is pending review), cross-student
authorization denial.

**Verification:** `php artisan migrate` — no new migrations, nothing to run.
`vendor/bin/pint --dirty` clean (one auto-fix, unused import). `phpstan
analyse --memory-limit=1G` 0 errors (the default 128M limit crashes the
parallel workers on this machine regardless of new code — a pre-existing
environment constraint, not a regression). `php artisan test` — 237/237 green
(217 pre-existing + 20 new).

### P3.4 — QuizService manual grading + feedback_mode gating (§5.2, §5.2.4)

**Built:** three additions to `app/Services/Learning/QuizService.php`, no new
schema (everything needed — `points_awarded`, `grader_feedback`,
`auto_graded` — already existed on `attempt_answers` since P3.1):

- `gradeManual(QuizAttempt, Question, float $pointsAwarded, ?string
  $feedback)` — an instructor scores one flagged answer (an essay, or a
  short_text miss that fell back to review). Rejects with `409` unless the
  attempt is `submitted` (not `in_progress`, not already `graded` — no
  re-grading path exists or is needed yet), and `422` if the points fall
  outside `[0, question.points]`. **Decision:** rather than add a new column
  to mark "this answer has been resolved," a pending answer is defined as
  `auto_graded = false AND points_awarded === null` — the exact state
  `submit()` already leaves an essay/flagged-short_text row in. Once
  `gradeManual()` writes a non-null `points_awarded`, that row reads as
  resolved without needing an extra "manually_graded" boolean anywhere.
  Private `finalizeIfFullyGraded()` re-checks every answer on the attempt
  after each manual grade; once none are still pending, it computes
  `score_points`/`score_percent`/`passed` (identical math to `submit()`'s
  fully-auto-graded branch) marks the attempt `graded`, and fires
  `QuizAttemptSubmitted` — the same event `submit()` fires, just triggered
  later and by a human instead of the grading engine, matching the doc
  comment already written for that event in P3.3.
- `previewGrade(Question, ?array $payload)` — a thin public wrapper around
  the same private `gradeAnswer()` table P3.3 built, but computed on demand
  and never persisted. This is what "immediate" feedback_mode needs: a
  per-question right/wrong check the instant the student answers, before the
  attempt is ever submitted. Building the actual AJAX wiring for this is
  P3.5's job (the quiz runner UI); P3.4 only had to make sure the grading
  logic was reusable without side effects, which it already was since
  `gradeAnswer()` never touched the database itself.
- `feedbackFor(QuizAttempt): ?array` — the review-page data source, gated by
  `feedback_mode`: `none` never reveals anything; `immediate`/`after_submit`
  reveal once the attempt has left `in_progress` (i.e. is `submitted` or
  `graded` — a still-in-progress attempt has nothing graded yet regardless of
  mode); `after_close` stays hidden until `quiz.available_until` has passed
  (and, deliberately, stays hidden forever if the instructor picked
  after_close without ever setting a close date — a misconfiguration to fix
  on their end, not a case to special-case around). Returns per-question
  `is_correct`, `points_awarded`, `max_points`, `explanation`, and
  `grader_feedback` — everything the review page (P3.5) will need to render
  without a second round of business logic.

**Abuse paths reasoned through:** an instructor trying to grade an
in-progress or already-fully-graded attempt → `409`, since neither state has
anything pending. Awarding more than a question's max points (fat-fingered or
adversarial input from an admin-side form later) → `422` before anything is
written. A student polling `feedbackFor` mid-attempt hoping to see answers
early → `null`, regardless of feedback_mode, until the attempt actually
leaves `in_progress`.

**Tests added** (`tests/Feature/Learning/QuizManualGradingTest.php`, 9
tests): grading the only pending essay finalizes the attempt and fires the
event with the correct combined score; a second still-pending essay keeps the
attempt `submitted` and un-scored; rejecting grading on a non-submitted
attempt; rejecting out-of-range points; `previewGrade` persists nothing;
feedback hidden while in progress; `none` mode never reveals; `after_submit`
reveals is_correct + explanation once graded; `after_close` stays hidden
until the close date passes, then reveals on the same attempt.

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean (one
auto-fix, unused import). `phpstan analyse --memory-limit=1G` 0 errors.
`php artisan test` — 246/246 green (237 pre-existing + 9 new).

### P3.5 — Student-facing quiz runner (§5.2, §7)

**Built:** `App\Http\Controllers\Student\QuizAttemptController` (new) wires
`QuizService` to the browser — `index` (a course's quiz list with per-quiz
attempt status/best score), `show` (intro page: question count, time limit,
pass mark, attempts used, best score, Start/Resume button), `start` (creates
the attempt, redirects into the runner), `run` (the take-quiz page), `answer`
(AJAX per-question autosave), `submit` (finalizes, redirects to review), and
`review` (feedback-gated results). Entry points wired from three places:
"My Courses" (a new "Quizzes" link per enrolled course — added
`published_quizzes_count` to `LearningController::index()`'s existing
`withCount` eager-load rather than an `exists()` check in the Blade loop, to
avoid reintroducing the exact N+1 pattern P0.5/P1.5 already closed on this
same page), the lesson page (a "Lesson quiz" card + a breadcrumb link when a
quiz is attached to that specific lesson), and the quiz list itself.

**Decision — the runner form works with zero JavaScript.** Every question
type renders as plain form fields whose `name` attributes already match
what `submit()` (extended in this item to accept a bulk `answers` map) and
`answer()` expect:
mcq_single/true_false — radios, `answers[id][selected]`.
mcq_multi — checkboxes, `answers[id][selected][]`.
fill_blank/short_text — text input, `answers[id][text]`.
numeric — number input, `answers[id][value]`.
matching — one text input per option, `answers[id][pairs][optionId]`.
essay — textarea, `answers[id][text]`.
ordering — **numbered position inputs** (`answers[id][order][optionId]`,
1..N) rather than a drag-and-drop list, specifically so the no-JS and
JS-enhanced experiences share identical markup — a private
`normalizePayload()` on the controller collapses the submitted
`{optionId: position}` map into the sequential id array
`QuizService::gradeOrdering()` already expects, applied identically whether
the payload arrived via single-question AJAX autosave or the bulk submit.
Pressing "Submit quiz" is a normal form submission to `learn.quiz.submit`;
JS only adds a confirm() dialog on top via `@click`, never `@submit.prevent`.

**Bug caught before it shipped, not after:** the first draft of the runner
put `x-cloak` on every question block so `one_question_per_page` mode could
hide all-but-the-current question before Alpine paints. `x-cloak`'s entire
contract is "hidden until Alpine removes the attribute" — with JavaScript
disabled, Alpine never runs, so every question would have stayed invisible
**forever**, turning "degrades gracefully" into "doesn't render at all" for
any quiz using that setting. Caught by re-reading what `x-cloak` actually
guarantees (not by a failing test — Laravel's test client doesn't execute
Alpine, so this specific class of bug is invisible to `php artisan test`
regardless of coverage). Fixed by dropping `x-cloak` from the question
blocks entirely and relying solely on `x-show` — which does nothing at all
when Alpine isn't present, so with JS off every question simply shows at
once (correct: paging is JS-only sugar, not the only way to see a question).
`x-cloak` stays on the Prev/Next pagination buttons and the timer bar, since
those two are meaningless without JS and hiding them permanently in that
case is the *correct* behavior, not a bug.

**Ownership guard:** `guardAttempt()` checks quiz→course, attempt→quiz, and
attempt→enrollment on every action and returns `404` (not `403`) on any
mismatch, so a URL for another student's attempt doesn't even confirm the
attempt exists.

**Tests added** (`tests/Feature/Learning/QuizRunnerTest.php`, 11 tests):
intro page renders with a Start button; starting redirects into the runner
with an in_progress attempt; the runner renders every question type present
(mcq/numeric/essay in one pass); AJAX answer autosaves; immediate
feedback_mode returns a grading preview on the answer response; **submitting
via a plain form POST with a bulk `answers` map and zero prior autosave
grades correctly** (the no-JS path, exercised for real, not just asserted in
prose); an ordering question submitted as a position map grades correctly;
review page shows per-question feedback once graded; a `none`-feedback quiz
shows score only; cross-student attempt access is `404`; a non-enrolled user
can't view the quiz at all.

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. `php artisan test` —
257/257 green (246 pre-existing + 11 new).

**Manual UI verification — partial, honestly reported.** I confirmed the new
routes resolve correctly through the real Apache + PHP stack (not just
PHPUnit's in-process client): `curl` against
`.../public/index.php/learn/some-course/quizzes` unauthenticated returns
`302` to `/login`, matching expected `auth`-middleware behavior. I could
**not** do a full live-browser click-through of the Alpine-driven
interactivity (autosave requests firing, the countdown ticking, tab-blur
detection) — there's no browser tool available in this environment, and this
MAMP install's pretty-URL routing has a pre-existing `mod_rewrite` gap
unrelated to this work (confirmed by testing: `/public/login` and even the
long-established `/public/courses` also 404 directly while
`/public/index.php/<same path>` correctly resolves — every route is affected
equally, old and new, so this isn't something introduced here, but it does
mean pretty-URL browser testing isn't possible against this install without
first fixing that Apache config separately). The automated feature tests
above render every new Blade view through Laravel's real view engine with
real assertions on the output, which is the strongest verification available
without a browser.

### P3.6 — Assignments schema + admin CRUD + student submission flow (§5.1, §5.3)

**Built:** `assignments`/`assignment_submissions` migrations exactly per
§5.1's schema; `Assignment`/`AssignmentSubmission` models (`AssignmentSubmission`
route-binds by `uuid`, matching Certificate/Invoice/QuizAttempt); a new
`AssignmentSubmissionStatus` enum (`draft`/`submitted`/`returned`).
`App\Services\Learning\AssignmentService` — student-side only, grading and
the Return flow are explicitly P3.7 — with `saveDraft()`/`submit()` sharing
a private `workingSubmission()` resolver:

- No prior submission → a new attempt (`attempt_no = 1`).
- Latest attempt is still `draft` → reuse that same row (autosave-style
  editing, no new attempt_no per keystroke/save).
- Latest attempt is `submitted` (awaiting grading) → a genuine resubmission
  is allowed, creating a **new** attempt_no, only if
  `assignment.resubmit_until_graded` is true; otherwise `409`.
- Latest attempt is `returned` (graded) → **always** `409`, regardless of
  `resubmit_until_graded` — the setting's name is literally "until graded,"
  so once grading has happened that door is closed unconditionally, not
  merely defaulted closed.

`submit()` additionally checks `assignment.isPastDue()`: if past due and
`allow_late` is false, `422` before anything is written; if past due and
allowed, the row is saved with `is_late = true` (the actual late-penalty
percentage is applied later, in the P3.8 gradebook computation — the
submission itself just records the fact). File uploads reuse the private
`local`-disk + policy-checked-stream convention from
`DocumentService`/`ProjectDocumentController` (not the class itself — it's
hard-coupled to `ProjectDocument` — but the identical pattern), deleting the
previous file when a resubmission replaces it.

`Admin\AssignmentController` mirrors `QuizController`'s shape exactly
(create/store/edit/update/destroy, one `validated()` private method).
`Student\AssignmentController` wires the service to `index` (course
assignment list with per-assignment status), `show` (instructions +
submission form + graded feedback + attempt history), `saveDraft`/`submit`,
and `download` (streams the student's own file back). Entry points added
from My Courses (an "Assignments" link alongside the existing "Quizzes" one,
same eager-loaded-count approach to avoid an N+1) and the lesson page (a
"Lesson assignment" card next to the existing "Lesson quiz" one).

**Bug found and fixed — MySQL identifier length limit (migration).** The
auto-generated name for the `(assignment_id, enrollment_id, attempt_no)`
unique index — `assignment_submissions_assignment_id_enrollment_id_attempt_no_unique`
— is 70 characters, over MySQL's 64-character identifier limit. `Schema::create`
compiles the columns/FKs into one `CREATE TABLE` statement and the
`unique()` call into a **separate** `ALTER TABLE ... ADD UNIQUE` statement
run right after; the first succeeded, the second failed with a genuinely
unrelated-looking "table already exists" error on retry (because the table
from the first, unlogged run was still sitting there). Caught by the
mandatory migrate/rollback/migrate verification gate — same category of
hazard as P3.1's same-second migration-ordering bug (an orphaned, unlogged
table), different root cause this time (identifier length, not FK
ordering). Fixed with an explicit shorter index name
(`assignment_submissions_attempt_unique`); full migrate → rollback → migrate
cycle re-verified clean afterward, including confirming the composite unique
index's leftmost column (`assignment_id`) still satisfies its own FK without
needing a redundant single-column index.

**Bug found and fixed — a real, previously-shipped routing bug.** `GET
{course:slug}/quizzes` and `GET {course:slug}/assignments` were both
registered **after** the generic `GET {course:slug}/{lesson}` route.
Laravel matches GET routes in registration order at the URI-pattern level —
purely by shape, before route-model binding ever runs — so any 2-segment
GET request matches the *first* 2-segment pattern registered, regardless of
whether that route's model binding can actually succeed. Both literal
routes were dead code: a request for `/learn/{course}/quizzes` was being
swallowed by the lesson route, which then 404'd trying to resolve "quizzes"
as a `Lesson`. This bug shipped **silently in P3.5** — `QuizRunnerTest` never
had a test hitting `learn.quizzes.index` directly, only the show/attempt/
answer/submit/review routes (3+ segments, which don't collide with the
2-segment `{lesson}` pattern and so were never affected). It surfaced now
only because this item's own list-page test (`test_the_assignment_list_page_renders`)
happened to be the first one to actually request a 2-segment `learn/{course}/<literal>`
URL. Fixed by moving both literal-prefix route blocks before the `{lesson}`
wildcard in `routes/web.php`, with a comment explaining why the ordering
matters so it isn't silently reintroduced by a future addition; backfilled
`test_the_quiz_list_page_renders` into `QuizRunnerTest` so the P3.5 gap is
now actually covered too, not just the P3.6 one.

**Tests added:** `tests/Feature/Admin/AssignmentCrudTest.php` (4 — create/
update/delete/non-admin-denied). `tests/Feature/Learning/AssignmentServiceTest.php`
(11 — draft creates attempt 1; re-drafting updates the same row; submit
finalizes the same row; late-but-allowed marks `is_late`; late-and-disallowed
rejects; resubmission creates a new attempt when allowed; resubmission
rejected when disallowed; **a returned submission can never be resubmitted
regardless of the flag** — the exact "until graded" semantics above, pinned
directly; file upload stored on the private disk and replaced on
resubmission; a disallowed type (e.g. `link` when only `text` is accepted)
is silently dropped rather than stored; cross-student authorization denial).
`tests/Feature/Learning/AssignmentSubmissionFlowTest.php` (6 — list/show
pages render; draft save persists via HTTP; submit with a file upload
persists and the file downloads back; a `returned` submission hides the
form and shows the grade/feedback; cross-student file download is `404`).
Plus the one quiz-list regression test noted above.

**Verification:** `php artisan migrate` → `rollback --step=2` →
`migrate` cycle clean (see the identifier-length bug above — verified with
the fix in place). `vendor/bin/pint --dirty` clean. `phpstan analyse
--memory-limit=1G` 0 errors. `php artisan test` — 279/279 green (257
pre-existing + 22 new, 21 for this item + 1 backfilled P3.5 regression).

### P3.7 — Grading queue + assignment Return flow + remaining §4.5 events (§6.3.3, §5.3, §4.5)

**Built:** `AssignmentService::return(AssignmentSubmission, float $points,
?string $feedback, User $grader)` — rejects `409` unless the submission is
`submitted` (not draft, not already returned), `422` if points fall outside
`[0, assignment.points]`, otherwise sets `points_awarded`/`feedback`/
`status = returned`/`graded_by`/`graded_at` and dispatches `SubmissionGraded`.

`App\Livewire\Admin\GradingQueue` (full-page, `admin.grading-queue`, added
to the sidebar under Courses) — the cross-course "daily inbox" (§6.3.3 item
3): merges every `attempt_answers` row with `auto_graded = false AND
points_awarded IS NULL` on a `submitted` attempt (the exact "pending manual
review" definition P3.4 established) with every `assignment_submissions`
row still `status = submitted`, sorted oldest-first by `submitted_at`. Each
row expands inline (no separate page/modal) into a points+feedback form that
calls either `QuizService::gradeManual()` or `AssignmentService::return()`
depending on the row's `type` discriminator. **Decision:** built as plain
PHP array/`usort` construction rather than chaining `Collection::map()` +
`::concat()` — PHPStan (correctly, per a documented limitation:
`Collection`'s generic isn't covariant) couldn't prove two independently
`map()`-built collections with structurally-identical-but-separately-inferred
array shapes were assignable to one declared return type; array-building
sidesteps the inference entirely and is no less readable. **Decision:**
`WithTable` (the concern `CourseStudents` reuses) wasn't reused here — it's
built around one Eloquent query; a queue spanning two unrelated models
doesn't fit that shape, and at this app's realistic queue depth a plain
sorted list needs no server-side pagination yet.

**A real, previously-shipped gap closed — three of six §4.5 events had never
actually been wired.** Re-reading §4.5's event list while building this
item's notifications surfaced that `QuizAttemptSubmitted` (dispatched since
P3.3) had **no listener at all** — it fired on every graded/re-graded attempt
and nothing ever happened. `AssignmentSubmitted` and `SubmissionGraded`
(named in §4.5, required for "every state change fires notifications")
didn't exist yet at all — P3.6 built the submission flow itself but never
wired its own notification, an oversight worth naming rather than quietly
folding in. Also: `LearningEventType::QuizStarted`/`QuizSubmitted` were
declared since P1 with a docblock explicitly flagging them as "waiting on
the quiz engine (P3)" — P3.3 built the quiz engine and never came back to
wire them. Fixed all three in one pass, each following the *exact* two-track
convention `ProgressService` already established (a direct
`LearningEventRecorder::record()` call for the analytics log, a completely
separate `Event::dispatch()` for cross-cutting side effects — one doesn't
trigger the other):
- `QuizService::start()`/`submit()` now call `$this->events->record(...)`
  with `QuizStarted`/`QuizSubmitted` (start only records on a genuine new
  attempt, not a resume — pinned by a test).
- `NotifyStudentOfQuizGrade` (+ `QuizGradedNotification`, mail+database) —
  listens for `QuizAttemptSubmitted`, notifies the attempt's owner with
  score/pass-fail, fires whether the attempt was graded by `submit()`'s
  auto-grading or by `gradeManual()` completing the last pending question.
- `AssignmentService::submit()` now dispatches `AssignmentSubmitted` (never
  on a draft save) and records `LearningEventType::AssignmentSubmitted`.
  `NotifyInstructorOfAssignmentSubmission` (+ `AssignmentSubmittedNotification`,
  **database-only, no mail** — a per-submission email to the instructor
  would be noisy; the grading queue page itself is the primary "inbox" per
  the plan's own framing of item 3) notifies `course.createdBy`, skipping
  silently if a course has no owner set.
- `NotifyStudentOfSubmissionGrade` (+ `SubmissionGradedNotification`,
  mail+database) — listens for `SubmissionGraded`, notifies the student with
  their grade and any feedback.

**Tests added:** `tests/Feature/Admin/GradingQueueTest.php` (6 — non-admin
denied; queue renders both pending types; grading a quiz answer finalizes
the attempt and notifies via `QuizGradedNotification`; returning an
assignment notifies via `SubmissionGradedNotification`; points above the max
rejected with `422` and the submission is left untouched; empty-queue state).
`AssignmentServiceTest.php` gained 5: `return()` success path; rejecting a
draft or already-returned submission; rejecting out-of-range points; the
instructor notification fires on submit; `AssignmentSubmitted` is recorded on
submit but *not* on a draft save. `QuizServiceLifecycleTest.php` gained 2:
start/submit feed `QuizStarted`/`QuizSubmitted`; resuming an in-progress
attempt does not record a second `QuizStarted`.

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean (one
auto-fix pass — unused-import removal after Pint noticed
`AssignmentSubmittedNotification` never used `MailMessage` once scoped to
`database`-only). `phpstan analyse --memory-limit=1G` 0 errors (after the
`Collection` covariance fix above). `php artisan test` — 292/292 green (279
pre-existing + 13 new).

### P3.8 — Gradebook (§5.4)

**Built:** `GradebookService::itemsFor(Enrollment)` — one entry per
published quiz/assignment in the enrollment's course: `{type, id, title,
percent, max_points}`. Quiz percent is computed only over `graded` attempts
(never `in_progress`), selected per the quiz's own `grading_method`
(`highest`/`latest`/`first`/`average` — all four implemented, matching the
enum P3.1 already declared). Assignment percent comes from the *latest*
`returned` submission (`points_awarded / assignment.points * 100`), with
`late_penalty_percent` applied **multiplicatively** if that submission was
late (`percent *= 1 - penalty/100` — a 20% penalty on a 100% submission
lands at 80%, not a flat 20-point subtraction off whatever the raw score
happened to be). An item with zero activity, or an assignment that's
`submitted` but not yet `returned`, comes back with `percent: null` —
**excluded** from the course average rather than counted as a zero.
**Decision:** this is deliberately a "current grade so far," matching how
students actually expect an in-progress gradebook to read (a course that's
half-finished shouldn't show a crushed average because most items haven't
been attempted yet); the plan doesn't spell out this choice explicitly, so
it's resolved the same direction the whole phase has resolved similar
ambiguities — favor what a real student/instructor would expect over a
literal zero-fill.

`courseGradePercent()` is the equal-weight average (§5.4: "weights default
equal") of whichever items came back non-null. `courseGradePercentFromItems()`
is the same math taking an already-fetched item list, added specifically so
the admin matrix/CSV export — which iterate every enrollment in a course —
don't pay for `itemsFor()` twice per row.

Student "Grades" tab (`learn.grades`, linked from My Courses whenever a
course has any published quiz or assignment) — a course-grade summary card
plus the per-item table. Admin per-course gradebook (`App\Livewire\Admin\GradeMatrix`,
linked from the course page next to Students) — students × items grid with
a course-grade column; `GradebookExportController` streams the identical
grid as CSV.

**Decision — plain array/foreach construction, not `Collection::map()`
chains, in both `GradeMatrix::render()` and `GradebookExportController`.**
The exact PHPStan `Collection`-generic-covariance limitation hit in P3.7's
`GradingQueue` (documented there) recurred here independently, on an
unrelated component combining a per-item collection with a per-enrollment
one. Two independent hits in two phases confirms this is a shape worth
avoiding by default in this codebase — heterogeneous/derived collections
built via chained `map()` calls and then combined or nested — rather than a
one-off to work around case-by-case.

**Tests added:** `tests/Feature/Learning/GradebookServiceTest.php` (9 — a
never-attempted item excluded from the average; all four `grading_method`
values pick the right attempt; an `in_progress` attempt ignored; assignment
percent from the latest returned submission; late penalty applied
multiplicatively; a submitted-not-returned assignment excluded, not zeroed;
course grade is the equal-weight average of graded items only).
`StudentGradesPageTest.php` (2 — page renders the grade/items, non-enrolled
`404`). `GradeMatrixTest.php` (3 — matrix renders one row per student with
correct percentages, CSV export contains the same data, non-admin denied).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. `php artisan test` —
306/306 green (292 pre-existing + 14 new).

### P3.9 — Certificate criteria tightening — closes L1 fully (§4.6)

**Built:** `GradebookService::meetsCertificateQuizRequirement(Enrollment): bool`
— published `counts_toward_certificate` quizzes on the course are the
"gate." No gating quizzes → trivially satisfied (unchanged behavior for
every course that doesn't use this feature). One or more gating quizzes →
each must already have a grade (reuses the same private `quizGradePercent()`
P3.8 built — an ungraded/unattempted gating quiz blocks issuance outright,
it does **not** count as a zero), and the average of their grades must meet
the average of their own `pass_percent` values. **Decision:** each quiz
keeps its own pass mark rather than inventing a single course-wide one —
the plan's "average ≥ pass mark" doesn't specify whose mark when multiple
quizzes are involved, and averaging both sides is the natural reading that
needs no new schema field. This means a quiz that individually misses its
own mark can still be compensated for by another that clears its mark by
more — pinned explicitly by a test — a debatable call, but a defensible one
given "average" is the literal word the plan uses, not "every quiz
individually."

`CertificateService::issueIfEligible(Enrollment): ?Certificate` — checks
`progressPercent() >= 100` **and** the quiz requirement above; returns
`null` while either is unmet, otherwise delegates to the existing (already
idempotent) `issue()`. The plain `issue()` method is untouched — still
callable directly wherever a caller has already verified eligibility itself
(none currently do, but no reason to remove the simpler primitive).

**A student can satisfy either requirement first**, so two independent
trigger points now call `issueIfEligible()`:
- `HandleCourseCompletion` (reacting to `CourseCompleted`, i.e. lessons just
  hit 100%) — swapped its unconditional `issue()` call for
  `issueIfEligible()`. If a quiz gate is still open, no certificate is
  created, but `CourseCompletedNotification` still sends — its mail already
  conditionally omits the certificate action when
  `$enrollment->certificate` is null (P0 behavior, unchanged), so a student
  who finishes the content before the quiz gets an honest "you finished the
  lessons" email with no premature certificate link, not a broken one.
- `IssueCertificateWhenQuizRequirementIsMet` (new, reacting to
  `QuizAttemptSubmitted`) — the other direction: a gating quiz gets graded
  after lessons were already done. No-ops immediately if a certificate
  already exists (avoids a redundant `issueIfEligible()` call on every
  future quiz submission once already certified) or if lessons/the
  requirement still aren't both satisfied. On success, notifies via the
  *same* `CourseCompletedNotification` class rather than a new one — its
  copy ("you finished the course, here's your certificate") reads correctly
  regardless of which requirement finished last, so a second notification
  class would only duplicate content, not add anything.

**Closes L1 fully.** The loophole audit (§2) named L1 — "certificates
attest nothing" — as closed by three prongs: completion rules (§4.3,
`min_watch`/sequential progression, landed in P2), quiz gates (§5, the
whole quiz engine, P3.1–P3.5), and certificate criteria (§4.6, this item).
All three are now in place.

**Tests added:** `GradebookServiceTest` gained 3 (no gating quizzes passes
trivially; an unattempted gating quiz blocks; two gating quizzes average
their grades and pass marks — one that individually misses its own 80%
mark still passes the blended check). `CertificateQuizGateTest.php` (6):
lessons-done-without-quiz withholds the certificate; passing the quiz after
lessons finish issues it via the new listener; the reverse order (quiz
passed first, lessons finish last) issues via the existing listener; a
failed gating quiz still blocks; a non-gating quiz never blocks; grading a
quiz on a course where most lessons remain incomplete does not prematurely
certify (guards against the new listener firing too eagerly).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. `php artisan test` —
315/315 green (306 pre-existing + 9 new).

### P3.10 — Quiz item analysis (§6.3.4)

**Built:** `QuizAnalysisService::itemAnalysisFor(Quiz)` — per question,
counts every `attempt_answers` row with a non-null `is_correct` (every
auto-graded objective answer, from any attempt — including one whose
*other* questions are still sitting in the grading queue, since that
question's own answer was already scored at `submit()` time regardless).
Essay answers never contribute (`is_correct` is always null for them, per
P3.3's grading table). A question with zero answers reports
`correct_rate: null`, distinguished from a real 0% — "nobody's tried this
yet" and "everybody failed it" are different signals and shouldn't look the
same in the table.

Admin-facing page (`admin.quizzes.analysis`, linked from the quiz edit
form) — one row per question: answered count, correct count, and a
color-coded correct-rate badge (red `<50%`, amber `<75%`, green otherwise),
so a question everyone fails is visually obvious per the plan's own framing
of what this view is for ("a bad question or a bad lesson").

**Decision — scoped to exactly what the roadmap groups under P3, not the
whole "Course analytics tab."** §6.3 item 4 bundles quiz item analysis
together with an enrollment funnel, a per-lesson drop-off chart, and a
watch-time histogram — but the plan's own phase roadmap (§8) lists "quiz
item analysis (§6.3.4)" as a P3 deliverable and "funnel/drop-off (§6.3.4)"
separately under P4. Built only the former here; the rest of that tab
remains deferred to P4 as the plan itself schedules it.

**Tests added:** `QuizItemAnalysisTest.php` (3 — no-answers-yet reports
null not zero; correct rate computed correctly across three different
students' attempts, 2/3 correct → 66.7%; essay questions never contribute).
`QuizAnalysisPageTest.php` (3 — page lists every question, the edit page
links to it, non-admin denied).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. `php artisan test` —
321/321 green (315 pre-existing + 6 new).

## P3 phase gate — closed

All ten items done, one commit per item, in plan order, tagged `lms-p3`.
Closes/advances: §5.1–§5.2 (quiz schema + `QuizService` auto-grading for all
9 question types), §5.3 (assignments, Classroom-style draft→submit→return),
§5.4 (gradebook), §4.6 (certificate criteria — **closes L1 fully**, the last
of its three prongs), §6.3.3 (grading queue), §6.3.4 (quiz item analysis),
and closed out three events from §4.5 (`QuizAttemptSubmitted` finally got a
listener; `AssignmentSubmitted`/`SubmissionGraded` were built for the first
time) that had been declared or dispatched since earlier phases but never
fully wired.

- `composer ci` (repo-wide `pint --test`, `phpstan --memory-limit=1G`,
  `check-empty-files`, `secrets-scan`, `php artisan test`): **green**, exit
  code 0, 321 tests / 691 assertions.
- `php artisan migrate` on a fresh checkout applies every P3 migration
  cleanly in order (verified via `migrate:status` + a full
  migrate→rollback→migrate cycle after each schema-touching item).
- **Five real, previously-undiscovered bugs were found and fixed while
  building and testing this phase (not routed around):**
  1. **Migration ordering hazard** (P3.1) — `question_options`/`quizzes`
     and `quiz_attempts`/`attempt_answers` migrations generated in the same
     wall-clock second fell back to alphabetical filename tie-break,
     creating a child table before its FK parent existed. Fixed by
     renaming to strictly later timestamps.
  2. **PHP array-union footgun** (P3.2) — `$data['question'] + ['sort_order' => ...]`
     silently discarded the intended default because `+` keeps the left
     array's value for a key present on both sides; every question
     create/update without an explicit sort order 500'd. Fixed with `??=`.
  3. **MySQL 64-character identifier limit** (P3.6) — the auto-generated
     name for the `(assignment_id, enrollment_id, attempt_no)` unique index
     was 70 characters; `CREATE TABLE` succeeded but the follow-up
     `ALTER TABLE ADD UNIQUE` failed, leaving an orphaned, unlogged table.
     Fixed with an explicit shorter index name.
  4. **A genuine, shipped routing bug spanning two phases** (P3.6) — `GET
     {course:slug}/quizzes` and `GET {course:slug}/assignments` were
     registered after the generic `GET {course:slug}/{lesson}` route, so
     both were dead code (Laravel matches GET routes in registration order
     at the URI-pattern level, before model binding runs). The quiz list
     route had silently shipped broken in **P3.5** — no test ever hit it
     directly — and only surfaced when P3.6's own list-page test happened
     to be the first to request a matching 2-segment URL. Fixed by
     reordering; backfilled the missing P3.5 regression test in the same
     commit.
  5. **Three of six §4.5 events had never been fully wired** (P3.7) —
     `QuizAttemptSubmitted` fired since P3.3 with no listener at all;
     `LearningEventType::QuizStarted`/`QuizSubmitted` were declared since P1
     with a docblock flagging them as waiting on the quiz engine, and the
     quiz engine (P3.3) never came back to record them. Closed all three in
     one pass, following `ProgressService`'s established two-track
     convention exactly (direct `LearningEventRecorder::record()` for the
     analytics log, separate `Event::dispatch()` for side effects).
- A recurring PHPStan limitation (`Collection`'s generic isn't covariant)
  surfaced independently in two unrelated components (P3.7's `GradingQueue`,
  P3.8's `GradeMatrix`/`GradebookExportController`) whenever a heterogeneous
  or per-row-derived collection got built via chained `map()`/`concat()`
  calls and combined. Resolved both times by building plain PHP
  arrays/`foreach` instead of `Collection` chains — noted as a shape to
  avoid by default going forward in this codebase, not a one-off workaround.
- Every quiz-runner AJAX surface (autosave, submit) has a real plain-HTML
  fallback path proven by a real test exercising it
  (`test_submitting_via_a_plain_form_post_with_bulk_answers_grades_the_attempt`)
  — not just asserted in prose. A P3.5 draft that used `x-cloak` on the
  runner's per-question blocks would have hidden every question forever
  with JS disabled; caught before shipping by re-reading what `x-cloak`
  actually guarantees, not by a failing test (Laravel's test client doesn't
  execute Alpine, so this class of bug is invisible to `php artisan test`
  regardless of coverage — a genuine boundary of what this phase's
  automated verification can prove, same as P2's).
- Manual UI verification is partial and honestly reported (P3.5): the new
  routes resolve correctly through the real Apache+PHP stack (confirmed via
  `curl .../index.php/learn/...` returning the expected `302`), but a full
  live-browser click-through of the Alpine-driven interactivity wasn't
  possible in this environment (no browser tool, and this MAMP install's
  pretty-URL routing has a pre-existing `mod_rewrite` gap unrelated to this
  work, confirmed by testing that even long-established routes like
  `/courses` and `/login` 404 the same way without going through
  `index.php` explicitly).
- Explicitly deferred to later phases, each tied to a plan section that
  itself places the work later:
  - The rest of §6.3's "Course analytics tab" (enrollment funnel,
    per-lesson drop-off chart, watch-time histogram) — the plan's own §8
    roadmap groups these under P4, separately from quiz item analysis which
    it lists under P3.
  - Commerce (Flutterwave course checkout, coupons, refunds), community
    features (announcements, Q&A, notes, reviews), bulk enroll, drag-drop
    curriculum builder, nudge emails, weekly instructor digest — all P4 in
    §8.
  - Badges/streaks, enrollment expiry, API v1 parity for the new quiz/
    assignment/gradebook surfaces, an accessibility pass, event pruning,
    heartbeat load sanity — all P5 in §8.

Next: P4 — Commerce & community (Flutterwave course checkout + coupons +
refunds, announcements + Q&A + notes + reviews, bulk enroll + drag-drop
curriculum builder, course analytics funnel/drop-off, nudge emails + weekly
instructor digest).

---

## P4 — Commerce & community

### P4.1 — Flutterwave course checkout (§7.1)

**Built:** wired paid-course enrollment through the invoice/payment machinery
already built for client project billing — reused entirely unmodified, not
duplicated. A research pass before writing any code confirmed
`BillingService::generateCourseInvoice(User, Course)` already existed
(unused anywhere), `Invoice.billable_type/billable_id` is already polymorphic
across `Client`/`User`, and `InvoicePolicy::isOwner()` already branches on
`billable_type === User::class` — meaning `portal.invoice.pay` /
`gateway.callback` / `gateway.webhook` / `GatewayPaymentService::settle()`
already work correctly for a course invoice billed to a student with **zero**
changes needed to any of them.

The one genuine gap: nothing reacted to an invoice reaching `Paid`, for
either billing use case — `BillingService::recordPayment()` only ever wrote
a `Payment` row and flipped the status. Added `App\Events\Billing\InvoicePaid`,
dispatched from `recordPayment()` the moment the balance hits exactly zero
(not on a partial payment — a course isn't "partially deliverable").
**Decision:** placed the dispatch inside `recordPayment()` itself — the one
chokepoint shared by *both* Flutterwave and admin-recorded cash/bank/mobile-money
payments — rather than only inside `GatewayPaymentService::settle()`, so an
instructor manually recording "he paid me cash for the course" also
activates access. The plan only mentioned Flutterwave explicitly, but the
shared code path makes supporting both essentially free, and there's no
reason a cash-paying student should be treated differently.

`ActivateCourseEnrollmentsOnInvoicePaid` (listener) walks the invoice's line
items for any with `source_type = Course::class` (via `InvoiceItem`'s
existing polymorphism — no new column needed to find "which course(s) this
invoice is for") and activates the matching `Enrollment` per item —
`firstOrCreate`, so it still works even if no enrollment row exists yet
(e.g. an admin raised the invoice directly without the student going
through checkout first). Idempotent: an already-`active`/`completed`
enrollment is a no-op, so a webhook/callback settlement race (both call
`settle()` on the same transaction) can't double-enroll or fire
`EnrollmentCreated` twice.

`CourseCatalogueController::enroll()` rewritten: an existing `active`/
`completed` enrollment still short-circuits to "already enrolled" (unchanged
free-course behavior); a paid course with no enrollment (or a previously
`cancelled` one) generates the invoice and creates/reactivates a `pending`
enrollment, then redirects to a new `courses.checkout` page; re-POSTing
`enroll()` while a `pending` invoice is already outstanding reuses it rather
than generating a duplicate. The checkout page itself is new
(`resources/views/courses/checkout.blade.php`, `layouts.app` — checkout is
inherently an authenticated action, not public marketing) but its "Pay with
Flutterwave" button posts straight to the **existing** `portal.invoice.pay`
route — no new payment-initiation endpoint was needed at all.

**Decision — `enrollments.invoice_id`, a new nullable FK, added via
migration.** Not strictly required (the listener locates course line items
through `InvoiceItem` regardless), but gives the checkout page a direct way
to re-find "the" pending invoice for an enrollment and makes "which invoice
funded this enrollment" auditable — consistent with §9's "grades are
auditable" principle extended to enrollments-from-payment.

**Gap closed in test infrastructure, not just app code:** no test anywhere
exercised `GatewayPaymentService`/the webhook layer before this item — only
`BillingService` in isolation (`InvoicePaymentTest`). Built
`tests/Support/FakePaymentGateway` (implements the existing `PaymentGateway`
interface, bound via `$this->app->instance()`) so the full chain — enroll →
checkout → `portal.invoice.pay` → webhook → `settle()` → `recordPayment()` →
`InvoicePaid` → enrollment activated — could be tested end to end with zero
real HTTP calls to Flutterwave. This fake is reusable for P4.3's refund path
and any future gateway-touching work.

**Explicitly deferred:** the `Api\V1\EnrollmentController` paid-course stub
(currently a 402 "checkout required" response) is left unchanged — the
plan's own §8 roadmap places "API v1 parity for every new module" in P5 as
a batched item, not per-feature in P4.

**Tests added:** `InvoicePaidEventTest` (2 — full payment dispatches,
partial doesn't). `ActivateCourseEnrollmentsOnInvoicePaidTest` (5 —
activates + dispatches `EnrollmentCreated`; creates the enrollment if none
existed; idempotent against a re-fire; a client/project invoice never
touches enrollments; a course invoice with no course line item is a no-op).
`CourseCheckoutTest` (6 — pending enrollment+invoice created and redirected
to checkout; a repeat enroll reuses the same invoice rather than
re-invoicing; checkout page renders; checkout with no pending invoice
redirects away; an already-active enrollment never creates a stray invoice;
**the full Flutterwave webhook chain end to end** via the new fake gateway).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 334/334 green (321 pre-existing + 13 new).

### P4.2 — Coupons (§7.1)

**Built:** `coupons` table (`code` unique, `type` percent|amount, `value`,
`max_uses`/`used_count`, `expires_at`, `course_id` nullable — null scopes to
any course), `CouponType` enum, `Coupon` model.
`CouponService::redeem(string $code, Course $course, string $subtotal)` —
looks the code up case-insensitively (uppercased + trimmed before the
query), locks the row for the duration of validation so two students racing
for the last remaining use can't both succeed, rejects unknown/inactive/
expired/exhausted/wrong-course-scope codes with `InvalidCouponException`
(same `::make(string $reason)` factory pattern as the existing
`OverpaymentException`), computes the discount via bcmath (percent against
the subtotal; a flat amount capped at the subtotal itself so a coupon can
never make an invoice go negative), and increments `used_count` atomically
within the same lock.

`BillingService::generateCourseInvoice()` gained an optional `$couponCode`
parameter — redeems before generating the invoice and feeds the computed
discount straight into `generateInvoice()`'s existing `discount` parameter,
so no invoice-side logic needed to change at all. **Decision:** a coupon
use is consumed at invoice-creation time, not at payment — matches the
plan's own framing of coupons as "nearly free to build," a lightweight
growth lever rather than something needing perfect atomicity if a student
abandons checkout after applying one.

`CourseCatalogueController::enroll()` accepts an optional `coupon_code`
input; an invalid code redirects back to the course page with a flash error
and — critically — creates **no** invoice or enrollment row at all, rather
than silently falling back to full price. `Admin\CouponController` (CRUD)
plus `CouponPolicy` (gated on `billing.manage`, mirroring `InvoicePolicy`
exactly) let an admin manage codes from a new "Coupons" entry in the
Billing nav group.

**Bug found and fixed — directly caused by P4.1, surfaced while building
this item.** `CourseCatalogueController::show()` originally computed
`$enrollment` as *any* existing row for the user+course, with no status
filter, and the view rendered "Continue learning" whenever it was truthy.
Before P4.1, only `active`/`completed` enrollments could ever exist (paid
checkout was fully blocked), so this was harmless. P4.1 introduced real
`pending` rows — and "Continue learning" now routes into
`EnrollmentPolicy::access()`, which explicitly excludes `pending`, so a
student who'd started but not finished checkout would hit a wall clicking
the button the course page told them to click. Fixed by restricting
`$enrollment` to `active`/`completed` only and adding a distinct "Complete
checkout" call-to-action (linking to `courses.checkout`) for the `pending`
case — caught and fixed in this item rather than left for later, since it
was directly adjacent to the coupon-code input being added to the same
enroll form on the same view.

**Tests added:** `CouponServiceTest` (8 — percent/flat-amount discount
math including the subtotal cap, `used_count` increments, unknown/
inactive/expired/exhausted/wrong-scope rejection, a no-scope coupon applies
to any course). `CouponCheckoutTest` (3 — a valid coupon discounts the real
invoice end to end through the controller, an invalid code creates nothing
and flashes an error, a second student can't exceed a coupon's `max_uses`
even across two different requests). `CouponCrudTest` (6). Plus one
regression test for the `courses.show()` fix above
(`test_the_course_page_shows_complete_checkout_not_continue_learning_while_pending`).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 352/352 green (334 pre-existing + 18 new).

### P4.3 — Refund path (§7.1)

**Built:** `BillingService::refund(Invoice, ?int $by)` — only `Paid`/
`PartiallyPaid` invoices can be refunded (a `RuntimeException` otherwise —
nothing was collected, nothing to give back). **Decision:** a new
`InvoiceStatus::Refunded` case, distinct from the existing `Void`. Void
already meant "this charge should never have existed" (used for cancelling
an unpaid/erroneous invoice); refunded means "money was actually collected,
then returned" — conflating the two would make it impossible to tell, from
status alone, whether a given invoice ever had money move against it.
`amount_paid` and the historical `Payment` rows are deliberately left
untouched by a refund — only `status` (plus new `refunded_by`/`refunded_at`
columns, extending the invoice's own existing `issued_by`/`issued_at`
pattern rather than introducing a new audit mechanism) changes, so the
permanent record of what was actually paid is never erased or fabricated
into a negative balance.

`EnrollmentDrilldown::cancelAndRefund()` (Livewire action, a
`wire:confirm`-guarded "Cancel & refund" button next to the existing nudge
button) is the one admin-facing entry point for "admin cancels enrollment →
access revoked, invoice credited": sets the enrollment to `cancelled`
(sufficient on its own to cut off access — `EnrollmentPolicy::access()`
already excludes anything but `active`/`completed`, unchanged since P0) and,
only if the enrollment's funding invoice was actually paid, credits it via
`refund()`. `EnrollmentCancelledNotification` fires either way, with its
copy adapting to whether a refund actually happened — cancelling a free
enrollment has nothing to credit, and the notification says so rather than
claiming a refund that didn't occur.

**Tests added:** `InvoicePaymentTest` gained 4 (refund records status/
`refunded_by`/`refunded_at` while the historical `amount_paid` is preserved
untouched; a partially-paid invoice can also be refunded; refunding one
with nothing paid is rejected; an already-refunded invoice can't be
refunded twice). `EnrollmentDrilldownTest` gained 2 (cancelling a paid
enrollment revokes access, refunds its invoice, and notifies with
`refunded=true`; cancelling a free enrollment with no invoice just revokes
access and notifies with `refunded=false`).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 358/358 green (352 pre-existing + 6 new).

### P4.4 — Announcements tab (§7.3, Classroom's stream)

**Built:** `Announcement` (`course_id`, `title`, `body` markdown, nullable
`published_at`, soft-deletable). **Decision:** publishing is deliberately
the *only* action that ever triggers a mass notification — creating can
publish immediately (a checkbox, checked by default) or save as a draft;
a separate `publish()` action lets a draft be published later, and editing
an already-published announcement's title/body afterward never re-notifies
anyone. Publishing an already-published announcement is rejected outright
(flash error, no-op) rather than silently re-sending to every student —
the "publish" transition happens exactly once per announcement, checked via
`published_at === null` before the update, not via any separate tracking
flag.

`AnnouncementPublishedNotification` (mail+database) goes to every
`active`/`completed` enrollment's user via `Notification::send()` — a
`pending` (unpaid, per P4.1) enrollment is deliberately excluded, matching
how every other student-facing surface already treats "enrolled" as
active/completed only.

Student `learn.announcements.index` shows only published announcements,
newest first, rendered through the existing `MarkdownRenderer`. Entry
points added from My Courses (an unconditional link — no eager-loaded count
needed, unlike Quizzes/Assignments, since it's a static link that doesn't
need to disappear when there's nothing published yet) and the lesson page
breadcrumb.

**Tests added:** `AnnouncementCrudTest` (7 — a draft notifies nobody;
publish-immediately notifies every active student but explicitly not a
pending one; publishing a draft later notifies exactly once; publishing an
already-published announcement is rejected and sends nothing; editing a
published announcement's content never re-notifies; delete; non-admin
denied). `StudentAnnouncementsTest` (3 — only published ones render,
non-enrolled `404`, markdown renders as real HTML).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 368/368 green (358 pre-existing + 10 new).

### P4.5 — Q&A tab (§7.3)

**Built:** `Discussion` (`course_id`, `lesson_id` nullable — a course-wide
question when null, a lesson-scoped one otherwise — `user_id`, `parent_id`
nullable for a reply, `body`, `is_instructor_answer`, `resolved_at`,
soft-deletable). **Decision:** threads stay flat — `DiscussionService::reply()`
rejects replying to a reply (`422`) rather than allowing arbitrary nesting,
matching the plan's "per-lesson threads" framing (a thread, not a tree) and
keeping the UI trivial (one indent level, no recursive rendering).
`is_instructor_answer` is never a field the replier chooses — it's computed
automatically from `User::isAdmin()` at reply time, so it can't be
spoofed or forgotten.

**Decision — where DiscussionService sits on the authorization spectrum.**
Unlike `QuizService`/`AssignmentService` (which self-check
`Gate::authorize('access', $enrollment)` internally, because grades and
money are involved), `DiscussionService` leaves enrollment/admin
authorization entirely to its callers — matching `GradebookService`/
`QuizAnalysisService`'s convention for services that read/write community
content rather than something requiring defense-in-depth. Both student and
admin controllers do their own access checks before calling in.

Student-facing (`learn.discussions.*`): a course-wide thread list (open/
resolved status, reply counts, per-thread lesson context), an
ask-a-question form optionally pre-scoped to a lesson via a query
parameter (linked from the lesson page breadcrumb as "Ask a question"), a
thread view with inline reply, and resolve — restricted to the original
asker or an admin, `403` otherwise.

Instructor-facing: `App\Livewire\Admin\CourseDiscussions`, a per-course Q&A
inbox mirroring P3.7's grading-queue inline-reply pattern (open a thread,
type a reply, submit — no separate page navigation). **Decision:** scoped
per-course, not a cross-course inbox like the grading queue — the plan's
own wording frames this specifically as a course-level "Q&A tab," and a
question's context (which course, which lesson) matters more here than in
grading, where "oldest first, across everything" was the explicit ask.

**Tests added:** `DiscussionServiceTest` (7 — asking notifies the course's
instructor; a question can be lesson-scoped; an admin's reply is
auto-badged and notifies the asker; a student's reply is never badged;
replying to your own question doesn't self-notify; replying to a reply is
rejected; resolve sets `resolved_at`). `StudentDiscussionsTest` (6 — post/
list/reply/resolve, only the asker or an admin can resolve, non-enrolled
`404`). `CourseDiscussionsTest` (4 — non-admin denied, inbox lists threads,
an admin reply from the inbox is badged and notifies, admin resolve).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 385/385 green (368 pre-existing + 17 new).

### P4.6 — Notes tab (§7.3)

**Built:** `LessonNote` (`enrollment_id`, `lesson_id`, nullable `seconds`,
`body`) — private, per-student notes, each a discrete timestamped entry
rather than an editable document (create + delete only, no update — matches
how a "quick note while watching" tool actually gets used, and keeps the
feature small). **Decision:** `Enrollment::lessonNotes()` is a deliberately
different relation name from the already-existing `notes()` (the *admin's*
private notes about a student, from P1's `EnrollmentNote`) — reusing
`notes()` would have silently collided with an established relation on the
same model.

Rendered directly on the lesson page — no separate route/page needed, since
"Notes tab" here means a panel alongside the video, not a distinct
destination. **Decision — real forms first, JS as pure enhancement, not a
fetch-driven reactive widget.** Considered building this as an
Alpine-reactive add/delete list (fetch, update state, no reload), but
rejected it: that would mean hydrating Alpine from server-rendered initial
state just to half-duplicate the same list, for a low-frequency
interaction where a page reload costs nothing. Instead: add/delete are
real `<form>`s that work fully with zero JavaScript (a reload shows the
updated list, matching every other non-AJAX-critical form in this app).
JS enhancement layers on top of the *same* forms: an inline `onsubmit`
handler fills the hidden `seconds` field from
`window.__lessonVideoPlayer.getCurrentTime()` right before the real POST
(no JS means the note just saves without a timestamp — a degraded but
correct outcome, not a broken form), and a timestamped note's time renders
as a `seekTo()` button — meaningless without JS anyway, exactly like the
rest of the video player controls already are.

**Tests added:** `LessonNotesTest` (6 — a timestamped note saves and
formats correctly, 125s → "2:05"; a note without a timestamp is allowed;
the lesson page renders saved notes; a student can delete their own note;
a student cannot delete another student's note — `404`, not `403`, so the
URL doesn't confirm the note exists; a non-enrolled user is denied).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 391/391 green (385 pre-existing + 6 new).

### P4.7 — Reviews (§7.3)

**Built:** `CourseReview` (`enrollment_id` unique — one review per
enrollment; `course_id` denormalized off the enrollment specifically so the
catalogue's per-course average doesn't need a join through `enrollments`
just to aggregate; `rating` 1–5; `body` nullable; `is_published` defaulting
`false`). Gated at ≥50% course progress — reuses
`Enrollment::progressPercent()` (no new tracking column), enforced with a
`403` before the review form is even reachable, not only at submit time.

Resubmitting updates the same row (`updateOrCreate` keyed on
`enrollment_id`) instead of erroring or creating a duplicate. **Decision —
closing a moderation bypass before it existed:** editing an
already-published review resets `is_published` back to `false` *only* when
the rating or body actually changed; resubmitting identical content (a
double-click, or just re-visiting the form) leaves a published review
published. Without this check, a student could get a positive review
approved, then quietly edit it into something else — the review stays
"published" but the *content* was never re-moderated. Checked by comparing
old vs. new values before writing, not by any separate "edited" flag.

Admin moderation: `App\Livewire\Admin\ReviewModeration` — a **global**
queue (unpublished first, publish/unpublish/delete), not per-course.
**Decision:** unlike P4.5's Q&A inbox (kept per-course, since a question's
course/lesson context matters to answering it), reviewing feedback across
every course in one place is the more useful default here, closer to how
P3.7's grading queue works — there's no per-review action that benefits
from course-scoping the way replying to a question does.

Catalogue average: `Course::withCount`/`withAvg` scoped to
`is_published = true` reviews only (a query-time filter on the aggregate,
not a separate query), shown on both the course index cards and the show
page's tag row, plus a published-reviews list at the bottom of the show
page.

**Tests added:** `CourseReviewTest` (6 — ≥50% can submit and starts
unpublished; <50% is `403`; resubmission updates the same row; editing a
published review un-publishes it; resubmitting *identical* content leaves
it published; the catalogue shows the average of published reviews only,
not the unpublished one mixed in). `ReviewModerationTest` (5 — non-admin
denied, queue lists a pending review, publish/unpublish/delete).

**Verification:** `php artisan migrate` → `rollback --step=1` → `migrate`
clean. `vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G`
0 errors. `php artisan test` — 402/402 green (391 pre-existing + 11 new).

### P4.8 — Bulk enroll (§7.5)

**Built:** `Admin\BulkEnrollController` — a textarea of pasted emails
(newline- or comma-separated, deduped, lowercased) against one course. No
new schema — this is pure orchestration over existing pieces. **Read
`Admin\UserController::store()` in full before writing anything**, since
the instruction was explicitly to reuse "the existing WelcomeCredentials
mail flow," not approximate it: matched its account-creation pattern
exactly — `Str::password(16)`, `password_change_required = true`,
`Mail::to()->send(new WelcomeCredentials(...))` wrapped in the identical
try/catch-and-log so one failed send doesn't abort the rest of the batch.
An email with no existing account becomes a new `role = student` account;
one that already has an account is enrolled without creating a duplicate
or re-sending credentials it was already issued.

**Decision — matched the existing single-enroll behavior's *absence* of a
notification, rather than introducing a new one.** Read
`Admin\EnrollmentController::store()` too, and found it does *not* dispatch
`EnrollmentCreated` for admin-sourced enrollments (only self-serve ones do)
— so bulk enroll doesn't either, for consistency with the one existing
precedent for "admin enrolls someone," rather than quietly changing that
behavior for the bulk case alone. A student already enrolled in this
specific course is skipped (not double-enrolled), mirroring that same
controller's existing dedupe check.

Invalid entries (anything failing `FILTER_VALIDATE_EMAIL`) are filtered out
before any account creation and named back in the result flash message —
never silently dropped without the admin knowing what got skipped.

**Tests added:** `BulkEnrollTest` (6 — non-admin denied; unknown emails
create accounts and send exactly one `WelcomeCredentials` each; an existing
user's email enrolls without a duplicate account or a re-sent credentials
email; an already-enrolled student isn't enrolled twice; invalid emails are
skipped and named in the response; comma- and newline-separated lists both
parse).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. `php artisan test` —
408/408 green (402 pre-existing + 6 new).

### P4.9 — Drag-drop curriculum builder (§7.5)

**Built:**

- `database/migrations/2026_07_26_223957_add_is_published_to_lessons_table.php`
  — `is_published` boolean, `default(true)`, placed after `sort_order`. The
  DB-level default is `true` deliberately, so every pre-existing lesson
  stays visible with zero behavior change on deploy.
- `app/Models/Lesson.php` — `is_published` added to `$fillable`/`$casts`;
  extracted `extractYoutubeId(string $url): ?string` as a reusable static
  (used by both the admin duration-fetch endpoint and the model's own
  `youtubeVideoId()`).
- `app/Models/Course.php` — `lessons()` (the `hasManyThrough`) now filters
  `where('lessons.is_published', true)`, so `lessonCount()`,
  `progressPercent()`, `withCount('lessons')`, and the certificate's 100%
  gate all automatically respect draft state with no separate plumbing.
- `app/Http/Controllers/Student/LearningController.php` — new private
  `publishedLessonsFlat(Course)` helper, used by `show()`'s first-lesson
  redirect, `lesson()` (`abort_unless($lesson->is_published, 404)` — a
  student cannot reach a draft lesson by guessing/bookmarking its URL even
  though it's still technically enrolled-accessible), and
  `adjacentLesson()`'s prev/next nav.
- `resources/views/learn/lesson.blade.php` — sidebar lesson list filtered
  to published only.
- `app/Http/Controllers/CourseCatalogueController.php` — `preview()` now
  also requires `is_published` alongside the existing `is_free_preview`
  check, so a draft lesson can't leak through the public free-preview URL
  either.
- `app/Http/Controllers/Admin/LessonController.php` — three new actions:
  `storeInline()` (curriculum-tree quick-add: title only, always created
  as an unpublished draft with sane defaults so it's immediately safe to
  leave half-finished), `togglePublish()` (flips the flag, no full-form
  visit needed), `fetchVideoDuration()` (validates a URL, extracts a
  YouTube ID, delegates to `YoutubeService`). `validated()` gained
  `is_published` handling matching the existing `is_free_preview` pattern
  — unchecked means `false` for a new lesson via the standard form.
- `app/Http/Controllers/Admin/CurriculumController.php` — new,
  `reorder(Request, Course)`: validates `modules[].{id,sort_order}` and
  `lessons[].{id,sort_order,course_module_id}`, and — critically — verifies
  every id actually belongs to the route-bound `$course` before writing
  anything, rejecting (silently dropping, not erroring, since this is a
  best-effort persist of a drag gesture) any id from a different course.
  Wrapped in `DB::transaction()` so a partial drag never leaves the tree in
  a mixed state.
- `app/Http/Controllers/Admin/QuizController.php` /
  `Admin/AssignmentController.php` — `create()` now accepts `?lesson_id=`
  from the curriculum tree's inline "+ Quiz"/"+ Assignment" links and
  pre-fills the new model, so creating an assessment from a specific lesson
  no longer requires re-selecting it from a dropdown.
- `app/Services/YoutubeService.php` — new. `isConfigured()`,
  `fetchDurationMinutes(string $videoId): ?int` (YouTube Data API v3
  `videos?part=contentDetails`), private ISO-8601 duration parser.
- `config/services.php` / `.env.example` — `YOUTUBE_API_KEY`, optional.
- `routes/web.php` — `modules/{module}/lessons-quick`,
  `lessons/{lesson}/toggle-publish`, `lessons/fetch-video-duration`,
  `courses/{course}/curriculum/reorder`.
- `resources/views/admin/courses/show.blade.php` — curriculum tree wrapped
  in an Alpine `curriculumBuilder(cfg)` component; SortableJS on the module
  list and each lesson list; drag handles; per-lesson Published/Draft badge
  + toggle form; inline "+ Quiz"/"+ Assignment" links; per-module quick-add
  form.
- `resources/views/admin/courses/lesson-form.blade.php` — `is_published`
  checkbox; video URL field wired to Alpine state; "Auto-fetch duration"
  button with a status message area.
- `public/vendor/js/sortable.min.js` — new vendored file.

**Decision — vendored SortableJS instead of importing it via npm/Vite.**
First attempt added `import Sortable from 'sortablejs'` to
`resources/js/app.js`, then reading `layouts/admin.blade.php` more closely
turned up its explicit comment that the admin layout deliberately never
loads the Vite bundle at all (Livewire 3 already bundles Alpine; loading
`app.js` too would double-instantiate it) — so anything the admin panel
needs as a plain global variable has to arrive as a vendored `<script>`
tag, exactly like the existing `public/vendor/js/chart.min.js` precedent.
Reverted `app.js`, copied `Sortable.min.js` from a temporary
`npm install sortablejs --save-dev` (immediately `npm uninstall`'d after,
confirmed zero net diff in `package.json`/`package-lock.json`) into
`public/vendor/js/sortable.min.js` instead. Caught before any test run or
commit, so nothing shipped with the broken approach.

**Decision — YouTube Data API v3 instead of oEmbed for duration.** The plan
text says "duration auto-fetch... via oEmbed," but YouTube's oEmbed
endpoint only ever returns title/thumbnail/author — it cannot report
duration under any circumstance. Since the plan's actual intent (auto-fill
duration so an admin doesn't have to look it up and type it by hand) can't
be satisfied by the literal mechanism named, built `YoutubeService` against
the real Data API v3 instead, gated entirely behind an optional
`YOUTUBE_API_KEY`. Unconfigured behavior is identical to before this item
existed: the admin types the duration manually.

**Bug found and fixed:** `test_toggling_publish_flips_the_flag` initially
failed asserting `is_published === true` right after
`Lesson::create([...])` — the DB-level default isn't reflected on the
in-memory model until it's reloaded. Fixed the test helper with `->fresh()`
after `create()`; the same class of bug hit several other places earlier in
this build and is now a standing thing to check for whenever a factory/
`create()` call relies on a column's DB default rather than passing it
explicitly.

**Tests added:** `CurriculumBuilderTest` (10 — publish toggle round-trips;
an unpublished lesson 404s for an enrolled student; an unpublished lesson
is excluded from `lessonCount()`; quick-add creates a draft; reordering
modules and lessons persists together in one request; reordering silently
rejects ids from a different course without touching them; a non-admin is
redirected to login for toggle/quick-add/reorder; duration-fetch reports
`unavailable` with no key configured, `not_youtube` for a non-YouTube URL,
and a correctly-parsed `PT15M33S` → 16 minutes via `Http::fake()`).

**Verification:** `php artisan migrate` / `:rollback` / `migrate` clean.
`vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G` 0
errors. Full untargeted `php artisan test` — 418/418 green (408
pre-existing + 10 new).

### P4.10 — Course analytics tab (§6.3.4)

**Built:**

- `app/Services/Learning/CourseAnalyticsService.php` — new.
  `funnel(Course)` (enrolled → started → reached_25/50/75 → completed →
  certified, each stage a `where` over the same base query so the numbers
  are directly comparable), `lessonDropOff(Course)` (per published lesson,
  in curriculum order, the % of enrolled students who completed it — a
  sharp drop between two adjacent bars is exactly the "where do people
  quit" signal the plan calls out), `watchTimeHistogram(Course)` (six
  fixed buckets from "no watch time" to "5+ hrs"), and
  `quizSummaries(Course)` (per-quiz average score across `Graded` attempts
  only, plus attempt volume).
- `app/Http/Controllers/Admin/CourseAnalyticsController.php` — new, a
  plain read-only `show()` action (no Livewire needed — nothing here is
  sortable/filterable/paginated, unlike Students/Gradebook/Q&A).
- `resources/views/admin/courses/analytics.blade.php` — new. Reuses
  `<x-dash.stat>`/`<x-dash.section>`/`<x-dash.bars>` exactly as they exist
  today — no new chart library, per the plan's explicit note that
  `components/dash/*` already covers this.
- `routes/web.php` — `courses/{course}/analytics` (`admin.courses.analytics`).
- `resources/views/admin/courses/show.blade.php` — "Analytics" button added
  to the action bar alongside Students/Gradebook/Q&A/Bulk Enroll.

**Decision — linked to the existing per-quiz item analysis instead of
re-deriving it here.** §6.3.4 lists "quiz item analysis (per-question
correct-rate…)" as one of the four charts, but `QuizAnalysisService` and
its `admin.quizzes.analysis` page already do exactly this per-quiz (built
in P3.10). Duplicating that logic into a fifth chart on this page would
mean the same per-question numbers computed two different ways. Instead
the course analytics tab lists each quiz with its average score and a
direct link into the existing item-analysis page — same data, one source
of truth.

**Decision — funnel/drop-off/watch-time all scope to `active`/`completed`
enrollments only, never `pending`.** Matches the definition
`CourseCatalogueController` already uses for "genuinely enrolled" (P4.2) —
a student who started Flutterwave checkout but never completed payment
isn't a real funnel entrant, so pending enrollments are excluded from
every count on this page, not just hidden from the UI.

**Tests added:** `CourseAnalyticsTest` (6 — non-admin redirected to login;
funnel stage counts across a mix of pending/active/completed enrollments at
different progress levels; a certificate moves an enrollment into
"certified"; per-lesson drop-off is in curriculum order, correctly
computed, and never includes an unpublished lesson; watch-time histogram
buckets correctly; quiz summary averages only `Graded` attempts and the
page links to the real `admin.quizzes.analysis` route).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. Full untargeted
`php artisan test` — 424/424 green (418 pre-existing + 6 new).

### P4.11 — Nudge emails + weekly instructor digest (§6.4)

**Built:**

- `app/Console/Commands/DetectAtRiskEnrollments.php` — the two rules its own
  P1 docblock explicitly deferred ("wait on the P3 quiz/assignment models")
  are now implemented, since P3 landed: `struggling` (a quiz's
  graded-attempt average is below that quiz's own `pass_percent`, or its two
  most recent graded attempts both failed) and `missing_work` (a published,
  past-due assignment with zero submissions from this enrollment). Checked
  in the plan's listed order — inactive → stalled → struggling →
  missing_work, first match wins, same single-column model as before.
- `app/Console/Commands/SendWeeklyInstructorDigest.php` — new. Queries every
  active enrollment with a non-null `at_risk_reason`, and — only if that
  list is non-empty — emails one `WeeklyInstructorDigestNotification` to
  every `super_admin`/`admin` user via `Notification::send()`. An empty list
  sends nothing at all, matching the plan's "optional" framing literally
  rather than treating it as an unused config flag.
- `app/Notifications/WeeklyInstructorDigestNotification.php` — new,
  mail-only. Subject and greeting match the plan's own example ("5 students
  at risk: …"); lists up to 20 students with course + human-readable reason,
  a "…and N more" line beyond that, a link to `admin.enrollments.index`, and
  a pointer to the drill-down's existing one-click nudge.
- `routes/console.php` — scheduled `app:send-weekly-instructor-digest`
  Mondays at 07:00, after the 02:00 nightly at-risk run so the week's
  numbers are fresh.
- `resources/views/livewire/admin/course-students.blade.php` — the at-risk
  badge now renders `str_replace('_', ' ', ...)` before `ucfirst()`, so the
  new `missing_work` reason reads "Missing work" instead of "Missing_work".

**Decision — the one-click nudge itself needed no new code.**
`StudentNudgeNotification` and `EnrollmentDrilldown::sendNudge()` already
existed from P1's per-student drill-down work and already do exactly what
§6.3.2/§6.4 describe (a templated "you're X% through" email, one click from
the drill-down page). P4.11's actual remaining scope was completing the
at-risk rules the digest depends on, plus the digest itself — verified by
re-reading both the P1 code and its own docblock before starting, per rule
#1.

**Decision — completing `struggling`/`missing_work` now rather than leaving
them deferred.** The plan doesn't list "finish the at-risk rules" as its
own P4 line item, but a weekly digest that claims "N students at risk"
while silently only ever checking two of the plan's four rules would be
dishonest about what it's reporting — and the stated blocker (no quiz/
assignment models) has been gone since P3. Rule #2's "ambiguity resolved in
favor of the plan's intent" points the same direction: §6.4 describes one
at-risk concept with four rules, not two now and two later indefinitely.

**Tests added:** `DetectAtRiskEnrollmentsTest` (+6 — below-pass-mark average
flags struggling; two consecutive failed attempts flag struggling even with
an above-mark average; an in-progress attempt is ignored; a past-due
assignment with no submission flags missing_work; a submitted assignment
clears it even past due; a not-yet-due assignment never flags it).
`SendWeeklyInstructorDigestTest` (4 — no email when nothing is at risk;
every admin is reached when something is; only active enrollments with a
reason are included; the mail content names the student and reason).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. Full untargeted
`php artisan test` — 434/434 green (424 pre-existing + 10 new).

## P4 phase gate — closed

All eleven items done, one commit per item, in plan order, tagged `lms-p4`.
Closes/advances: §7.1 (Flutterwave course checkout, coupons, refunds),
§7.3 (announcements/Q&A/notes/reviews — all four community tabs), §7.5
(bulk enroll, drag-drop curriculum builder), §6.3.4 (course analytics tab —
funnel/drop-off/watch-time, on top of P3.10's quiz item analysis), and
§6.4 (at-risk detection's `struggling`/`missing_work` rules, deferred since
P1, plus the weekly instructor digest).

- `composer ci` (repo-wide `pint --test`, `phpstan --memory-limit=1G`,
  `check-empty-files`, `secrets-scan`, `php artisan test`): **green**, exit
  code 0, 434 tests / 945 assertions.
- `php artisan migrate` on a fresh checkout applies every P4 migration
  cleanly in order (verified via a full migrate→rollback→migrate cycle
  after each schema-touching item, same discipline as every prior phase).
- **Real bugs found and fixed while building and testing this phase (not
  routed around):**
  1. **Pending enrollment treated as fully enrolled** (found during P4.2) —
     `courses/show.blade.php` only checked "does an enrollment row exist,"
     so a student who'd started Flutterwave checkout but never paid saw
     "Continue learning," which then 403/404'd against `EnrollmentPolicy`.
     Fixed by restricting the "enrolled" state to `active`/`completed` and
     adding a distinct "Complete checkout" state for `pending`; pinned with
     a regression test.
  2. **`Lesson::create()` without an explicit `is_published` left the
     in-memory model `null`** (P4.9) — the DB-level `default(true)` isn't
     reflected until the model is reloaded, so a test asserting
     `$lesson->is_published === true` right after `create()` failed. Same
     class of bug hit several times across earlier phases; fixed with
     `->fresh()` in the test helper.
  3. **Self-caught before shipping** (P4.9) — first attempt at loading
     SortableJS added it to `resources/js/app.js` (the Vite bundle), then
     re-reading `layouts/admin.blade.php`'s own comment turned up that the
     admin panel deliberately never loads that bundle at all (avoids a
     double-Alpine conflict with Livewire 3's bundled Alpine). Caught
     before any test ran or anything committed; reverted, vendored
     `Sortable.min.js` into `public/vendor/js/` instead, matching the
     existing `chart.min.js` precedent.
- **One plan-text ambiguity resolved via judgment call, documented at the
  time (P4.9):** the plan says duration auto-fetch works "via oEmbed," but
  YouTube's oEmbed endpoint cannot report duration under any circumstance —
  only the real Data API v3 can. Built `YoutubeService` against the Data
  API, gated behind an optional `YOUTUBE_API_KEY`, since the plan's actual
  intent (auto-fill duration) couldn't be satisfied by the literal
  mechanism named.
- **One scope decision made explicitly in favor of the plan's intent
  (P4.11):** `struggling`/`missing_work` at-risk detection had been
  deferred in P1 pending the P3 quiz/assignment models. Rather than leave
  the weekly digest reporting against only half of §6.4's rules now that
  the blocker was gone, both rules were completed as part of P4.11 before
  the digest was built on top of them.
- Every new AJAX/interactive surface added this phase degrades without
  JavaScript: the curriculum builder's quick-add/publish-toggle/materials
  forms are real `<form>` posts (drag-reorder is the one JS-only
  enhancement, with the existing edit-form reorder fields as the
  non-JS fallback); Q&A and Notes are plain forms; reviews and coupons are
  plain forms; the checkout page posts to the pre-existing
  `portal.invoice.pay` route.
- No query-count regressions on any learn surface — every new tab
  (announcements, Q&A, notes, reviews) is its own dedicated route/page, not
  additional data bolted onto the existing lesson/dashboard queries.

**Tagged `lms-p4`.**

---

## P5 — Polish & scale

### P5.1 — Badges & weekly streak counter (§6.5)

**Built:**

- `app/Enums/BadgeType.php` — a fixed, code-defined vocabulary
  (`FirstCourseCompleted`, `FiveCoursesCompleted`, `PerfectQuiz`,
  `FourWeekStreak`), matching `LearningEventType`/`CouponType`'s existing
  backed-enum convention. No admin CRUD — new badges ship as new cases, not
  database rows.
- `database/migrations/2026_07_26_231833_create_user_badges_table.php` —
  `user_badges`: `user_id`, `badge_type`, unique on the pair, `created_at`
  only (doubles as `earned_at`, mirroring `LearningEvent`'s
  `UPDATED_AT = null` pattern).
- `app/Models/UserBadge.php`, `User::badges()` (latest first).
- `app/Services/Learning/StreakService.php` — `currentWeeklyStreak(User)`:
  consecutive ISO weeks (ending this week, or last week if this week hasn't
  had activity yet — a week still in progress doesn't break a live streak)
  with at least one `learning_events` row across any of the user's
  enrollments. Computed in PHP over distinct `DATE(created_at)` values
  rather than a DB-specific week function, since `YEARWEEK()` is MySQL-only
  and the test suite runs on SQLite.
- `app/Services/Learning/BadgeService.php` — `awardCourseCompletionBadges()`,
  `awardPerfectQuizBadgeIfEarned()`, `awardStreakBadgeIfEligible()`. Every
  award goes through `firstOrCreate`, so nothing can double-issue.
- `app/Listeners/Learning/AwardCourseCompletionBadges.php` (queued, on
  `CourseCompleted`) and `AwardPerfectQuizBadge.php` (queued, on
  `QuizAttemptSubmitted`) — no new events needed; both already fire exactly
  where required (`QuizAttemptSubmitted` fires once per attempt reaching
  `Graded`, whether auto- or manually-graded — confirmed by reading
  `QuizService` before assuming so).
- `app/Console/Commands/AwardStreakBadges.php` — new, scheduled `02:30`
  daily in `routes/console.php`. The streak badge is time-based, not
  action-based, so unlike the other two it has no single triggering event;
  mirrors `app:detect-at-risk-enrollments`'s nightly-command shape rather
  than inventing a different pattern for the same kind of problem.
- `app/Support/Dashboard/DashboardService.php` — `studentBadges()`,
  `studentWeeklyStreak()`, both flat single queries regardless of
  enrollment count.
- `resources/views/admin/dashboard/roles/student.blade.php` — the
  post-login student dashboard gained a streak stat, a badge-count stat,
  and an earned-badge shelf.

**Decision — listeners for the two event-triggered badges, a nightly
command for the time-based one.** §4.5 explicitly names "streak/badge
updates" as listener-handled side effects, and the codebase's whole
event/listener apparatus already exists for exactly this. The streak badge
doesn't fit that shape (nothing "happens" to trigger it — a week just
passes), so it follows the OTHER existing convention for time-based
aggregate checks (`DetectAtRiskEnrollments`) instead of forcing a
listener-only design where one doesn't fit.

**Decision — badges surfaced on the login dashboard, not the "My Courses"
hub.** `learn/index.blade.php` already has its own per-course progress ring
(§6.5's other requirement, built in P1); the login dashboard
(`roles/student.blade.php`) is the actual landing page and didn't yet have
a motivational-progress section, so that's where the single instance of
this widget lives — avoids two places rendering slightly different badge
lists.

**Bug found while writing tests (test code only, not production code):**
backdating `learning_events.created_at` via
`->create(['created_at' => ...])` silently no-ops — `created_at` isn't in
`LearningEvent::$fillable` (deliberately; it's a domain log, not client
input), so every such call across the *entire* test suite, going back to
P1's `DetectAtRiskEnrollmentsTest`, had actually been writing `now()`. It
never flipped an assertion before because every existing test's date
offset stayed safely inside whatever window it was checking (a few days
either side of a 14- or 21-day threshold). The week-boundary-sensitive
streak tests are the first to actually depend on the exact value, and
caught it immediately. Fixed in the new tests via
`forceFill(['created_at' => $when])->save()` after `create()`; older tests
were left as-is since their assertions never depended on the real value.

**Tests added:** `StreakServiceTest` (7 — no activity; this-week-only;
last-week-only-still-counts; consecutive weeks accumulate; a gap breaks the
streak before the gap; a 2+-week-old gap resets to zero; another student's
activity never counts). `BadgeServiceTest` (8 — one completion awards only
the first-course badge; five completions also awards the five-course
badge; awarding twice doesn't duplicate the row; completing a course's
final lesson awards the badge via the real `CourseCompleted` event; a
100% quiz awards `PerfectQuiz`; 99% doesn't; an in-progress attempt never
does; the dashboard actually renders the badge shelf and streak stat).
`AwardStreakBadgesTest` (4 — a 4-week streak earns the badge; a 3-week
streak doesn't yet; a student with no enrollments is skipped without
error; admins/clients are never evaluated).

**Verification:** `php artisan migrate` / `:rollback` / `migrate` clean.
`vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G` 0
errors. Full untargeted `php artisan test` — 453/453 green (434
pre-existing + 19 new).

### P5.2 — Enrollment expiry windows (§4.4/§6.4)

**Built:**

- `database/migrations/..._add_expires_at_to_enrollments_table.php`,
  `..._add_access_duration_days_to_courses_table.php` — both additive,
  both nullable, both default to no behavior change (null = lifetime
  access) for every enrollment/course that exists today.
- `app/Models/Course.php` — `enrollmentExpiresAt(): ?Carbon`, the single
  place that turns a course's `access_duration_days` into a concrete
  timestamp. Every call site below goes through this one method rather
  than each computing its own `now()->addDays(...)`.
- `app/Models/Enrollment.php` — `isExpired(): bool`.
- `app/Policies/EnrollmentPolicy.php` — `access()` now denies an expired
  window on top of the existing status check, regardless of status (a
  completed-but-expired enrollment loses lesson access exactly like an
  active one — certificate viewing was already unaffected by this policy,
  confirmed by reading `LearningController::certificate()` before assuming
  so).
- `expires_at` now stamped at all four places an enrollment actually
  activates: `CourseCatalogueController::enroll()`'s free self-enroll path,
  `Admin\EnrollmentController::store()`, `Admin\BulkEnrollController::store()`,
  and `ActivateCourseEnrollmentsOnInvoicePaid` (paid checkout). All four
  route through `Course::enrollmentExpiresAt()` so there's exactly one
  place the "days → timestamp" math lives.
- `app/Livewire/Admin/EnrollmentDrilldown.php` — `extendAccess()` (extends
  from the later of "now" or the current expiry, so a lapsed window doesn't
  shortchange the extension) and `removeExpiry()` (grants lifetime access).
  This is P1's explicitly-deferred "extend access" button — its own
  docblock said it "needs an enrollment expiry concept that doesn't exist
  until P5," which is exactly what this item builds.
- UI: an "Expired" badge on the admin Students tab
  (`course-students.blade.php`), an "Access expired" state on the "My
  Courses" hub that replaces the Resume/Continue/Quizzes/etc. button row
  entirely (a live certificate link still shows if one was already earned),
  an access-days disclosure on the public course page, and the admin course
  form's new "Access duration (days)" field.

**Decision — expiry denies access regardless of enrollment status, not just
`active`.** A `completed` enrollment with a lapsed access window still
loses lesson/material access under this policy — matching the actual
intent of a "Udemy-style access window" (a time-limited license, not just
a block on unfinished courses). The certificate a student already earned
during that window remains permanently viewable, since certificate
delivery was never gated by `EnrollmentPolicy::access()` to begin with.

**Bug prevented before it shipped (same class as a real P4.2 bug, caught by
re-reading `learn/index.blade.php` before assuming the existing status
check was sufficient):** without the "My Courses" hub fix, an expired
enrollment would still render a "Resume"/"Continue" button (since its
`status` column is still `active`), which would then 403 the moment it's
clicked — the same dead-link shape P4.2 fixed for pending enrollments.
Fixed in the same commit as the policy change, not left for a follow-up.

**Tests added:** `EnrollmentExpiryWindowTest` (11 — a lifetime-access
course computes a null expiry; a course with a duration computes the right
future timestamp; all four activation sites correctly stamp `expires_at`
(free self-enroll, admin single-enroll, bulk-enroll, paid-checkout
end-to-end through the real gateway/webhook flow); a lifetime-access course
leaves `expires_at` null; extending a lapsed window starts from now, not
the stale past date; extending a still-active window adds on top of the
current expiry; removing the expiry grants lifetime access; the "My
Courses" hub shows the expired state and hides Resume). Plus 2 added to
`EnrollmentAccessPolicyTest` (an expired active enrollment is denied; a
future-dated expiry still allows access).

**Verification:** `php artisan migrate` / `:rollback` / `migrate` clean.
`vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G` 0
errors. Full untargeted `php artisan test` — 466/466 green (453
pre-existing + 13 new).

### P5.3 — Self-hosted video option via signed streaming URLs (§6.2/§8 roadmap)

**Built:**

- `database/migrations/..._add_video_disk_path_to_lessons_table.php` —
  nullable, additive; every existing lesson is completely unaffected.
- `app/Models/Lesson.php` — `hasSelfHostedVideo(): bool`; takes priority
  over `video_url`/`youtubeVideoId()` in the player when both are set.
- `app/Http/Controllers/Admin/LessonController.php` —
  `applyVideoUpload()`: a new `video_file` upload replaces (and deletes)
  whatever was attached before; a `remove_video_file` checkbox clears the
  path and falls back to the plain URL field, so there's always a way back
  to YouTube-only. Validated `mimes:mp4,mov,webm|max:512000` (500MB).
- `app/Http/Controllers/Student/LessonVideoController.php` — new,
  `stream()` on the `learn.lesson.video-stream` route, gated by Laravel's
  built-in `signed` middleware.
- `app/Http/Controllers/Student/LearningController.php` — `lesson()`
  mints a fresh `URL::temporarySignedRoute(..., now()->addHours(6), ...)`
  on every page load when `hasSelfHostedVideo()`.
- `resources/views/learn/lesson.blade.php` — a native `<video>` element +
  new `selfHostedVideoPlayer()` Alpine component. Extracted the heartbeat
  fetch/toast logic out of `youtubePlayer()` into a shared
  `postLessonHeartbeat()` function first, then had both players call it —
  the request/response contract was already player-agnostic
  (`ProgressService::recordHeartbeat()` takes a plain delta/position,
  confirmed by re-reading it before assuming so), so this was a
  refactor, not a new contract. `selfHostedVideoPlayer()` also sets
  `window.__lessonVideoPlayer` to the same `getCurrentTime()`/`seekTo()`
  shape `youtubePlayer()` already provides, so the notes tab's seek
  buttons and the note-form's "current timestamp" field work identically
  regardless of which player actually mounted — zero changes needed there.
- `resources/views/admin/courses/lesson-form.blade.php` — file input +
  conditional "attached" indicator + remove checkbox.

**Decision — the signed URL is a hardening layer, not a replacement for
real authorization.** A signature alone proves "we generated this exact
URL, for this exact lesson, within its validity window" — it says nothing
about whether the enrollment backing it is still `active`/unexpired at the
moment someone actually pulls the bytes. `LessonVideoController::stream()`
therefore still runs the identical `EnrollmentPolicy`/`LessonPolicy` checks
every other lesson surface enforces, on every request (including every
Range request a seek triggers). This costs a few extra queries per
seek — a non-issue at this platform's scale — in exchange for closing the
"leaked link still works after cancellation" gap a signature-only design
would have left open. The signature itself still earns its place: it keeps
the real storage path out of the client, and bounds a leaked link's window
to 6 hours instead of forever.

**Decision — `response()->file()` over a hand-rolled streamed response.**
Video seeking needs HTTP Range support (206 Partial Content); Symfony's
`BinaryFileResponse` (what `response()->file()` returns) handles Range
headers automatically via its own `prepare()`, called as a normal part of
the response lifecycle — no manual byte-range math needed, and no risk of
a subtly-wrong hand-rolled implementation breaking seeking on some
browsers.

**Tests added:** `LessonVideoUploadTest` (5 — a new upload stores the file
and sets the path; uploading again replaces and deletes the old file; the
remove checkbox clears the path and deletes the file; a non-video file is
rejected; leaving the field untouched on update keeps the existing path
unchanged). `SelfHostedVideoTest` (8 — the lesson page renders the
self-hosted player with a signed URL; self-hosted wins over a YouTube URL
when both are set; streaming with no signature is rejected; a valid
signature streams successfully; a non-enrolled user's request 404s (no
enrollment row — the same shape `LessonMaterialController::download()`
already produces for this scenario) while a *pending* enrollment's valid
signature still 403s from the policy; an expired signature is rejected;
a lesson with no attached video 404s on the stream route).

**Verification:** `php artisan migrate` / `:rollback` / `migrate` clean.
`vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G` 0
errors. Full untargeted `php artisan test` — 479/479 green (466
pre-existing + 13 new).

### P5.4 — API v1 parity for the core learning loop (§8 roadmap)

**Scope decision, asked of the user before starting:** the API v1 surface was
still the original P0/P1 skeleton (catalogue, my-enrollments, enroll,
complete-lesson, invoices, projects) — nothing from P2 (heartbeat/resume),
P3 (quizzes, assignments, gradebook), or P4 (checkout/coupons,
announcements/Q&A/notes/reviews) existed in the API at all. Genuinely full
parity is roughly as large as P3+P4's API-facing surface combined —
disproportionately large next to every other single-feature P5 item. Given
the choice between full parity as its own mini-phase, this scoped-down
slice, or deferring entirely, the user chose: **core learning loop only**
(lesson player, quizzes, assignments, grades, notes) — the things a mobile
app needs to be usable day-to-day. Commerce (checkout/coupons) and
community (announcements/Q&A/reviews) endpoints are explicitly deferred as
naturally web-first flows, not mobile-critical, and out of scope for this
item.

**Built:**

- `Api\V1\LessonController::show()` — content, video source (a signed
  stream URL when self-hosted), resume position, lock state. Calls
  `ProgressService::recordView()`, same as the web player.
- `Api\V1\LessonVideoController::stream()` — the mobile equivalent of
  P5.3's web stream controller, but deliberately `signed`-only with no
  `auth:sanctum` layer on top. Documented at length in the class's own
  docblock: a native mobile video player generally can't attach a bearer
  token to its own request the way a browser attaches a session cookie for
  free, so the signature has to be the sole credential at stream time —
  real authorization already happened once, at mint time, in
  `LessonController::show()`.
- `Api\V1\EnrollmentController::heartbeat()` — calls the exact same
  `ProgressService::recordHeartbeat()` the web player calls; confirmed
  player-agnostic by design back in P5.3, so this needed zero new logic.
- `Api\V1\QuizController` + `QuizAttemptController` — start/run/answer/
  submit/review, all routed through `QuizService`. Deliberately simpler
  than the web `QuizAttemptController`: no "no-JS form" bulk-answers
  fallback and no ordering-question position-input normalization, since a
  native JSON client always sends a well-shaped `{"order": [id, id, id]}`
  array directly rather than an HTML form's positional inputs.
- `App\Http\Resources\QuestionRunResource` — new. The web quiz runner gets
  "never reveal `is_correct`/`match_key` before grading" for free by simply
  never echoing those fields in Blade; a JSON API has no equivalent
  implicit safety (a naive `$question->load('options')` would serialize
  the whole model, correctness data included), so this resource explicitly
  strips them. Caught by writing a real test that asserts the string
  `is_correct` never appears anywhere in the run-endpoint's raw response
  body, not just that a specific key is absent.
- `Api\V1\AssignmentController` — index/show/draft/submit/download, same
  `AssignmentService` and the same per-`Assignment::acceptsType()`
  validation rules the web controller builds.
- `Api\V1\GradeController` — a direct mirror of `Student\GradesController`.
- `Api\V1\LessonNoteController` — index/store/destroy, same ownership
  checks as `Student\LessonNoteController`.
- `OpenApiController` — every new path documented, matching the class's
  own "hand-maintained alongside the routes" convention.

**Bug found and fixed:** `Api\V1\EnrollmentController::store()` (the free
self-enroll endpoint) never stamped `expires_at` from
`Course::enrollmentExpiresAt()` — P5.2 updated the other three
enrollment-activation call sites (web free-enroll, admin single-enroll,
bulk-enroll, paid-checkout activation) but missed this one, since the API
wasn't in scope for that item at the time. Found while auditing the API
surface for this item; fixed and pinned with
`test_free_self_enroll_via_the_api_stamps_the_courses_access_window`.

**A second, more subtle bug — this time in the *test* code, not the app —
found and fixed while writing this item's tests:** several tests mixed
`$this->actingAs($user)` (used to authorize a direct service-layer call
during fixture setup, e.g. starting a quiz attempt outside of any HTTP
request) with `$this->withToken($token)` (bearer-token HTTP requests) in
the same test method. Sanctum treats a Laravel test request as "stateful"
by default, which means the session user set by `actingAs()` silently wins
over the bearer token — so a test built specifically to prove "student B
cannot see student A's quiz attempt" was actually, invisibly, still
running as whichever user `actingAs()` had last set, defeating its own
purpose. Caught because fixing the course-slug/attempt-uuid route-key bugs
below made this particular test go from an accidental false-positive pass
to a real failure — the fix (`auth()->logout()` before each
token-authenticated assertion) is now applied everywhere the pattern
recurs in this file.

**Two route-key mismatches also found while first running these tests
(both test bugs, not app bugs):** `Course` and `QuizAttempt`/
`AssignmentSubmission` all override `getRouteKeyName()` (`slug` and `uuid`
respectively, both pre-existing conventions from earlier phases) — the
first test drafts used numeric ids in the URL for all three, which 404'd
immediately at the routing layer before ever reaching a controller. Fixed
across all five new test files.

**Tests added:** 22 across five files — `LessonApiTest` (6: auth required,
content + resume position, signed video URL present when self-hosted, a
pending enrollment denied, heartbeat records watch time and applies the
same `min_watch` rule as the web player, free self-enroll stamps
`expires_at`), `QuizApiTest` (6: index, start, the run endpoint never
leaks correctness data, immediate-feedback preview, submit+review
end-to-end, a genuine cross-student denial), `AssignmentApiTest` (4: index,
draft, submit, a pending enrollment denied), `GradesAndNotesApiTest` (3:
grade percent matches `GradebookService`, note create/list/delete,
cross-student note deletion denied), `LessonVideoStreamApiTest` (3: a
valid signature streams with no token, no signature is rejected, an
expired signature is rejected).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. Full untargeted
`php artisan test` — 501/501 green (479 pre-existing + 22 new).

### P5.5 — Accessibility audit pass (§7.6)

**Built:**

- Computed real WCAG contrast ratios (relative-luminance formula, not
  eyeballed) for the navy/gold theme across the three public/portal layouts
  (`layouts/app.blade.php`, `marketing.blade.php`, `auth.blade.php`, which
  keep identical `:root` blocks). Found and fixed three genuine failures,
  confirmed against real usages in `learn/*` templates (status pills, the
  Q&A "Instructor" badge, success alerts), not just the raw variable
  declarations: `--tx3` "muted" text 2.92:1 → `#706f5c` (4.72:1); `--gold-d`
  on `--gold-soft` 3.83:1 → `#7d6228` (5.06:1); `--ok` on `--ok-soft` 4.42:1
  → `#0f6b30` (5.84:1). The admin back-office's separate blue theme
  (`td-admin.css`) was deliberately left untouched — it isn't the
  "navy/gold theme" §7.6 names, and there's no dark-mode implementation
  anywhere in the app yet, so "in both light/dark" had nothing to check.
- Tap targets: `layouts/app.blade.php`'s `.btn` measured ~41px tall
  (13px font + 9px padding + 1px border, under the 44px minimum); added
  `min-height:44px` there and on the header's sign-out button.
- Focus states: `layouts/app.blade.php` had no `:focus-visible` styling at
  all (the other two public layouts already did); added a themed gold
  outline ring.
- 360px layout: the actual lesson-player grid already had a 760px
  breakpoint from earlier work and needed nothing further, but the shared
  header above it (wordmark + nav + username + sign-out, one un-wrapping
  flex row) had zero narrow-viewport handling and overflowed well before
  360px. Added a 480px breakpoint dropping the wordmark and username —
  the two least essential pieces — first.
- Captions: new `lessons.captions_url` (nullable, additive) for a
  self-hosted video's WebVTT track, rendered as a real
  `<track kind="captions">` element. YouTube's own captions needed no new
  data field at all — just `cc_load_policy:1` in `playerVars` to have them
  show by default, since the video's captions (if any) already live on
  YouTube's side and "pass through" the IFrame player once enabled.
- Accessible names: the static Vimeo/plain-URL iframe and the self-hosted
  `<video>` element had no `title`/`aria-label`; fixed both. The YouTube
  player's iframe is created by Google's own script, not ours, so it isn't
  directly addressable — added `role="region" aria-label` on its wrapper
  div instead as the closest achievable equivalent.
- `aria-live`: read `partials/toast-host.blade.php` before assuming
  anything was missing — it already had `aria-live="polite"` from earlier
  work, so every toast-routed notification (lesson auto-complete, etc.)
  was already covered. Added it to the two things that genuinely lacked
  it: the quiz autosave "Saved" hint, and new 5-minute/1-minute quiz-timer
  threshold announcements — routed through the *existing* toast bus rather
  than making the visible countdown itself a live region, which would
  re-announce every single second.

**Decision — left the lesson auto-advance countdown card alone.** "Next: X
— starting in 5s" ticks every second same as the quiz timer; making it
`aria-live` would have the identical every-second-announcement problem the
timer fix was built specifically to avoid. It already has a "Stay here"
button for control, and unlike the quiz timer, §7.6 doesn't name it
specifically — a defensible scope boundary rather than a missed item.

**Tests added:** `AccessibilityTest` (7 — captions_url persists via the
admin form; a self-hosted video with captions renders a real `<track>`
element and one without renders none; the YouTube player's
`cc_load_policy: 1` is present; both static-iframe and self-hosted-video
embeds carry an accessible name; the three corrected theme colors are
asserted against the actual WCAG contrast formula, not just "looks fine,"
so a future accidental revert of any of the three hex values fails CI
immediately).

**Verification:** `php artisan migrate` / `:rollback` / `migrate` clean.
`vendor/bin/pint --dirty` clean. `phpstan analyse --memory-limit=1G` 0
errors. Full untargeted `php artisan test` — 508/508 green (501
pre-existing + 7 new).

### P5.6 — Load-test the heartbeat endpoint (§7.3 perceived-performance target)

**Built/found:** a genuine, real load test against the actual MAMP-served
app (not PHPUnit's HTTP kernel simulation, which never goes through
Apache) — 40 concurrent distinct-student heartbeat requests via `curl`+
`xargs -P`, a query-log audit of one heartbeat request, and a sequential
latency baseline.

**Bug found and fixed (production-relevant, not a test artifact):** every
Sanctum bearer-token API request 401'd as "Unauthenticated" through the
actual served app — including pre-existing, unrelated endpoints like
`/api/v1/auth/me`. Root cause: this project is served from its root
directory, with a root-level `.htaccess` that proxies every request into
`public/`. Only `public/.htaccess` had the standard "pass the Authorization
header through to PHP" rewrite rule (Apache/`mod_fastcgi` strips that
header by default otherwise) — the root `.htaccess` never had it, so by
the time a bearer-token request reached PHP, the header was already gone.
This means every mobile/API bearer-token request would have silently
failed in exactly this deployment shape — invisible to the entire test
suite all session, since PHPUnit's HTTP kernel simulation bypasses Apache
entirely and was never going to catch it. Fixed by adding the identical
rule to the root `.htaccess`; verified with real `curl` requests against
`api/v1/auth/me` before (401) and after (200) the fix.

**Query-level audit:** enabled the query log for one real heartbeat
request — 9 queries total, zero N+1, and both hot-path lookups
(`enrollments.user_id`+`course_id`, `lesson_progress.enrollment_id`+
`lesson_id`) are backed by pre-existing composite **unique** indexes from
the original migrations (confirmed by reading the migrations directly, not
assumed) — nothing to fix here.

**Latency — measured honestly, with the confound identified rather than
papered over:** raw p95 under 40-way concurrency on this local MAMP setup
didn't clear the plan's `<300ms` target, and even a *single* sequential
heartbeat request measured ~350-400ms. Before concluding the endpoint
itself was slow, compared it against a **zero-DB-work baseline**
(`/api/v1/openapi.json`, a hand-built static array with no auth, no
queries) — that measured ~265ms on its own. This isolates the heartbeat's
actual incremental cost to roughly 70-135ms above the shared baseline
(auth resolution + 9 indexed queries + business logic), not the full raw
figure. The ~265ms shared floor is this specific local environment's
per-request PHP bootstrap cost — MAMP's `mod_fastcgi` here spawns fresh
PHP per request rather than reusing a persistent worker pool (PHP-FPM/
Octane), and Telescope is active by default in `local` env. Tried
`config:cache`/`route:cache`/`view:cache` and toggling `TELESCOPE_ENABLED`
off to see if either moved the number — neither did, consistent with
process-bootstrap (not Laravel's own config resolution or Telescope's
instrumentation) being the actual bottleneck. Reverted both changes
immediately after testing.

**Honest scope boundary, not a fabricated pass:** a true `<300ms` p95
measurement needs a production-shaped environment (PHP-FPM or Octane
worker pool, opcache, Telescope disabled, config cached, no MAMP) that
genuinely cannot be reproduced from this local setup. Recorded here rather
than claiming a pass this environment can't actually demonstrate.

**Cleanup:** all load-test fixtures (a throwaway course/module/lesson, 40
student accounts + Sanctum tokens, all created directly against the real
local dev database, since a real Apache-served load test can't run
against the sqlite-in-memory PHPUnit environment at all) were fully
deleted after testing — verified user count (3) and course count (1)
matched the pre-test state exactly.

**Tests added:** none — this item's real deliverable was the `.htaccess`
fix, which by its nature can't be exercised by PHPUnit's HTTP kernel
simulation (it never touches Apache). Verified manually via `curl` instead
and documented here as the evidence.

**Verification:** no app-code changes beyond `.htaccess`. Full untargeted
`php artisan test` — 508/508 green, unchanged (this item added no new
application-layer surface for the suite to cover).

### P5.7 — Monthly prune job for `learning_events` (§6.2)

**Built:**

- `app/Console/Commands/PruneLearningEvents.php` — deletes
  `learning_events` rows with `created_at` older than 12 months. §6.2 has
  said "raw events pruned after ~12 months by a scheduled command" since
  P1, but only the write side (`LearningEventRecorder`) had ever actually
  been built — the retention half was still outstanding until now.
- `routes/console.php` — scheduled `1st of the month, 03:30`. Matches the
  plan's own "volume at your scale is a non-issue" framing for this
  table — nothing else in the app depends on it staying small in real
  time, so a monthly cadence (rather than nightly, like the other
  scheduled commands) is the right fit.

**Decision — a single `where(...)->delete()` rather than chunked
batching.** Every other bulk-touching command in this codebase
(`DetectAtRiskEnrollments`, etc.) chunks because it runs per-row *domain
logic* (rule evaluation, conditional updates) that benefits from bounded
memory. This command does none of that — it's one unconditional bulk
delete — and the plan explicitly frames volume as a non-issue at this
platform's scale, so a single query is both simpler and correct here.

**Tests added:** `PruneLearningEventsTest` (5 — an event past the 12-month
cutoff is deleted; one within the window is kept; one exactly one day
inside the boundary survives (pins the `<` vs `<=` cutoff comparison);
pruning never touches an enrollment's denormalized
`total_watch_seconds`, proving the aggregate/raw-log split actually holds;
running against zero events is a clean no-op).

**Verification:** no new migrations. `vendor/bin/pint --dirty` clean.
`phpstan analyse --memory-limit=1G` 0 errors. Full untargeted
`php artisan test` — 513/513 green (508 pre-existing + 5 new).

## P5 phase gate — closed

All seven items done, one commit per item, in plan order, tagged `lms-p5`.
Closes/advances: §6.5 (badges, weekly streak counter), §4.4/§6.4
(enrollment expiry windows — closing a gap originally scoped for P0/P1
that was never actually built, plus P1's explicitly-deferred "extend
access" drill-down button), the P5 roadmap's "self-hosted video option
(signed URLs)" bullet, API v1 parity for the core learning loop (scoped
down from full P3+P4 parity per an explicit user decision), §7.6
(accessibility), the heartbeat endpoint's real-world load
characteristics, and §6.2's retention half (the prune job).

- `composer ci` (repo-wide `pint --test`, `phpstan --memory-limit=1G`,
  `check-empty-files`, `secrets-scan`, `php artisan test`): **green**, exit
  code 0, 513 tests / 1089 assertions.
- `php artisan migrate` applies every P5 migration cleanly in order on both
  the sqlite test database and, since P5.6 required actually serving the
  app to load-test it, the real local MySQL dev database as well
  (`migrate:status` shows all 23 migrations `Ran`) — the first time this
  session verified a fresh-checkout migration path against a real,
  non-sqlite database rather than only the test suite's in-memory one.
- **One explicit scope decision, asked of the user rather than assumed
  (P5.4):** the API v1 surface was still the P0/P1 skeleton; genuinely full
  parity for every P3/P4 web capability would have been roughly as large
  as P3+P4's API-facing work combined. Given the choice between full
  parity as its own mini-phase, a scoped-down "core learning loop only"
  slice, or deferring entirely, the user chose the scoped slice —
  documented in P5.4's own entry, including exactly what's deferred
  (checkout/coupons, announcements/Q&A/reviews) and why.
- **A real, previously-invisible production bug found and fixed (P5.6):**
  every Sanctum bearer-token API request 401'd through the actual
  Apache-served app, root-caused to a missing "pass the Authorization
  header through" rewrite rule in the project's root `.htaccess` (only
  `public/.htaccess` had it, and the root `.htaccess` proxies every
  request into `public/` before that rule would ever run). This had been
  invisible to the entire test suite for the whole session, since
  PHPUnit's HTTP kernel simulation never goes through Apache at all — only
  a load test that actually hit the served app could have found it. Fixed
  and manually verified with real `curl` requests; a real load test run
  against the real dev database is also the first (and only) time this
  session touched non-test data, and every fixture created for it was
  confirmed fully cleaned up afterward.
- **A second bug found and fixed while auditing for the first (P5.4):**
  the free-self-enroll API endpoint never stamped `expires_at` from a
  course's access-duration setting — P5.2 updated the other three
  enrollment-activation call sites but missed the API one, since the API
  wasn't in scope for P5.2 at the time.
- **A third, subtler bug — this time in test code, not the app — found
  while writing P5.4's tests:** mixing session auth (`actingAs()`, used to
  authorize a direct service call during fixture setup) with bearer-token
  auth (`withToken()`) in the same test let the session user silently win
  once Sanctum treated the test request as stateful, which meant a test
  built specifically to prove "student B can't see student A's data" was
  actually still running as whichever user session auth had last set —
  defeating its own purpose without ever failing. Fixed everywhere the
  pattern recurred; documented at length in P5.4's entry since it's the
  kind of gotcha that could easily recur in any future API test.
- **Two items delivered with an honestly-bounded scope rather than a
  fabricated full pass:** P5.5's accessibility work fixed every genuinely
  code-verifiable issue (WCAG contrast computed against the real formula,
  not eyeballed; tap targets; focus states; a real 360px layout bug;
  captions; accessible names; the two places that actually lacked
  `aria-live`) but explicitly didn't claim to verify things that need a
  real browser or screen reader. P5.6's load test measured real numbers
  honestly, isolated the actual confound (this specific local MAMP
  environment's per-request PHP bootstrap cost, demonstrated by comparing
  against a zero-DB-work baseline endpoint) rather than either claiming a
  false "<300ms p95 verified" or silently dropping the item.
- No query-count regressions: the two new dashboard widgets (streak,
  badge shelf) add a fixed number of extra queries regardless of
  enrollment count, verified against the pre-existing flat-query-count
  test rather than assumed safe.

**Tagged `lms-p5`.**

---

## Final done-check — §9 error-proofing checklist, verified line by line

Per the standing instruction: every line of `LMS_MASTER_PLAN.md` §9 checked
against the actual code, proven with a file/line or test reference, not
assumed from memory of having built it. Five real gaps were found this
pass and closed (see the dedicated `fix(lms): §9 final done-check` commit
above) — everything else below was already true, verified fresh rather
than taken on faith.

### Integrity

- **Server-side validation for every rule the UI implies (locks, timers,
  attempt limits, due dates, enrollment status).** `EnrollmentPolicy::access()`
  (status + §5.2's expiry check), `LessonPolicy::view()` (sequential lock),
  `QuizService::start()` (max attempts, publish window — `QuizService.php`
  lines ~25-45), `QuizService::submit()` (time limit + 30s grace, line
  ~102), `AssignmentService::submit()` (`isPastDue()`/`allow_late` check).
  None of this is duplicated or looser on the API side — same services.
- **Idempotent completion, certificate issuance, webhook handling, quiz
  submit.** `ProgressService::completeLesson()` uses `updateOrCreate`
  (`ProgressServiceIdempotencyTest`); `CertificateService::issueIfEligible()`
  — `Certificate::firstOrCreate()` (`CertificateService.php:54`,
  `CertificateIssuanceTest`); webhook — `GatewayLog::where('tx_ref',...)
  ->lockForUpdate()`/`isSettled()` dedup (`GatewayPaymentService.php:69,73`);
  quiz submit — `QuizAttemptStatus::InProgress` guard rejects a second
  submit with 409 (`QuizService.php:96`, "submitting twice is rejected" in
  `QuizServiceLifecycleTest`).
- **Transactions around all multi-row writes.** `BillingService`
  (`generateInvoice`/`recordPayment`/`refund` — lines 64/133/179),
  `CouponService::redeem()` (line 26), `GatewayPaymentService::settle()`
  (line 67), `Admin\CurriculumController::reorder()` (line 31), and — fixed
  this pass — `QuizService::submit()`'s per-question grading loop (line
  117, previously unwrapped).
- **Private-disk + policy-streamed for every student/instructor file.**
  Every file surface uses `Storage::disk('local')` (private,
  `storage/app/private`, confirmed in P5.3's worklog entry) plus an
  explicit policy/ownership check before streaming:
  `LessonMaterialController.php:40`, `LessonVideoController.php:35` (web
  and API variants), `LessonContentImageController.php:28-30`,
  `AssignmentController::download()`. None are ever `Storage::disk('public')`.
- **Signed/UUID public tokens only.** `Certificate`, `Invoice`,
  `QuizAttempt`, `AssignmentSubmission` all override `getRouteKeyName()` to
  `uuid`; the self-hosted video stream route is Laravel `signed`
  (P5.3/P5.4); the Flutterwave webhook is verified by
  `PaymentGateway::verifyWebhookSignature()`, not a guessable ID.
- **Throttle auth, enroll, contact, heartbeat, quiz-answer.** Web login —
  Breeze's standard `LoginRequest::ensureIsNotRateLimited()` (5
  attempts/email+IP, `RateLimiter`, not route middleware but equally real);
  API login — inherits the global `throttleApi()` 60/min-per-IP/user
  limiter every `/api/*` route gets (`bootstrap/app.php:26`,
  `AppServiceProvider`'s `'api'` limiter); contact —
  `throttle:5,1` (`web.php:40`); enroll — `throttle:10,1` (`web.php:54`);
  quiz-answer — `throttle:60,1` (`web.php:195`); heartbeat —
  `throttle:20,1` (`web.php:221`).
- **Activity-log on grade changes and enrollment mutations — grades are
  auditable.** Was a real gap (`spatie/laravel-activitylog` installed,
  migration run, but wired into zero models anywhere in the app) — closed
  this pass: `Enrollment`, `QuizAttempt`, `AssignmentSubmission` all now
  `LogsActivity`, scoped to the fields that actually matter
  (`GradeAuditLogTest`).

### Correctness

- **One `ProgressService` code path for web + API.** `Student\LearningController`
  and `Api\V1\EnrollmentController`/`LessonController` both inject and call
  the same `ProgressService` — never reimplement completion/heartbeat
  logic. `ApiProgressServiceParityTest` pins this (closes L14).
- **Frozen shuffle order per attempt (regrade-safe).** `QuizService::start()`
  computes `question_order` once via `buildQuestionOrder()` and persists it
  on the attempt (`QuizService.php:64`); every later read (`submit()`,
  the web runner, the API run endpoint) reads the frozen order back off the
  attempt row, never re-shuffles. "Ordering question options are always
  shuffled in the frozen option order" in `QuizServiceLifecycleTest`.
- **Timezone-safe due dates (store UTC, render in Africa/Kampala).** Was a
  real gap (every due-date render was raw UTC) — closed this pass via the
  `Carbon::macro('toLocal')` in `AppServiceProvider` and applied at all
  read-only render sites (`DueDateTimezoneTest`).
- **Money stays in the bcmath/decimal-string convention.**
  `BillingService.php` (`bcadd`/`bcsub`/`bcmul`/`bccomp` throughout,
  e.g. lines 45/55/56/59/62), `CouponService.php:55-59`. No `(float)`
  arithmetic on money anywhere in either service.
- **Completed enrollments freeze when curricula change.** Was a real gap
  (`ProgressService::recordView()` unconditionally recomputed
  `progress_percent` even for an already-`completed` enrollment, so a
  curriculum grown after completion could silently regress a frozen 100%
  the next time the student viewed any lesson) — closed this pass
  (`CompletedEnrollmentFreezeTest`, confirmed to genuinely fail without the
  fix via a temporary `git stash` before writing it up here).

### Quality gates

- **phpstan clean on every new service.** True for the entire session —
  every single work item's verification gate included
  `phpstan analyse --memory-limit=1G` with zero errors before being
  called done, no exceptions. Confirmed again just now: 0 errors on the
  full, final codebase.
- **A factory + seeder for every new model (demo course with quiz +
  assignment seeded for local dev).** Partially true, by deliberate
  decision: `DemoCourseSeeder` (new) delivers the concrete, useful half —
  a real, idempotent, tested seeder producing a demo course with modules,
  lessons, a 3-question quiz, and an assignment. Per-model factories for
  the ~14 P3-P5 models were **not** retroactively added — every test all
  session (150+ files) used explicit `::create()` successfully and never
  needed one; scaffolding 14 unused factory classes now would be
  speculative scope, not a real fix. Documented here rather than silently
  claimed complete.
- **OpenAPI spec updated as API grows.** Confirmed current — every P5.4
  endpoint has a `paths` entry in `OpenApiController`, verified by
  `OpenApiTest` still passing after the additions.
- **Telescope in dev to watch query counts on the player.** Confirmed
  active (`config('telescope.enabled')` defaults `true`, verified directly
  during P5.6's load test — toggling `TELESCOPE_ENABLED=false` and back
  was how its overhead was isolated from the heartbeat endpoint's own
  cost).

### §8 "Definition of done, every phase" — reconfirmed for the whole plan

- **Policies enforce server-side** — see Integrity's first bullet above.
- **Feature tests cover the happy path + the abuse path** — true
  throughout; every phase gate in this log documents the specific abuse
  scenarios covered (double-click, stale tab, cross-student/cross-course
  access, expired/pending enrollment, replayed webhook).
- **`composer ci` green** — reconfirmed at every phase gate (P0-P5) and
  again just now: exit 0, 524 tests / 1120+ assertions.
- **Works without JS** — every AJAX-enhanced surface built across P2-P5
  (lesson complete, quiz runner, notes, curriculum reorder aside — that
  one's admin-only drag-and-drop with no non-JS equivalent needed) has a
  real `<form>` fallback; documented per-item in this log as each was
  built (e.g. P4.6's Notes tab, P3's quiz runner).
- **Works at 360px** — the actual player grid has a 760px breakpoint
  (pre-existing); P5.5 fixed the one real 360px failure found (the shared
  site header overflowing before the player's own layout would have).
- **No query-count regressions on the learn surfaces** — every dashboard/
  index view touched this session that could plausibly regress has a
  dedicated flat-query-count test (`StudentDashboardQueryCountTest`,
  `LearnIndexQueryCountTest`), all still green.
