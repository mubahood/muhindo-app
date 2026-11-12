# Where this system stands, and what to fix next

Written 11 November 2026, from the live site at muhindomubaraka.com and the
database behind it.

Read the first section before any of the others. The work this system needs is
not the work it looks like it needs.

---

## 1. The numbers, first

Everything below follows from these. They were counted, not estimated.

| | |
|---|---|
| Registered users | 15 |
| Enrolments | 3 |
| Paid invoices | 4 (of 19 raised) |
| Lesson-progress rows | **1** |
| Course reviews | 0 |
| Certificates issued | 0 |
| Discussion posts | 0 |
| Courses | 25, of which **2** are sellable |
| Lessons | 758 |
| Products published | 8 |
| Products with a downloadable file | **0** |
| Coupons | 1 |

Two readings of this matter.

**The system is pre-launch, not under-featured.** There are 184 admin routes
covering courses, modules, lessons, quizzes, gradebooks, a grading queue,
enrolments, bulk enrolment, invoices, payments, receipts, coupons, products,
projects, clients, analytics and a waitlist. Very little is missing from the
admin in the sense of "a screen that does not exist". One lesson-progress row
across 758 lessons says nobody is using any of it yet.

**So the constraint is the funnel, not the feature set.** Building more admin
screens cannot raise 3 enrolments. The ranked list in section 2 is ordered by
money, not by effort or by how interesting the work is.

A direct consequence: **do not build anything in section 5 or 6 before
finishing section 2.** A coupon engine is worth nothing while the shop cannot
take a sale.

---

## 2. What is costing money today

### 2.1 The shop cannot sell anything — and says it can

Eight products are published. **Zero have a file attached.** `Product::isDeliverable()`
returns false for every one, so each shows "Being packaged" and refuses the
sale. Meanwhile the home page advertises them under:

> "Instant download after payment · MTN MoMo, Airtel Money, Visa or Mastercard"

That is UGX 2.4M of listed inventory — InvetoTrack 450,000, MarketLink 550,000,
HotelPro 280,000, Online Shop 220,000, Android E-Commerce 260,000, Laravel
Admin Starter 150,000, Database Workbook 90,000 — none of it purchasable.

The free Systems Handover Checklist is also unbuyable. That one is the cheapest
fix on this page and the most valuable: it is the lead magnet that turns an
anonymous visitor into an email address.

Three distinct faults, in order of cost:

1. No files uploaded. Content work, not code.
2. The home page promises instant download for items the shop refuses. Fix by
   hiding undeliverable products from the home page rather than by softening
   the promise.
3. "Tell me when it is ready" links to `/hire`, the consultancy page. A person
   who wants a UGX 450,000 download is sent to a page selling bespoke
   development. There is a waitlist table and a working waitlist for courses;
   products do not use it.

### 2.2 The site publishes its own lack of traction

The catalogue prints **"2 enrolled"** and **"1 enrolled"** on the course cards,
while the home page says the instructor is "teaching thousands of people" and
claims 23K subscribers. A buyer reads the small number, not the big one.

Show enrolment counts only once they help. Below a threshold, show something
true and favourable instead: lesson count, hours, last-updated date. The
23K-subscriber audience is the real proof and it is not linked anywhere on the
site — there is no link to the YouTube channel on any page read.

### 2.3 Nothing on the site is in anybody else's words

No student testimonial, no course review, no client quote. The references
section names three credible academics and then asks the visitor to go and
verify them independently. That is honest and unusually confident, and it asks
the visitor to do work they will not do.

The machinery already exists: `CourseReview` (student-facing controller and
all), and testimonials editable in admin and rendered on the home page. Both
are empty. With 3 enrolments there is no review to collect yet, which is
precisely why the 23K-subscriber audience and the named clients have to carry
the proof until students exist.

### 2.4 Twenty-three courses are 404s to Google

Of 25 courses, 2 are sellable. The rest are hidden, so previously-indexed URLs
now return 404 and will drop out of search. That was a deliberate decision
(`catalog.show_unopened`) and it is still worth revisiting: a "notify me" page
that ranks is worth more than a 404.

---

## 3. Public side, A to Z

Ordered by page, with the specific fix. Items marked **(content)** need writing,
not code.

### Home
- Two competing hero CTAs, "Explore e-Learning" and "Hire Muhindo", so the page
  commits to neither business. Pick the primary by margin and make the other
  secondary.
- The HTML/CSS course card renders literal markdown: `you learn real HTML and
  CSS **and** you learn...`. The card prints a raw description through a
  non-markdown view.
- Stat tiles contradict the prose: "700+ Video tutorials" against "Over 200
  tutorials" lower down. One is wrong. **(content)**
- Course cards omit lesson count, hours and the instalment option, all of which
  the course page shows.
- No link to the YouTube channel anywhere, despite it being the single largest
  proof asset.
- Both blog posts read "1 min read".

### /e-learning
- Filters for category, level, price and sort are rendered for a catalogue of
  two courses. Hide filtering below a useful threshold.
- "You will be able to" bullets truncate mid-sentence with ellipses.
- No instalment messaging, though the course page offers UGX 32,500 a quarter.
  The cheaper entry price belongs where people are comparing.
- No ratings, no reviews.

### Course page
This is the best page on the site. The remaining gaps are specific:
- **The instalment terms are undefined.** It says "UGX 32,500 a quarter, paid
  as you go" and "Pay in 4 quarters, same total" but never says whether a
  quarter is a quarter of the course or three months, when each payment is due,
  that later modules stay locked until paid, or what happens if you stop
  paying. This is the single most important copy fix on the site: it is the
  one place a buyer must trust an unfamiliar payment structure. **(content)**
- The FAQ mentions an "access window" the page never states. **(content)**
- Lesson count says 76; module headings sum to 87 (76 plus 11 quizzes).
- About 13 lessons show no duration, most in the final-project modules, which
  reads as unfinished. Either they are text lessons and should say so, or they
  are unrecorded and should not be listed.
- Leftover text appears above the title: "Free preview · ... / Previous Next /
  UGX 130,000 Enrol now".
- Three different labels for one action: "Enrol now", "Buy course", "Sign up to
  enrol".
- "Start here" includes "Setting up Claude AI" while the description and
  requirements mention only GitHub Copilot. **(content)**
- The description promises a "Marigold Stores website"; module 11 builds a news
  list, article page and admissions form. **(content)**
- Requirements say "Basic HTML and CSS" without linking the course that teaches
  exactly that. A bundle of the two is the obvious upsell and does not exist.
- The WhatsApp prefill asks "When does it open?", contradicting the Buy button.
- One free preview out of 76 lessons. Two or three, chosen to show the teaching
  rather than the intro, would convert better.

### Checkout and registration
- Registration takes name, email, password, intent and a referral note. **No
  phone number** — in a market paying by MTN MoMo and Airtel Money, the phone
  number is the payment instrument and the support channel.
- The register page shows UGX 130,000 with no mention of quarters, so the
  cheaper entry disappears at the exact moment of commitment.
- Terms link to `/terms` and `/privacy`; the footer links `/solavia/terms` and
  `/solavia/privacy`. Confirm whether those are the same documents.
- No social sign-in. For this audience Google sign-in removes a real step.
- An image captcha stands between a paying customer and an account.

### Shop
- `/shop` and `/source-code` serve identical content at two URLs. Pick one,
  redirect the other, or they compete in search.
- No screenshots, no live demo, no sample code on any product. The portfolio
  has live links for the same systems; the products selling that code have
  none.
- Product bullets truncate mid-word.
- "InvetoTrack" may be a typo for "InventoTrack". It appears in the product
  name, the course catalogue and the home page. **(content)**

### /about
- No call to action to a course or a product. It mentions "the courses on this
  site" as unlinked plain text. A visitor who reads the whole page and is
  convinced has nowhere to go.
- Names the YouTube channel without linking it.

### Trust, policy and credibility
- The refund policy is narrow and fair, but it is not a money-back guarantee
  and it does not cover the instalment plan. For a first-time buyer of a
  UGX 130,000 course from an unfamiliar site, a plain 14-day guarantee would do
  more for conversion than any feature in this document. **(content, and a
  commercial decision)**
- A registered company with a certificate number uses a Gmail address for
  refunds. A domain address costs nothing and reads differently.
- No certificate sample anywhere, though a certificate is promised on every
  course page and a public verifier exists.

---

## 4. Admin side, A to Z

Start from what is already there. The following all exist and work: courses,
modules, lessons (plus content images, materials, markdown preview, video
fetch), quizzes, questions, quiz analysis, assignments, gradebook with export,
a grading queue, enrolments with notes, bulk enrolment, invoices with PDF and
receipts, payments, Flutterwave gateway logs, coupons, products, projects with
tasks/notes/documents/updates, clients, project inquiries, posts, portfolio,
services, skills, education, experience, gallery, testimonials, reviews, users,
settings, analytics (live, sources, content, visitors), messages, waitlist and
a "today" view.

So this section is short on purpose. These are the genuine gaps.

### 4.1 Student progress is invisible where it matters
`admin.courses.students` exists, and `Enrollment` already denormalises
`progress_percent`, `total_watch_seconds`, `last_lesson_id`, `last_accessed_at`
and `at_risk_reason`. What is missing is the per-student view that answers the
questions a teacher actually asks:

- which lesson is this student stuck on, and for how long
- who has not opened the course since enrolling
- who is mid-quarter and about to hit a paywall
- who finished and has no certificate issued

`DetectAtRiskEnrollments` already computes at-risk reasons on a schedule.
Surface it as a worklist rather than a column.

### 4.2 Refunds work but cannot be reached
`BillingService::refund()` exists, is careful, and is tested: it refuses an
invoice that was never paid, leaves `amount_paid` and the payment rows standing
as the historical record, and stamps `refunded_by` and `refunded_at`.

**There is no route and no button that calls it.** The capability is complete
and unreachable, so honouring the 7-business-day promise in the refund policy
means a Flutterwave dashboard job plus a manual database edit. One route, one
confirm dialog and one policy check would close this, and the service it needs
is already written.

Still genuinely missing underneath it: partial refunds, and any reversal of the
access a refunded invoice paid for — a refunded student keeps their enrolment.

### 4.3 Instalments cannot be managed, only observed
Courses can be sold in quarters, but an admin cannot: see who is part-paid and
on which quarter, waive or extend a quarter for a student who asks, or chase
the ones who stopped. Given the plan is new and 3 students exist this is cheap
now and expensive later.

### 4.4 No revenue view
There are invoices, payments and analytics, but nothing that answers "what did
I earn this month, from which course, after discounts, net of refunds". The
rows exist; the report does not.

### 4.5 Products are missing the fields their own page promises
`products` has no version history, no changelog, no update notification. The
product page promises "a step-by-step install guide" and the licence terms are
a single string. A buyer of a UGX 550,000 codebase will ask for the fix you
shipped last month, and there is no mechanism to deliver it.

### 4.6 No admin audit trail for destructive actions
Enrolments, invoices and courses can be deleted. `spatie/activitylog` is
already installed and used on `Enrollment`. Extend it rather than add anything.

---

## 5. Coupons and promotions

Current state: a real `coupons` table (code, type, value, max_uses, used_count,
expires_at, course_id, is_active), a `CouponService` that validates and redeems
under a row lock, a typed `CouponType`, an admin CRUD, a policy, and a coupon
field on the course page. The race condition on the last remaining use is
already handled correctly.

The gaps, in order of how much they cost:

1. **Courses only.** `CouponService::redeem()` is typed to `Course`. The shop
   checkout has no coupon support at all, so no discount can ever be offered on
   UGX 2.4M of source code — the higher-margin half of the catalogue.
2. **Uses are consumed when the invoice is created, not when it is paid.** A
   50-use launch coupon can be exhausted by people who never pay. With 19
   invoices and 4 paid, that is a 4-in-19 conversion rate against a counter
   that treats all 19 the same.
3. **No per-user limit.** One student can use the same code repeatedly until the
   global cap runs out. There is no "one per customer".
4. **No redemption record.** `used_count` is a bare integer. Nothing records
   who redeemed what, on which invoice, for how much discount. So the question
   a coupon exists to answer — did this campaign make money — cannot be asked.
5. **No `starts_at`.** A Black Friday code cannot be created in advance.
6. **No minimum spend**, so a flat UGX 50,000 off applies to a UGX 90,000
   workbook as happily as to a UGX 550,000 marketplace.
7. **No bulk generation.** A cohort of 40 students needs 40 single-use codes,
   created one screen at a time.

The design that closes 1–6 is in section 7.

---

## 6. Sequence

Each step is worth doing even if the next never happens.

**First — make the shop able to sell.** Upload the files, or unpublish what has
none. Put the free checklist behind an email. Point "tell me when it is ready"
at the waitlist. Hide undeliverable products from the home page.

**Second — stop the credibility leaks.** Hide low enrolment counts. Link
YouTube. Fix the markdown asterisks, the 700-vs-200 contradiction and the
stray preview text. Collect three client quotes.

**Third — write the instalment terms.** The payment structure is the newest and
least familiar thing you ask a buyer to accept, and it is undocumented.

**Fourth — coupons as designed below**, so launch discounts work everywhere and
can be measured.

**Fifth — the admin gaps**: refunds, the instalment worklist, the revenue
report, the at-risk worklist.

---

## 7. Coupon design

What is being built now.

**Scope.** A coupon targets any of: everything, one course, all courses, one
product, all products. Replaces the single nullable `course_id`.

**Limits.** `starts_at` and `expires_at`; `max_uses` globally and
`max_uses_per_user`; `min_subtotal`.

**Redemption ledger.** A `coupon_redemptions` row per use, holding the user,
the invoice, the discount applied and the time. `used_count` becomes a cache of
it rather than the only record.

**Consumed on payment.** A redemption is *reserved* when applied to an invoice
and *confirmed* when the invoice is paid. A reservation that is never paid
expires and returns the use to the pool. This is what makes a limited campaign
mean what it says.

**Reporting.** Per coupon: reserved, confirmed, revenue after discount. The one
number that decides whether to run the campaign again.

---

## 8. What section 7 now does, and what it does not

Built and tested on 11 November 2026.

| | |
|---|---|
| Scope | `any`, `courses`, `products`, or one targeted course or product |
| Limits | `starts_at`, `expires_at`, `max_uses`, `max_uses_per_user`, `min_subtotal` |
| Ledger | `coupon_redemptions`, one row per use, carrying the discount and invoice |
| Lifecycle | reserved on apply → confirmed on payment → released if abandoned or refunded |
| Shop | the basket takes a coupon, and discounts only the lines the code covers |
| Admin | scope and limits on the form; a per-coupon ledger at `admin.coupons.show` |
| Tests | 36 across four files |

Two decisions worth knowing about:

**A reservation holds its slot for `catalog.coupon_reservation_hours` (48) and
then stops counting, read at query time.** No scheduled job sweeps it, so a
campaign heals itself on a host whose cron is not running — which is the normal
state of a small deployment. The cost is that `CouponRedemption::holdingASlot()`
must be used everywhere "uses remaining" is computed.

**A refund hands the use back** rather than leaving it spent, so "50 students
get this price" means fifty who kept the course. The released row keeps the
history.

Not built, and deliberately:

- **Bulk code generation.** A cohort of 40 still needs 40 trips through the
  form. Worth doing when a cohort actually exists.
- **A public "apply and see the new price" step.** `CouponService::quote()`
  exists and is tested — it validates exactly as a real redemption does and
  consumes nothing — but no page calls it yet. Both checkout screens still
  apply the code on submit, so the buyer learns the discount on the next page
  rather than in place.
- **Stacking.** One code per order. Nothing supports two.
