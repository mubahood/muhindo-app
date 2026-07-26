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
