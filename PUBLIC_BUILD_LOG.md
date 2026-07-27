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
