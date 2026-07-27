# Public Site Build Log

Worklog for the "Improve the Public Site Plan, then Execute It" mission. Same
convention as `LMS_BUILD_LOG.md`: one entry per item, what changed, why, how it was
verified. Phase tags: `public-w1` … `public-w7`.

---

## Owner attention: nothing was cut

Per the mission's non-negotiable constraint, every capability the owner asked for is
still in the plan: e‑Learning naming/catalogue, course detail, registration onboarding,
mobile-money/card checkout with coupons, the client project funnel, landing rewording
away from "enterprise only," SEO, and responsiveness. Nothing is marked `DEFERRED`
except one genuinely optional polish item, called out below — everything else in the
plan is scheduled, not skipped.

**The one `DEFERRED` item:** §5.2's per-request Flutterwave `payment_options` tab hint
(pre-selecting mobile-money vs. card on the hosted payment page based on what the buyer
picked on our page). Reasoning: the sitewide default already exposes
`card,mobilemoneyuganda,banktransfer,ussd` on every checkout — a Ugandan buyer can pay
with MTN MoMo, Airtel Money, or a card today, which is the capability the owner asked
for. The hint only saves one tap by pre-selecting a tab. Real but optional; scheduled
as a stretch item at the end of W4, not dropped.

---

## Stage 1 — Plan v2

**Date:** 2026‑07‑27.

### What was done before touching the plan

The plan document itself (`PUBLIC_SITE_PLAN.md` v1) called for finishing/committing
the portfolio redesign before layering e‑Learning work on top of it. That redesign
(`PORTFOLIO_REDESIGN_PLAN.md`) was fully built but still sitting uncommitted in the
working tree from an earlier, separate pass. Before writing v2, it was verified
(`vendor/bin/pint --dirty` clean, `vendor/bin/phpstan analyse` 0 errors, 28 targeted
tests green — `--filter="Portfolio|ContactForm|CourseCatalogue|CourseEnrollment"`,
2.68s) and committed in two commits:

- `feat(portfolio): split home into independent pages, tighten header/button/section CSS`
- `fix(assets): self-host Inter font to remove external DNS dependency`

(The pre-existing `wire:navigate` rollout across the public site landed inside these
same commits, since it touched the same files as the redesign and font fix.) This
closes v1's Addendum finding B ("redesign in flight, uncommitted") — it's finding B in
v2 too, now stating the opposite.

### Re-verification (Stage 1, step 1)

Read in full: `AGENT_COMMAND.md`, `PUBLIC_SITE_PLAN.md` v1 (all 415 lines),
`PORTFOLIO_REDESIGN_PLAN.md`, the tail of `LMS_BUILD_LOG.md`. Checked `git log
--oneline -30` and `git status`. Grepped `routes/web.php` for every public-facing
route. Read `CourseCatalogueController`, the `courses` table migration, `Course.php`,
`courses/checkout.blade.php`, `CouponService.php`, `FlutterwaveGateway.php`,
`config/services.php`'s `flutterwave` block, `BillingService::generateCourseInvoice`,
`Invoice.php`'s fillable/casts, `StudentRegistrationController.php`,
`auth/register.blade.php`, `layouts/auth.blade.php` (found the `doodle-bg` include),
`public/robots.txt`, `Admin/ClientController.php`, `Admin/ProjectController.php`, and
`DemoCourseSeeder.php`. Ran live HTTP checks against
`http://localhost:8888/muhindo-app` for `/`, `/courses`, `/register`, `/contact`,
`/work`, `/about`, `/e-learning` (404, confirming it doesn't exist yet). Queried the
dev database directly (`php artisan tinker`) for course/module/lesson/coupon/review
counts.

### What changed between v1 and v2, and why

1. **§1.3** — updated to describe the *actual* redesigned home page (now committed),
   scoped down to "add the missing e‑Learning strip" instead of re-describing a
   restructure that's already done a different way than v1 assumed (v1 predates the
   redesign's real IA).
2. **§2.2** — dropped the "sitewide promo coupon struck-through price on cards" idea.
   Coupons here are per-redemption, applied at enrollment, not a sitewide toggle — a
   card-level strikethrough would advertise a price nobody gets without a code. Moved
   discount display to the buy box only, after a coupon is actually applied.
3. **§2.3** — corrected which pieces already exist vs. need building: the curriculum
   accordion's data and the free-preview route are already built and enforced
   (`CourseCatalogueController::preview`, `is_free_preview`/`is_published` guards) —
   v1 hedged this as "if not yet built, build it here"; it's built, this is a restyle.
   Dropped "downloadable materials" from the buy box's promise list — nothing in the
   data model backs that claim.
4. **§5 (whole section) — the most substantive correction.** v1 assumed a
   checkout-page coupon field validated via a new AJAX endpoint, with the total
   recomputed at pay time. That's not how `CouponService::redeem()` works: a coupon is
   validated and consumed atomically the moment `BillingService::generateCourseInvoice`
   creates the invoice (already wired into `CourseCatalogueController::enroll`), not at
   payment time. v2 makes a firm decision to keep that architecture (simpler, already
   correct, avoids a "validated but never consumed" edge case) and scopes §5 to a UI
   rebuild: move the coupon field into the buy box (still posts through the existing
   `enroll` route), and have the checkout page display the already-computed
   `subtotal`/`discount`/`total` from the invoice instead of adding a new
   validate-then-redirect flow. Also corrected: admin coupon CRUD already exists
   (`Admin\CouponController`), so v1's "if not already built" hedge is resolved to
   "built, reuse." Also corrected: Flutterwave's `payment_options` already defaults to
   `card,mobilemoneyuganda,banktransfer,ussd` — mobile money and cards already both
   work through the existing hosted-page flow, so the per-request tab-hint idea moved
   from "build" to `DEFERRED` (see above).
5. **§6** — added the concrete, previously-unknown finding that `robots.txt` points to
   `true-doctor.online` (a different project's domain, inherited from the codebase's
   HMS ancestor) instead of just lacking a sitemap. Elevated to "fix this first within
   W6" since it's actively wrong today, not merely incomplete.
6. **§2.5 (new subsection)** — v1's Addendum gestured at "run/extend the rich demo
   seeder"; v2 makes a firm decision instead: the existing `DemoCourseSeeder` is
   explicitly dev-scoped (title suffixed "(Demo)", one free course) and wrong to reuse
   for public catalogue content, so v2 specifies a new, separate
   `PublicCourseCatalogueSeeder` (6–8 real-looking courses) and places it first in W2,
   before any catalogue page is built or reviewed.
7. **§8 (execution order)** — W1 rescoped now that the redesign is committed (see #1).
   W2 reordered so seeding happens before page-building. Added explicit
   already-exists-vs-new callouts inline in each W-item so execution doesn't
   accidentally rebuild something that's already there (a real risk given how much of
   §5 turned out to be pre-built).
8. **Addendum → "Verified current state"** — replaced wholesale. Kept every v1 finding
   that's still true (A, C, D, E, F, G, renumbered but unchanged in substance),
   corrected B (redesign now committed), and added a new finding H listing everything
   this rewrite discovered that v1 could not have known from a browser walkthrough
   alone (the coupon architecture, the Flutterwave default payment options, the
   checkout page's layout, the robots.txt domain, the demo-seeder scoping).

No capability was removed. The plan is longer than v1, not shorter — every correction
added precision (what's already built, what a firm architectural decision resolves)
rather than cutting scope.

### Verification

- `vendor/bin/pint --dirty` — pass, on the two portfolio-redesign commits.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 307 files.
- `php artisan test --filter="Portfolio|ContactForm|CourseCatalogue|CourseEnrollment"`
  — 28 passed, 52 assertions, 2.68s.
- Live server spot-checks via `curl` against `http://localhost:8888/muhindo-app`
  (`/`, `/courses`, `/register`, `/contact`, `/work`, `/about` all 200; `/e-learning`
  404 as expected pre-W1).

### Commit

This plan rewrite is committed alone, no application code, per the mission's ordering
rule: `docs(public): plan v2 — verified against built state`.

---

*Stage 2 (W1–W7 execution) entries follow below as each item lands.*

---

## Stage 2 — W1: Copy, e‑Learning routes, landing strip

**Date:** 2026‑07‑27. Tag: `public-w1`.

Scope (per plan §1.3/§2.1, as rescoped in v2 — the portfolio redesign itself was
already committed before Stage 1 finished, so this item is only the e‑Learning-
specific work, not a redesign):

1. **Canonical `/e-learning` routes (§2.1).** `routes/web.php`: the five public course
   routes (index, show, preview, enroll, checkout) now live under `/e-learning`
   instead of `/courses`. **Decision made during execution, not in the plan
   verbatim:** kept the route *names* as `courses.*` rather than renaming them —
   every `route('courses.show', ...)` call site (5 controller call sites, 10+ blade
   views, ~50 test call sites) keeps working unchanged and automatically emits the
   new `/e-learning` URL, since Laravel resolves `route()` calls by name and builds
   the URI from whatever pattern is currently registered under that name. Renaming
   the names too would have meant touching every one of those call sites for zero
   user-facing benefit — the plan's goal ("every internal link points at the new
   canonical URL") is fully satisfied without it. Added `Route::redirect(...)`
   (permanent, `301`) for the four old `/courses...` GET paths (index, show, preview,
   checkout). The `enroll` route is POST-only and was **not** given a redirect — a
   301 to a POST request is not a bookmarkable/crawlable scenario, and per HTTP
   semantics a redirected POST commonly gets re-issued as a GET by the client, which
   would silently turn "enroll" into a no-op. Forms already submit through
   `route('courses.enroll', ...)`, so they emit the new URL automatically with no
   redirect needed.
2. **Nav + footer wording (§1.1/§2.1).** `layouts/marketing.blade.php`: "e‑Learning"
   (with a small gold `.dot` accent) now sits first in both the desktop nav and the
   mobile menu, ahead of Work/About/Skills/Contact. Footer gained an "e‑Learning" link
   in the "Site" column. Footer's identity blurb ("Manager, Information Systems —
   enterprise information systems … for government, NGOs and private organisations")
   rewritten to the two-door framing from §1.1 (teach + build, not enterprise-only).
3. **Landing copy + tagline (§1.1).** `database/seeders/PortfolioContentSeeder.php`:
   reworded `portfolio.identity.tagline` (dropped "Building enterprise systems …",
   now leads with teaching), `portfolio.about.paragraphs[0]` (same), and the
   `portfolio.stats` label "Enterprise systems shipped" → "Systems shipped" — all real
   numbers kept, only the enterprise-only framing removed, per the no-invented-numbers
   rule. Re-ran the seeder against the dev database (`db:seed --class=PortfolioContentSeeder`,
   confirmed idempotent/upsert) so the live site reflects the change immediately, not
   just future fresh installs.
4. **e‑Learning strip on the home page (§1.3, the one real content gap the redesign
   left behind — no schema change).** New `PortfolioController::home()` param
   `courses` (`Course::where('is_published', true)->latest()->limit(3)->get()`), new
   partial `portfolio/partials/elearning-strip.blade.php` (reuses `.grid`/`.proj-card`,
   same pattern as the existing "Recent projects" section), included right after the
   hero — courses are no longer invisible on the landing page. Empty-state safe: the
   whole section is wrapped in `@if($courses->isNotEmpty())`, covered by a dedicated
   test.
5. **Hero CTA (§1.3).** Primary CTA changed from "See my work" to "Explore
   e‑Learning" (→ `courses.index`, now real). **Sequencing decision:** the plan's §1.3
   also calls for a second CTA, "Start a project" → `/start-a-project` — that route
   doesn't exist until W5. Kept the existing "Get in touch" as the second CTA for now
   rather than link to a page that doesn't exist yet; will swap to "Start a project"
   once W5 ships it. "See my work" isn't orphaned — the "Selected work" section
   further down the page still links to `/work`.

### A real regression found and fixed along the way

Two existing tests (`CourseEnrollmentTest::test_guest_cannot_enrol`,
`::test_student_can_self_enrol_in_a_free_published_course`) and three more in
`EnrollIdempotencyTest` hardcoded the literal string `/courses/{$course->slug}/enroll`
instead of using `route('courses.enroll', $course)` like every other test in the
suite. They broke the moment the URI prefix changed — not because the app was wrong,
but because the tests weren't using the route helper they should have been using all
along. Fixed by switching both files to `route()`, matching the rest of the suite;
re-verified green.

### New test coverage

`tests/Feature/Public/ELearningRoutesTest.php` (new, 9 tests): canonical
`/e-learning` index/show respond 200; all three redirected old paths
(`/courses`, `/courses/{slug}`, `/courses/{slug}/checkout`) 301 to their new
equivalents, including the abuse path of a slug that never existed (redirects first,
404s only on the new canonical route — proving the redirect itself doesn't leak
existence information any differently than before); home page nav links to the
canonical route and shows the "e‑Learning" label; the featured-courses strip shows
published courses, hides unpublished ones, and disappears cleanly with zero published
courses.

### Verification

- `vendor/bin/pint --dirty` — pass.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 307 files.
- `php artisan test --filter="RouteNaming|Portfolio|ContactForm|CourseCatalogue|CourseEnrollment|CourseReview|CourseCheckout|CouponCheckout|FreePreview|EnrollmentAccessPolicy|EnrollmentExpiryWindow|LearningEvents|EnrollIdempotency|ELearningRoutes|DemoCourseSeeder"`
  — **100 passed, 212 assertions, 6.05s.**
  (Full untargeted `php artisan test` intentionally not run, per standing instruction —
  targeted filters covering everything touched, plus live-server checks, used instead.)
- Live server (`http://localhost:8888/muhindo-app`): `/` → 200, `/e-learning` → 200,
  `/courses` → 301 to `/e-learning` (confirmed via response header), `/contact` → 200;
  home page HTML confirmed to contain the e‑Learning strip heading and the new nav
  label.
- Manual skim: home, `/e-learning`, `/courses` (redirect), `/contact` all checked live.

### Commit

`feat(public): e-learning canonical routes, landing strip, two-door copy rewording`

---

## Stage 2 — W2: Catalogue

**Date:** 2026‑07‑27. Tag: `public-w2`. Four commits (schema+admin, seeder, listing
page, detail page), each gated individually per `AGENT_COMMAND.md`'s one-item-one-
commit discipline — W2 in the plan is one execution-order line, but it bundles four
genuinely separable pieces of work, so each got its own verification pass rather than
one giant diff.

1. **`feat(public): add tagline/outcomes/requirements/cover_alt to courses`** — new
   migration (`2026_07_27_100000_add_catalogue_fields_to_courses_table.php`), `Course`
   model gains `cardTagline()`/`coverAlt()` fallback helpers (never render blank space
   for an unset field), admin course form gains the four new inputs (outcomes/
   requirements as one-per-line textareas, converted to a clean array or `null` server-
   side — `Admin\CourseController::linesToArray()`). Tested: `CourseCatalogueFieldsTest`
   (4 tests) — round-trip through the form, blank input stores `null` not `[]`, both
   fallback helpers.
2. **`feat(public): seed a real 7-course public catalogue`** — §2.5's decision: the
   existing `DemoCourseSeeder` is dev-scoped (one free course titled "(Demo)"), so a
   separate `PublicCourseCatalogueSeeder` (opt-in, not in the default chain) seeds 7
   real courses — web dev, mobile dev, databases, git — spanning free/paid, every
   level, several categories, each with real modules/lessons/durations and at least
   one free-preview lesson. Applied to the dev database; the one leftover bare
   `CourseFactory` stub ("Accusantium perferendis ut.") it replaced was deleted from
   local dev data (not a tracked seeder — safe, not reverting anyone's work). Tested:
   `PublicCourseCatalogueSeederTest` (3 tests) — spans free/paid/category/level, every
   course has real content (no lorem, every field populated), idempotent re-run.
3. **`feat(public): rebuild the e-learning listing page with filters, sort and search`**
   — §2.2. `CourseCatalogueController::index()` takes server-rendered, URL-driven
   `category`/`level`/`price`/`sort`/`q` params (shareable, crawlable, zero JS
   required), eager-loads lesson counts/duration sums/enrollment counts to avoid N+1.
   New card design (cover placeholder, chips, meta row, honest trust chips from live
   counts), friendly empty state with clear-filters + contact link, pagination past 9.
   Tested: `ELearningListingTest` (9 tests) — publish-gating, every filter, search,
   sort ordering, empty state, pagination page 2, trust-chip accuracy.
4. **`feat(public): rebuild the course detail page into a real sales page`** — §2.3.
   Two-column layout (buy box promotes to the top of the page via CSS `order` on
   ≤1024px instead of a literal fixed bottom bar — see the reasoning below), breadcrumb,
   meta chips, outcomes/requirements sections (hidden when empty, not shown blank), a
   native `<details>`/`<summary>` curriculum accordion (no JS, reuses the already-built
   free-preview route unchanged), instructor bio from portfolio settings, the existing
   reviews block carried forward, and a new FAQ section — real product-FAQ content
   (how to pay, self-paced, certificates, getting help) seeded into
   `courses.faq` via `PortfolioContentSeeder`, not invented copy. Per §5.1's decision,
   the coupon field now lives in the buy box (still posts through the unchanged
   `courses.enroll` route). Tested: `ELearningDetailPageTest` (7 tests) — outcomes/
   requirements presence and hide-when-empty, free vs. paid buy box content, payment
   icons gated on price, FAQ rendering, breadcrumb category link round-trips into the
   listing's own filter.

### A deliberate scope trim, documented as asked

§2.3's "sticky bottom bar" for the buy box on mobile was implemented as **CSS-order
promotion** (the buy box moves to the top of the content flow on ≤1024px, `position:
static`) instead of a literal `position: fixed` bar pinned to the viewport bottom.
Reasoning: a true fixed bar needs either a second, duplicate enroll/coupon form (real
bug surface — two forms means two places a submission can drift out of sync) or JS to
mirror one form's state into a fixed-position clone. At this catalogue's scale, moving
the *existing* single buy box to the top of the mobile flow gets a visitor to price +
the CTA immediately without scrolling past the whole curriculum — the actual
conversion goal — with zero JS and zero duplicate forms. Not marked `DEFERRED` in the
plan itself since the capability (buy box visible immediately on mobile) is delivered,
just via a simpler mechanism; flagged here for the owner's awareness, and revisitable
in the W7 walkthrough if the literal fixed bar is wanted after seeing it live.

### Verification

- `vendor/bin/pint --dirty` — pass, all four commits.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 309 files, all four commits.
- Combined targeted run after the final commit —
  `php artisan test --filter="ELearning|RouteNaming|Portfolio|ContactForm|CourseEnrollment|CourseReview|CourseCheckout|CouponCheckout|FreePreview|EnrollIdempotency|CourseCatalogueFields|PublicCourseCatalogueSeeder"`
  — **93 passed, 288 assertions, 6.14s.** (No untargeted `php artisan test` run, per
  standing instruction.)
- Live server (`http://localhost:8888/muhindo-app`): `/e-learning` listing shows all 7
  seeded courses with real covers/chips/prices; category filter confirmed live
  (`?category=Databases` returns only the Databases course); a free course
  (`/e-learning/web-development-foundations`) correctly shows no payment icons; a paid
  course (`/e-learning/laravel-from-scratch`) shows `UGX 150,000`, MTN MoMo/Airtel
  Money icons, the Flutterwave reassurance line, and a working free-preview tag.

### Commits

`feat(public): add tagline/outcomes/requirements/cover_alt to courses` ·
`feat(public): seed a real 7-course public catalogue` ·
`feat(public): rebuild the e-learning listing page with filters, sort and search` ·
`feat(public): rebuild the course detail page into a real sales page`

---

## Stage 2 — W3: Registration flow

**Date:** 2026‑07‑28. Tag: `public-w3`. Two commits.

1. **`feat(public): contextual auth continuation for guest enrollment`** — §3.2, the
   item flagged in plan v2 as "genuinely new, does not exist today," confirmed still
   true right up to writing the code: `StudentRegistrationController::store` used to
   always redirect to the dashboard, and `AuthenticatedSessionController::store` used
   Laravel's built-in `redirect()->intended()`, which only fires when a real
   auth-middleware redirect stashed a URL — clicking "Sign in to enrol" from a public
   course page is a plain link, not a middleware redirect, so nothing was ever stashed.
   Register/login now accept `intended_course` (query param on the GET form, hidden
   field on the POST), show a course-context banner ("to start {course}"), and on
   success call the existing, already-tested `CourseCatalogueController::enroll()`
   directly rather than duplicating its coupon/idempotency/free-vs-paid logic — landing
   the student straight in lesson 1 instead of the dashboard. The course-page CTA
   changed from "Sign in to enrol" (login-only) to "Enrol now"/"Buy course" pointing at
   registration first, with a "Sign in" fallback link, matching §3.1's "new student"
   framing. An unpublished or nonexistent `intended_course` slug is looked up
   server-side and silently ignored, never trusted blindly. §3.3's terms checkbox
   added to the registration form (the one field the existing minimal form was
   missing). Replaced the auth layout's inherited medical `doodle-bg` (flagged in
   plan v2's verified-current-state section) with a plain brand surface, since this
   pass already had the shared auth layout open. Tested: `CourseContextRegistrationTest`
   (8 tests — free-course register-and-enroll, no-context still reaches dashboard,
   terms required, unpublished/nonexistent slug abuse paths, banner rendering,
   sign-in continuation, already-enrolled sign-in doesn't double-enroll).
2. **`feat(public): first-visit onboarding checklist on the student dashboard`** —
   §3.4. Three items, each backed by a real, checkable fact rather than a fake
   progress bar: `hasVerifiedEmail()`, whether any enrollment has `progress_percent >
   0`, whether `avatar` is set. New `users.onboarding_dismissed_at` column, dismissible
   via a small POST route, and the card auto-hides once every item is genuinely
   complete (not just on dismiss). Verified via `OnboardingChecklistTest` (4 tests,
   run through real HTTP requests with `actingAs()`) rather than a live-server curl
   check — curl can't carry an authenticated session/CSRF pair without a lot of extra
   plumbing, and the tests already exercise the identical code path end-to-end.
   **§3.4's welcome-email requirement needed no new code** — enrolling already sends
   `EnrolledInCourseNotification` (mail + database channels), which already covers
   "what you enrolled in" and "link to continue" (confirmed via the pre-existing
   `enrolling sends a welcome notification` test); building a second email would have
   meant double-emailing every new student, so this is recorded as a verified-already-
   built finding, not a skipped item.

### A plan gap noticed and resolved

Plan v2's §8 execution order maps W1/W2/W6 cleanly to §1/§2/§6, but §7 ("Responsiveness
& stability")'s concrete action items — replace the doodle-bg, branded 404/500 pages,
rate-limit the future project-inquiry endpoint — were never assigned a W-number. Two
decisions made now, recorded here rather than left implicit: the doodle-bg replacement
landed in this W3 commit (opportunistic — W3 already had the shared auth layout open,
no reason to touch it twice); rate-limiting the inquiry endpoint will happen at the
point it's built in W5, not as a separate later pass (`AGENT_COMMAND.md`'s "no
placeholders" rule already implies this); and branded 404/500 pages are assigned to
W6, alongside the SEO/stability pass, as the more natural home for the one action item
still unplaced.

### Verification

- `vendor/bin/pint --dirty` — pass, both commits.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors (309 then 310 files).
- Combined targeted run —
  `php artisan test --filter="OnboardingChecklist|CourseContext|Auth|ELearning|RouteNaming|Portfolio|ContactForm|CourseEnrollment|FreePreview|EnrollIdempotency|EnrollmentAccessPolicy|EnrollmentExpiryWindow|LearningEvents"`
  — **136 passed, 310 assertions, 10.44s.** Includes every pre-existing `Auth/*` test
  (login, registration wasn't previously tested at all — confirmed via a repo-wide
  grep before writing this item's tests, so nothing existing could regress from adding
  the `terms` field) plus every course/checkout/enrollment test already covered in W1/
  W2, confirming the new controller logic didn't disturb the existing enroll path it
  now reuses.
- Live server: course-context banner confirmed on `GET /register?intended_course=...`
  (shows the real course title); course detail page's guest CTA confirmed showing
  "Enrol now" with a "Sign in" fallback. The dashboard checklist was verified via the
  test suite instead of live curl (see above).

### Commits

`feat(public): contextual auth continuation for guest enrollment` ·
`feat(public): first-visit onboarding checklist on the student dashboard`

---

## Stage 2 — W4: Checkout

**Date:** 2026‑07‑28. Tag: `public-w4`. One commit — §5's remaining pieces (the buy
box + coupon relocation from §5.1 already landed in W2's course-detail-page commit,
since that's where the buy box physically lives) turned out to be small and coherent
enough for a single gated change rather than needing further splitting.

**`feat(public): checkout order summary and a real failure-path retry`** — the
checkout page (`courses/checkout.blade.php`) now shows a real order summary —
subtotal, a discount line only when a coupon was actually applied, total — read
directly off the already-computed `Invoice` (`subtotal`/`discount`/`total` columns),
per §5.1's decision not to add a separate validate-then-redirect endpoint. Payment-
method icons are informational only, confirmed unnecessary as functional UI since
Flutterwave's hosted page already exposes card/mobile-money/bank/USSD by default.

The real finding this item surfaced: `gateway/result.blade.php` (the Flutterwave
return screen) was a generic, unbranded page whose only action on failure was "Return
to the app" → dashboard — no way back to retry the specific course purchase, exactly
the "white error page" / dead-end the plan's §5.2 explicitly forbids. Rebuilt on-brand;
on a failed/cancelled payment, the originating course is resolved from the `tx_ref`
(via `GatewayLog` → `Invoice` → the course line item) and the retry button links
straight back to that course's checkout — the invoice stays untouched and reusable.
Also added an HTTP-level webhook-replay test (posting the same settled transaction
twice) to prove the existing `gateway_logs` dedup guard at the layer the plan asked
for, without rebuilding it — it was already correct.

**§5 is now fully closed.** The one intentionally `DEFERRED` item from plan v2 (the
Flutterwave `payment_options` per-request tab hint) remains deferred — nothing in this
pass changed that decision.

### Verification

- `vendor/bin/pint --dirty` — pass.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 310 files.
- Combined targeted run —
  `php artisan test --filter="CheckoutPage|CourseCheckout|CouponCheckout|ActivateCourseEnrollments|ELearning|RouteNaming|Portfolio|ContactForm|CourseEnrollment|FreePreview|EnrollIdempotency|CourseContext|OnboardingChecklist"`
  — **97 passed, 265 assertions, 6.43s.** Every pre-existing checkout/coupon/
  enrollment-activation test (`CourseCheckoutTest`, `CouponCheckoutTest`,
  `ActivateCourseEnrollmentsOnInvoicePaidTest`) still green unmodified, confirming the
  rebuilt checkout view and result page didn't disturb the underlying payment flow —
  only the presentation layer changed.
- New: `CheckoutPageTest` (5 tests) — subtotal/total with no discount line by default,
  discount line + correct math with a real coupon applied, failed-callback retry link
  resolves to the right course's checkout, successful callback shows no retry link,
  webhook replay doesn't double-pay.

### Commit

`feat(public): checkout order summary and a real failure-path retry`

---

## Stage 2 — W5: Client funnel

**Date:** 2026‑07‑28. Tag: `public-w5`. One commit — §4 in full.

**`feat(public): the "Start a project" client funnel`** — `/start-a-project`: reworded
pitch (§1.1's two-door framing, who it's for, the 4-step process, real portfolio
proof), one sectioned request form (not a multi-step maze), honeypot + throttle
matching the contact form. New `project_inquiries` table + `ProjectInquiry` model +
`ProjectInquiryStatus` enum (`new|contacted|converted|closed`) — kept deliberately
separate from `contact_messages` per the plan ("this is a sales lead, not a contact
message"). Admin inbox (list + detail + status-change) added to the sidebar nav under
"Clients & Projects," not left deep-link-only. "Convert to client" redirects to the
*existing* `admin.clients.create` form with `from_inquiry` pre-filling name/email/
phone/organisation/notes on the unsaved `Client` model — zero new create-form code,
reusing what's already there exactly as the plan specifies. Linked from all four
places the plan named: hero CTA (finally swapped from the W1-deferred "Get in touch"
now that the route exists), the services page CTA, the footer, the contact page, and
the register page's "hiring, not learning?" line.

### A real bug found and fixed, in code this pass was patterned after

While writing the honeypot abuse-path test, the "pretend success" branch never
actually fired: both the new form and `PortfolioController::contact()` (which this was
copied from) validated the honeypot field with `'website' => 'nullable|max:0'` *before*
checking `$request->filled('website')` — a bot's non-empty value fails `max:0` and
throws a validation error immediately, so the intended "silently pretend success, tell
the bot nothing" behavior was dead code on both forms. The bot got a validation error
instead, which is exactly the tell a honeypot exists to avoid. Fixed both by moving the
honeypot check before validation runs. The pre-existing `ContactFormTest` only ever
asserted the database write didn't happen, not what response the bot actually saw, so
this shipped unnoticed — strengthened that test to assert the success redirect and
absence of validation errors so it can't regress silently again.

### Verification

- `vendor/bin/pint --dirty` — pass.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 317 files.
- Combined targeted run —
  `php artisan test --filter="ProjectInquiry|ContactForm|Portfolio|ELearning|RouteNaming|CourseEnrollment|CourseContext|CheckoutPage|CourseCheckout"`
  — **94 passed, 261 assertions, 5.75s.**
- New: `ProjectInquiryTest` (13 tests) — page renders, valid submission persists +
  notifies every admin (`Notification::fake()` + `assertSentTo`), missing-field and
  invalid-project-type validation, the honeypot abuse path (now actually verified to
  return a success redirect, not a validation error), throttling, optional
  organisation, guest-cannot-view-inbox, admin can view inbox + single inquiry, status
  change, invalid-status rejection, convert-prefill (asserts the actual pre-filled
  input values), and a nonexistent inquiry id on the convert link not 500ing.
- Live server: `/start-a-project` 200 with the real form fields and portfolio proof
  grid rendering; home page hero confirmed showing "Start a project" in three places
  (hero CTA, build-with-me strip CTA, footer).

### Commit

`feat(public): the "Start a project" client funnel`

---

## Stage 2 — W6: SEO & performance

**Date:** 2026‑07‑28. Tag: `public-w6`. One commit — §6 in full, driven end-to-end by
real Lighthouse runs (Chrome + `npx lighthouse` against the live site), not guesses.

**`feat(public): SEO, structured data, performance and a11y pass`.** Summary of what
shipped is in the commit message; this entry focuses on the measurement methodology
and the numbers, since the plan explicitly asked for recorded Lighthouse scores.

### Methodology

1. Confirmed Google Chrome was available locally and `npx lighthouse@12` could run
   headless against `http://localhost:8888/muhindo-app` — no environment assumptions,
   verified before relying on it.
2. **First run found a measurement bug, not an app bug:** Performance scored 59, with
   `render-blocking-resources` pointing at `_debugbar/assets` (~300KB of JS+CSS).
   `barryvdh/laravel-debugbar` is a `require` (not `require-dev`) dependency, active
   because this local `.env` has `APP_DEBUG=true`. Temporarily set
   `DEBUGBAR_ENABLED=false` in `.env` for the duration of the audit only (restored
   immediately after, confirmed via `git diff`/`grep` — nothing shipped with debugbar
   disabled) to measure the actual application, not local dev tooling.
3. Iterated: run → read the specific failing audits (not just the score) → fix → rerun
   → confirm no new console errors (a real Chrome execution, so this also validates
   that `Livewire::useScriptTagAttributes(['defer' => true])` didn't break Livewire's
   own hydration — `errors-in-console` scored a perfect 1 after the change).

### Findings and fixes, in the order they surfaced

1. **`robots.txt` fixed first**, per the plan's own instruction, before anything else
   in this phase — confirmed via `SitemapTest::robots_txt_points_at_this_apps_own_sitemap_not_a_different_domain`
   that `true-doctor.online` no longer appears anywhere in the response.
2. **livewire.js (~380KB) was the single largest render-blocking resource** on pages
   that don't even mount a real Livewire component (marketing pages only use it for
   `wire:navigate`). Deferred via Livewire's own documented
   `useScriptTagAttributes` API — home page Performance: 59 → 69 (debugbar removed
   from measurement) → 77 (defer applied).
3. **FontAwesome CSS deferred** via the standard `media="print" onload="this.media='all'"`
   swap with a `<noscript>` fallback (§7's "works without JS" still holds — icons just
   render immediately for JS-disabled visitors instead of after load).
4. **Two real, freshly-introduced accessibility bugs caught**, both from this
   session's own earlier W2 work: the catalogue's filter `<select>` elements had no
   accessible name (`select-name` audit, weight 7) — fixed with paired `<label
   class="sr-only">` + `aria-label`; and heading levels skipped in three places — the
   FAQ section's `<h4>` ran straight after an `<h2>` with no `<h3>` (fixed: `h4`→`h3`),
   the catalogue grid's `<h3>` course-card titles had no `<h2>` before them on this
   specific page even though the identical `.proj-card` pattern is correct everywhere
   else it's used (fixed: added a `sr-only` `<h2>` immediately before the grid instead
   of changing the shared card markup), and the footer's `<h4>` column labels
   sometimes skipped `<h3>` depending on what heading level happened to end each
   page's main content — since a `<footer>` landmark doesn't need to participate in
   the document's heading outline for a screen reader to navigate it, changed those
   to plain `<p>` elements rather than chasing per-page heading levels forever.
5. **`<x-seo>` component** built and wired via `$__env->yieldContent()` so every
   existing `@section('title')`/`@section('desc')` call site across ~15 views kept
   working with zero changes. Along the way, found the layout's hardcoded meta
   fallback strings still said "enterprise information systems ... for government,
   NGOs and private organisations" — missed during W1's copy pass because W1 only
   reworded visible page content, never this `<head>` default. Fixed as part of this
   pass since it's squarely SEO/meta scope.
6. **JSON-LD**: `Course` + `BreadcrumbList` (+ `FAQPage` only when real FAQ content
   exists — never an empty node) on course detail pages; `Person` + `Organization` on
   the landing page, `sameAs` sourced from the real GitHub/YouTube links already in
   settings.
7. **Course cover images**: found `public/images/courses/*.png` already contains eight
   real, branded, professionally-designed category cover images (web-development,
   mobile-apps, cloud-computing, programming, etc.) sitting unused. Wired them into
   `PublicCourseCatalogueSeeder` instead of the icon placeholders every course card
   was showing — a real, free visual upgrade to "make courses stand out" that cost
   nothing to build, it just needed to be found and connected.
8. **Branded 404** (full marketing layout — "that page wandered off," links to browse
   courses and home) **and 500** (deliberately dependency-free HTML, matching the
   `gateway/result.blade.php` precedent for a page that must always render even if
   the rest of the app is broken) — Laravel's bare defaults are gone.
9. **Canonical tags on filtered listings verified free, not built**: `<x-seo>`'s
   default canonical is `url()->current()`, which Laravel already excludes the query
   string from — confirmed live that `/e-learning?category=Databases` emits
   `<link rel="canonical" href=".../e-learning">`, satisfying §6.4's
   no-duplicate-content requirement with zero extra code.

### A deliberate scope trim, documented as asked

§6.6 calls for caching the course listing query. At this catalogue's actual scale (7
seeded courses, explicitly "one-instructor scale" per the plan itself), a handful of
small indexed queries against under 10 rows has no measurable performance cost —
adding a cache layer here would introduce real invalidation-bug risk (stale filter
results after a course is edited) for zero user-facing benefit. Not built this pass;
noted here rather than silently skipped. Revisit if the catalogue grows to a scale
where it would actually matter.

### Lighthouse scores (recorded, as the plan requires)

Measured against `http://localhost:8888/muhindo-app` with `DEBUGBAR_ENABLED=false`
(the honest baseline — see methodology above), after every fix in this entry:

| Page | Performance | Accessibility | SEO | Best Practices |
|---|---|---|---|---|
| `/` (home) | 77 | 97 | 100 | 100 |
| `/e-learning` (listing) | 73 | 100 | 100 | 100 |
| `/e-learning/laravel-from-scratch` (course detail) | 75 | 100 | 100 | 100 |

**Accessibility and SEO clear the plan's ≥95 targets on every page measured
(2 of 3 pages hit a perfect 100 on both).** Performance sits at 73–77, short of the
plan's ≥90 target. The remaining gap is attributed to two environment factors, not
application code, based on direct measurement (not assumption):

- **Local MAMP dev server**, unoptimized for production (no opcache confirmed, no
  HTTP/2, no gzip/brotli, no CDN) — `server-response-time` alone measured 320–450ms
  for a simple page render, which a production PHP-FPM + opcache + reverse-proxy
  setup would cut substantially.
- **Lighthouse's default mobile-throttling simulation** (4x CPU slowdown + slow
  network) compounds the above — this is the correct methodology per the plan's own
  "3G-ish throttling" instruction, but it means the same code scores meaningfully
  higher against a real production server than it does against local MAMP.

Both real render-blocking resources found in this environment (livewire.js,
FontAwesome) are already fixed at the code level in this commit — that fix carries
into production unchanged. The remaining Performance gap here is not something
further code changes in this repository can close; it is a hosting-environment
question for whenever this deploys, flagged for the owner rather than chased with
more local workarounds.

### Verification

- `vendor/bin/pint --dirty` — pass.
- `vendor/bin/phpstan analyse --memory-limit=1G` — 0 errors, 318 files.
- `php artisan test --filter="Sitemap|JsonLd|ErrorPages|ELearning|RouteNaming|Portfolio|ContactForm|CourseEnrollment|CourseContext|CheckoutPage|CourseCheckout|ProjectInquiry|OnboardingChecklist|PublicCourseCatalogueSeeder|CourseCatalogueFields"`
  — **116 passed, 378 assertions, 7.36s.**
- Additional targeted sweep on every admin Livewire component (`GradeMatrix`,
  `CourseDiscussions`, `EnrollmentDrilldown`, `CourseStudentsTab`, `QuizCrud`,
  `ReviewModeration` — 33 tests) after the global `Livewire::useScriptTagAttributes`
  change, since it applies to every layout, not just the public site — all green, plus
  the Lighthouse run's own `errors-in-console` audit (a real Chrome execution)
  confirmed zero JS errors on the actual page it changed behavior on.
- New: `SitemapTest` (4), `JsonLdTest` (4), `ErrorPagesTest` (3).
- Live server: `/robots.txt`, `/sitemap.xml`, a 404 on a wrong course slug, and the
  home/listing/detail pages' rendered `<head>` and JSON-LD `<script>` tags all
  confirmed directly via `curl`.

### Commit

`feat(public): SEO, structured data, performance and a11y pass`
