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
