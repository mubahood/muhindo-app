# AGENT COMMAND — Improve the Public Site Plan, then Execute It

> Paste everything below this line into the coding agent, verbatim.

---

You are the senior Laravel architect-engineer already working in
`/Applications/MAMP/htdocs/muhindo-app`. The operating rules in `AGENT_COMMAND.md`
(read-before-write, no skipping, one item = one commit, verification gate after every
item, worklog discipline, server-side truth, take your time) apply to this entire
mission verbatim.

Your mission has **two stages, in strict order**: first make `PUBLIC_SITE_PLAN.md`
better, then execute the improved plan completely. Do not write a single line of
application code until Stage 1 is finished and committed.

## Stage 1 — Improve the plan itself (plan before doing)

`PUBLIC_SITE_PLAN.md` (project root) is a strong brief for the public, logged-out
experience — e‑Learning catalogue and course pages, student registration and
onboarding, Flutterwave checkout with mobile money/cards/coupons, the
`/start-a-project` client funnel, landing-copy rewording, SEO, responsiveness. It was
written from a code audit and a live browser walkthrough (see its Addendum), but it
was written from *outside* the project. You are *inside* it — you built the LMS
(P0–P5) and the portfolio redesign. You know things its author could not. Sharpen it
into the plan YOU would bet on, so that executing it fully achieves the owner's
goal: a perfect, stable, human-feeling public site that turns strangers into
enrolled students and project clients.

Do this, in order:

1. **Re-verify reality.** Read `LMS_BUILD_LOG.md`, `PORTFOLIO_REDESIGN_PLAN.md`,
   `git log`, the current routes, and open the running site
   (`http://localhost:8888/muhindo-app/`) page by page — including `/courses`, one
   course detail, `/register`, checkout, and every redesigned portfolio page — as a
   stranger would. Note where the plan's Addendum is already stale (e.g. work you
   have committed since it was written).
2. **Critique the plan section by section.** For each of §1–§8 ask: Is this right
   for what actually exists now? Is anything missing that the owner's goal clearly
   needs? Is anything specified in a way that conflicts with code you already built
   (checkout, coupons, previews, reviews, the redesign IA)? Where can you exceed it
   creatively — copy, micro-interactions, onboarding touches, SEO details — without
   changing its data model or scope philosophy?
3. **Rewrite `PUBLIC_SITE_PLAN.md` in place** as version 2: keep its structure,
   voice rules, and intent; correct stale facts; integrate rather than duplicate
   everything the LMS build already provides; resolve every open assumption into a
   decision; replace the Addendum with a fresh "Verified current state" section;
   and tighten the W0–W7 execution order into steps you can gate and test exactly as
   `AGENT_COMMAND.md` demands. Every work item must end with its definition of done.
4. **Record and commit.** Append a Stage‑1 entry to `PUBLIC_BUILD_LOG.md` (create
   it) listing every change you made to the plan and why — the owner will audit this
   diff. Commit the improved plan alone (`docs(public): plan v2 — verified against
   built state`) before any code.

Improvement means sharpening, not shrinking: you may not delete a capability the
owner asked for (e‑Learning naming and catalogue, course detail, registration
onboarding, mobile-money/card checkout with coupons, client project funnel,
landing rewording away from "enterprise only", SEO, responsiveness). If you believe
something should be cut or deferred, keep it in the plan, mark it `DEFERRED` with
one sentence of reasoning, and flag it at the top of the build log for the owner to
decide.

## Stage 2 — Execute the improved plan

Work through your v2 plan W0 → W7 exactly as written, under `AGENT_COMMAND.md`
discipline: one item at a time, pint + phpstan + full test suite green after every
item, tests for happy path and abuse path, works without JS, works at 360px, commit
per item, phase tags (`public-w1` … `public-w7`), worklog entry per item. Seed
real-looking demo content first so every page is built and reviewed against real
courses, never lorem text. Finish with the plan's four-persona walkthrough (student
buying with MTN MoMo and a coupon on a cheap Android; free-course student on
desktop; a school director requesting a project; Googlebot crawling sitemap,
canonicals and JSON-LD), fix everything that feels less than excellent, and confirm
`composer ci` is green from a fresh checkout.

You are finished only when the improved plan has nothing unimplemented, the
walkthrough finds nothing to fix, and both facts are recorded in
`PUBLIC_BUILD_LOG.md` with proof (test names, routes, Lighthouse scores).

Begin now with Stage 1, step 1. Read `PUBLIC_SITE_PLAN.md` end to end first.
