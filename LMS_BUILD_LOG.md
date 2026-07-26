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
