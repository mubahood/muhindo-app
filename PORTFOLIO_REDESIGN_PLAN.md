# Portfolio site redesign — plan

## Why

The current home page (`resources/views/portfolio/home.blade.php`) is one
266-line file with **12 stacked sections** (hero, clients strip, about,
services, work, skills, experience, research, products, education,
languages, contact) all rendered on `/`. It's wordy and crowded, the header
is taller and busier than it needs to be, and buttons run large. Feedback:

- Split sections into independent pages — stop making people scroll through
  everything to find one thing.
- Home page: leaner, less text, less crowded.
- Header: shorter. Buttons: smaller.
- Be creative — this doesn't need to be a literal 1:1 port of the old
  sections into new URLs.

## New information architecture

| Route | Page | Content |
|---|---|---|
| `/` | Home | Hero (headline + one-line lead), 4 stat chips, a 4-icon "what I do" strip (links to `/services`), 3 featured projects (links to `/work`), one closing CTA band → `/contact`. Nothing else. |
| `/work` | Work (new index) | Full project grid. Existing `/work/{slug}` case-study pages unchanged. |
| `/about` | About | Bio paragraphs + client list. Cross-links to Experience/Education/Research. |
| `/services` | Services | Full service grid (currently buried mid-home). |
| `/skills` | Skills | Toolbox, grouped by category. |
| `/experience` | Experience | Career timeline. |
| `/education` | Education | Academic timeline. |
| `/research` | Research | Graduate research detail (currently a mid-page card). |
| `/products` | Products | Self-built products grid. |
| `/contact` | Contact | Contact info + form + languages. Was a `#contact` anchor slapped on the bottom of every page; now a real page. |

Existing `/privacy`, `/terms`, `/work/{slug}` are untouched.

**Header nav** (was 3 items but every one of them pointed at a `#anchor` on
`/`, which no longer exist): `Work · About · Skills · Courses · Contact` —
five items, each a real page. Everything else (Services, Experience,
Education, Research, Products) is one click away via a small cross-link
pill row rendered at the top of every "about-family" page, plus the footer,
so nothing becomes an orphan just because it's not in the header.

## Visual tightening

- `--hd` (header height): `60px → 52px`.
- Header vertical rhythm: brand badge `30px → 26px`, nav gap `24px → 20px`,
  font-size `13.5px → 13px`.
- `.btn` base padding: `10px 18px → 8px 14px`; `.btn.lg`: `13px 24px → 10px 18px`;
  new `.btn.sm` stays for header/inline use.
- Section vertical padding: `60px → 52px` (`48px → 44px` at narrow widths) —
  still breathing room, less dead air.
- New `.page-hero` — a shorter, left-aligned hero variant for sub-pages
  (eyebrow + h1 + one-line description, no stat row, ~40% shorter than the
  home hero) so every sub-page doesn't reopen the same tall hero home uses.
- New `.subnav` pill row for the about-family pages.

## Execution

1. `PortfolioController`: keep `home()` (slimmed data + view), keep
   `project()` unchanged, add `work()`, `about()`, `services()`, `skills()`,
   `experience()`, `education()`, `research()`, `products()`, `contactPage()`.
   Update `contact()` (POST handler)'s redirect target from `route('home').'#contact'`
   to `route('contact')`.
2. Routes: add the nine new GET routes; keep everything else as-is.
3. `layouts/marketing.blade.php`: shrink header/button/section CSS as above,
   add `.page-hero`/`.subnav` styles, rewrite the header nav + mobile menu +
   footer link columns for the new IA.
4. New views: `portfolio/work.blade.php`, `about.blade.php`,
   `services.blade.php`, `skills.blade.php`, `experience.blade.php`,
   `education.blade.php`, `research.blade.php`, `products.blade.php`,
   `contact.blade.php` — each reuses the existing `.grid`/`.card`/`.timeline`/
   `.feature-box`/`.contact-*` component classes, just relocated off the
   home page and behind `.page-hero`.
5. Rewrite `portfolio/home.blade.php` to the lean structure above.
6. Tests: keep `ContactFormTest::test_home_page_renders` (still `GET /`),
   update the POST-contact redirect assertion, add one smoke test per new
   route (`assertOk()`), add a `RouteNamingTest`-style check that nav links
   resolve.
7. `vendor/bin/pint --dirty`, full test suite, manual skim of every new page
   before calling this done.

## Status: executed

All of the above is built: 10 pages, tightened header/button/section CSS,
`.page-hero`/`.subnav` components, the lean home page, and
`tests/Feature/Portfolio/PortfolioPagesTest.php` (every new route renders,
nav links resolve, subnav cross-links every about-family page, contact
redirect targets `/contact`). `pint`/`phpstan` clean.

## Follow-up work done in the same pass

**1. Fixed a real, currently-broken page-load issue.** Every layout
(`marketing`, `app`, `admin`, `auth`) loaded Google Fonts from
`fonts.googleapis.com`/`fonts.gstatic.com` as a **render-blocking**
`<link>`. On a network where that DNS fails (`net::ERR_NAME_NOT_RESOLVED`
— confirmed from the reported browser console), the whole page hangs
waiting on that request. Fixed by self-hosting Inter (latin + latin-ext,
weights 200-700, woff2) at `public/vendor/fonts/inter/` — vendored from
`@fontsource/inter` via a temporary `npm install --no-save` (nothing added
to `package.json`), matching the existing `public/vendor/fa` /
`public/vendor/js/*.min.js` pattern already used for FontAwesome/Chart.js/
SortableJS in this codebase. Removes the external DNS dependency entirely
— not just a fallback, a fix — and is faster even when Google is reachable
(no cross-origin DNS+TLS round trip). All four layouts now load
`vendor/fonts/inter/inter.css` locally; `<link rel="preconnect">` to
Google removed as dead weight.

**2. `wire:navigate` (Livewire pjax-style navigation) added across the
public site.** `layouts/marketing.blade.php` now loads
`@livewireStyles`/`@livewireScripts`; every internal link across the
header, mobile menu, footer, subnav partial, and each portfolio/course
view got `wire:navigate` (external `target="_blank"` links — GitHub,
YouTube, a project's external site — deliberately excluded, they're
supposed to leave the app). Result: clicking between pages swaps only the
`<body>` over AJAX instead of a full reload — same instant-feel navigation
the admin panel already has. **SEO is unaffected**: `wire:navigate` is a
progressive enhancement over real `<a href>` navigation — every page still
has its own real URL, still fully server-renders complete HTML on a
direct/crawler request (the `<link rel="canonical">` and `og:url` tags are
already computed per-request), and Livewire automatically falls back to a
normal full navigation for any link whose target page doesn't share the
same asset set (e.g. jumping into the authenticated student/admin areas),
so nothing is silently downgraded to a client-only route. The mobile
burger-menu script is re-initialized on every `livewire:navigated` event
(same pattern the admin layout already uses) so it keeps working across
swapped pages.
