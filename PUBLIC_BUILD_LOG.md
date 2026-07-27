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
