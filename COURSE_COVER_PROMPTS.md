# Course cover art — 21 generation prompts

> **REVISION 2 — read this first.** The first version of this brief produced
> covers with an empty left half and a small diagram crammed into the right.
> That was the brief's fault, not the generator's: it asked for emptiness
> twelve separate times. Block A has been rewritten to demand the opposite.
> If you already generated from Revision 1, regenerate — or paste the
> rejection below.

## Rejecting a Revision-1 cover

Paste this with the image attached. It is written as a working art director
would write it: what is wrong, why it is wrong, and what to do instead.

```
This does not work. Specific problems, in order of seriousness:

1. COMPOSITION IS BROKEN. Over half the canvas — the entire left side —
   is empty paper, and the whole subject is crammed into the right
   third. This is not "generous negative space", it is an unbalanced
   image with a hole in it. The artwork must fill the frame edge to
   edge. Negative space belongs BETWEEN and AROUND elements as
   breathing room, distributed across the whole canvas — never pooled
   into one dead half.

2. IT DIES AT REAL SIZE. This is a course card on a website, displayed
   about 320 pixels wide. At that size these hairline strokes vanish
   and the whole thing turns to grey mush. Line weights must be heavy
   and confident — think 3-4pt strokes at full size, not 0.5pt. Squint
   at it: if the shape does not survive squinting, it is too fine.

3. TOO LITTLE INK. A two-colour screen print is bold by nature —
   opaque ink laid down in real coverage. This is 90% bare paper with
   some thin outlines. I want large areas of solid flat navy and solid
   flat gold. Fill shapes. Commit.

4. NO FOCAL POINT. Five near-identical floating planes with equal
   visual weight, so the eye has nowhere to land. One element must
   dominate — bigger, or solid-filled, or gold against navy — and the
   rest must clearly support it.

5. TOO MANY PARTS. Five stacked layers plus leader lines plus a
   cylinder is a technical figure, not a cover. Three major elements
   maximum.

Redo it as a bold, poster-like composition that fills the entire
frame, with heavy line weights, large areas of solid ink, one clear
focal point, and no more than three major elements. Keep the two-ink
navy-and-gold screen print treatment and the flat geometric style —
those parts were right. Make it read from across a room.
```

Art direction and one ready-to-paste prompt per course, for generating the
1280×720 cover of every course in the catalogue.

Works with Midjourney, DALL·E / ChatGPT images, Ideogram, Flux, Firefly, Stable
Diffusion. Notes for specific tools are at the bottom.

---

## Why these prompts are built this way

AI-generated cover art announces itself. It does it through a small, very
predictable set of tells:

- glossy 3D renders, floating holograms, glowing neon circuit boards
- blue-purple gradients and lens flares
- "particles", bokeh, depth-of-field on a flat graphic
- garbled pseudo-text in fake UI panels
- hyper-detail everywhere, perfect symmetry, no material
- imagery unrelated to the actual subject — a generic robot for anything about code

Asking for "not AI-looking" does not remove any of that, because it names no
alternative. What does remove it is **committing to a real human craft with
physical constraints**, so the generator has rules to obey rather than freedom
to render.

The commitment here is **two-colour screen printing**. Ink is opaque and
limited, layers misregister very slightly, paper takes ink unevenly, and there
is no such thing as a gradient or a lens flare. That single constraint kills
most of the tells at once, and it is a tradition with real lineage — Swiss
poster design, Bauhaus workshop prints, mid-century book jackets, technical
drafting.

It also happens to be exactly your brand: navy and gold, on paper.

---

## BLOCK A — the house style

**Paste this before every course block.** It is what makes 21 covers look like
one set rather than 21 unrelated pictures.

```
Two-colour screen print in the Swiss International Typographic style,
1970s technical-manual cover. Printed in exactly two opaque inks —
deep navy (#0B1F3A) and antique gold (#B8933F) — on warm off-white
uncoated paper stock (#F5F2EA) with visible paper tooth.

Craft rules, obey strictly:
- Flat opaque ink only. No gradients, no glow, no glossy highlights,
  no 3D rendering, no drop shadows.
- Slight registration offset between the two ink layers, under 1mm,
  as on a real hand-pulled print.
- Subtle ink grain and a few honest imperfections in the coverage.
- Overprint where navy and gold cross: the overlap goes darker.
- Composition FILLS THE FRAME edge to edge. Breathing room sits
  between and around elements, distributed — never pooled into one
  empty half. No dead quadrants.
- Bold poster, not a technical figure. Three major elements at most,
  one clearly dominant, and it must read from across a room.
- Large areas of SOLID FLAT INK — filled shapes, not just outlines.
  Roughly half the surface carries ink.
- Heavy confident line weights, equivalent to 3–4pt at full size.
  This is displayed 320px wide on a card: anything hairline disappears.
- Geometric construction: compass arcs, ruled lines, isometric
  projection, hand-drafted diagram logic.
- Absolutely no text, no letterforms, no numerals, no fake UI, no
  logos or brand marks of any company.

Mood: bold, confident, engineered. A specialist print shop made this
as a poster for an engineering faculty — meant to be seen from the far
end of a corridor, not studied at arm's length.
```

## BLOCK B — the negative prompt

For tools with a negative field (Stable Diffusion, Flux, some Midjourney
workflows). For prompt-only tools it is already covered by Block A.

```
3D render, octane, unreal engine, glossy, glass, chrome, neon, glow,
bloom, lens flare, bokeh, depth of field, gradient mesh, holographic,
cyberpunk, circuit board, binary code, matrix, floating particles,
sparkles, stock-photo businessman, generic robot, laptop on a desk,
text, letters, words, numbers, watermark, signature, UI mockup,
company logos, photorealistic, hyperdetailed, busy, cluttered,
symmetrical, centred composition, purple and blue gradient
```

## Technical

| | |
|---|---|
| Aspect | 16:9 · export 1280×720 |
| Midjourney | append `--ar 16:9 --style raw --stylize 150` |
| Ideogram | best for crisp flat vector-like ink; set "Design" style |
| Flux / SD | Block B into the negative field, CFG 4–6 |
| Where they go | `public/images/courses/{slug}.png` — slugs are listed per course below |

**Two rules when you generate:**

1. **Never let it render text.** Every prompt below forbids it. The course title
   is set in HTML over the card by the site, in your real brand typeface — that
   is why these covers carry none, and why they will never have the mangled
   pseudo-lettering that gives AI art away instantly.
2. **Generate all 21 in one sitting** if you can, with the same tool and the
   same seed family. A set that drifts in style is worse than a plain one.

---

# The 21 prompts

Each is Block A plus a subject drawn from what the course actually teaches —
not a generic "coding" picture. The metaphor comes from the real modules, so
the cover means something to somebody who has taken the course.

---

## 01 · Introduction to Web Development
`introduction-to-web-development-html-css-bootstrap-php-mysql`

> Modules: your first web page · Bootstrap · Hello PHP · forms · MySQL · PHP+MySQL together

```
[BLOCK A]

Subject: an exploded isometric diagram of a single web page being built
up in layers, drawn as five flat planes floating in ordered sequence
above one another, each offset diagonally so all five are visible at
once. From the bottom: a plain rectangular slab (the empty folder), a
wireframe skeleton of boxes (structure), a plane of solid gold shapes
(styling), a plane of arrows flowing left and right (the server
conversation), and at the base a horizontally-banded cylinder drawn in
navy line-work (the database). Thin gold leader lines connect the layers
like an assembly drawing. The stack fills the frame, tilted to run corner to corner.
```

---

## 02 · AI-Powered Web Development ⭐
`ai-powered-web-development-html-css-github-copilot`

> Modules: the AI developer mindset · tools · HTML · forms · SEO & semantics

```
[BLOCK A]

Subject: two hands drawing the same line. On the left a human hand in
navy line-work holding a technical pen; on the right a jointed drafting
arm, a real mechanical pantograph, rendered in the same weight of line.
Both meet at the centre on one continuous gold stroke that neither
started alone. The gold line continues past them and resolves into a
simple rectangular page layout. Draw the hands as a 1950s engineering
manual would: contour lines, no shading, no fingers rendered in detail.
The two arms fill the width, meeting slightly left of centre.
```

---

## 03 · PHP Programming, Step by Step
`php-programming-step-by-step`

> Modules: getting started · data types · operators · decisions · loops & functions

```
[BLOCK A]

Subject: a rising staircase built from stacked rectangular blocks, drawn
in flat isometric projection, climbing from the lower left to the upper
right. Each block is a different size, the smallest at the bottom — small
lessons, big foundations. Two blocks near the top are gold; the rest
navy. A single continuous gold line loops back from a higher step to a
lower one and returns, drawn as a clean geometric arc: the loop. The
staircase spans corner to corner, its top step touching the upper right.
```

---

## 04 · Database Design & MySQL for Beginners
`database-design-mysql-for-beginners`

> Modules: thinking in tables · first SQL · everyday queries · ERDs

```
[BLOCK A]

Subject: an entity-relationship diagram treated as ornament. Four
rectangles of ruled rows, arranged asymmetrically, joined by connector
lines ending in real crow's-foot notation — the three-pronged fork and
the small perpendicular bar. One relationship line is gold and travels
further than the others, crossing behind a rectangle and overprinting
darker where it passes. The rectangles are empty ruled rows, never
filled with text. The four tables fill the frame in a loose asymmetric cluster.
```

---

## 05 · JavaScript, jQuery & AJAX Essentials
`javascript-jquery-ajax-essentials`

> Modules: the language · jQuery · AJAX, the magic behind modern apps

```
[BLOCK A]

Subject: a flat page layout of stacked navy bars, precisely aligned —
except one single bar in the middle which has lifted a few millimetres
off the page and is being redrawn on its own. Show its motion with three
concentric gold arcs radiating from it, drawn as compass work, and a
thin gold line running to it from off the edge of the paper. Everything
else stays perfectly still. The whole idea of the course in one image:
the page does not reload, one piece changes.
```

---

## 06 · CodeIgniter Crash Course
`codeigniter-crash-course-your-first-php-framework`

> Modules: meet the framework · working with data · modern CodeIgniter 4

```
[BLOCK A]

Subject: a lightweight scaffold. A minimal navy frame of thin struts and
right-angle joints, drawn in isometric projection, standing around and
supporting a single simple gold rectangle. The frame is conspicuously
light — few members, generous gaps, nothing structural that is not
needed. Small circular joint details where struts meet, drawn as a
drafting manual would. The scaffold fills the frame, seen from slightly below.
```

---

## 07 · WordPress from Zero to Hero
`wordpress-from-zero-to-hero`

> Modules: your first site · publishing content · running a real site

```
[BLOCK A]

Subject: a hand-operated printing press, drawn as a flat geometric
side-elevation in navy line-work — a platen, a lever arm, a bed. Below
its bed, instead of paper, three identical gold rectangles emerge in a
neat overlapping stack, each one a published page. The lever arm is
gold. Draw it the way a Victorian patent illustration would: pure
outline, no texture, every part diagrammatic. The press fills the height of the frame, weighted left of centre.
```

---

## 08 · Web Application Security Essentials
`web-application-security-essentials`

> Modules: thinking like an attacker · the fixes, in code · the professional standard

```
[BLOCK A]

Subject: a single form input drawn as a long navy rectangle, seen from
the side in cross-section, with a gold line entering it from the left.
Inside the rectangle the gold line meets a series of geometric baffles —
angled plates set in the channel, like a maze or a labyrinth seal — that
turn it back on itself. Below, drawn faintly in navy, the same channel
without baffles and the line passing straight through. Two states of one
system: unguarded, and guarded. The cross-section runs the full width, filling the frame.
```

---

## 09 · Modern HTML & CSS Deep Dive
`modern-html-css-deep-dive`

> Modules: HTML refresher · CSS fundamentals · flexbox & grid (the heart) · responsive · polish

```
[BLOCK A]

Subject: rectangles finding their alignment. A loose scatter of navy
rectangles of varying sizes on the left, drifting, unaligned — and as
the eye moves right they rotate into place and lock into a precise
modular grid, the rightmost ones perfectly gold and flush. Show the
transition in five or six discrete steps, not a blur. Thin gold
registration marks and measurement ticks along the top edge, like a
typographer's layout sheet.
```

---

## 10 · Laravel Essentials
`laravel-essentials-routes-to-real-apps`

> Modules: setup & first pages · Blade · migrations & models · auth & uploads · ship it

```
[BLOCK A]

Subject: routes, drawn literally as a track diagram. Several navy lines
enter from the left edge at different heights, run horizontally, and pass
through a set of geometric junction points — small circles and switch
symbols borrowed from railway signalling diagrams — before converging
into one thick gold line that exits right. Each junction is labelled only
by a distinct geometric glyph, never a word. The track band fills the full height of the frame.
```

---

## 11 · Flutter Mobile App Development ⭐
`flutter-mobile-app-development-from-zero`

> Modules: hello Dart · first app · layouts & navigation · forms · JSON & HTTP · offline SQLite

```
[BLOCK A]

Subject: one source becoming two devices. A single gold vertical line
descends from the top of the paper, and partway down splits cleanly into
two lines that terminate in two simple rounded rectangles of different
proportions, drawn in navy outline, standing side by side — one taller
and narrow, one shorter and wide. The split is a precise geometric fork,
compass-drawn, not organic. Inside each rectangle, three plain navy bars
in identical arrangement: the same layout, two shapes. The fork and both
devices fill the frame vertically.
```

---

## 12 · Laravel Admin Panel Mastery ⭐
`laravel-admin-panel-mastery`

> Modules: setup · forms that build themselves · grids · access control · hotel mini-project

```
[BLOCK A]

Subject: a control panel drawn as a flat front-elevation instrument
plate. A navy rectangular face carrying an ordered arrangement of
geometric controls: a large ruled grid on the right two-thirds, a column
of circular dials on the left, two toggle slots, and one prominent gold
rotary dial with graduation ticks around it. Every element is pure
geometry — no screws, no texture, no rendered metal. The kind of plate
drawn in an equipment manual. Slight registration offset most visible on
the gold dial.
```

---

## 13 · Mastering Flutter UI ⭐
`mastering-flutter-ui`

> Modules: clone famous feeds · cards & dialogs · lists & grids · inputs · complete screens

```
[BLOCK A]

Subject: a designer's component specification sheet. Nine small
rectangular UI components — a card, a list row, a circular avatar, a
pill button, a slider track, a checkbox square, a dialog box, a tab bar,
a grid tile — laid out in a strict 3×3 arrangement with generous gutters,
each drawn in flat navy outline as a spec drawing, each with fine gold
dimension lines and arrowheads indicating its padding and height. No
text on any dimension line. The nine cells fill the frame with tight gutters.
```

---

## 14 · Android Development Fundamentals (Java)
`android-development-fundamentals-java`

> Modules: setup · how Android really works · lists & menus · Retrofit networking

```
[BLOCK A]

Subject: the activity lifecycle as a circular state diagram. Six navy
circles arranged around a large ring, connected by directional arrows
running clockwise, with two shorter gold arrows cutting back across the
middle of the ring — the paths a screen takes when it is paused and
resumed. Each state circle contains only a simple geometric glyph, never
a word. Drawn exactly as a control-systems textbook would draw it.
The ring fills most of the frame, sitting slightly low and right.
```

---

## 15 · Android Material UI Design Challenge ⭐
`android-material-ui-design-challenge`

> Modules: the challenge begins · component days · advanced days

```
[BLOCK A]

Subject: a wall calendar of work. A grid of small squares, five across
and four down, drawn in navy rule, each square containing one different
simple geometric UI glyph — a bar, a circle, a stack of lines, a
triangle, a ring. The squares fill progressively: the early ones are
completely filled with solid gold ink, the middle ones are outline only,
and the last few in the bottom row are empty paper. A challenge in
progress, honestly unfinished. The grid fills the frame edge to edge, margins tight.
```

---

## 16 · InvetoTrack: Inventory Management System ⭐
`invetotrack-build-a-complete-inventory-management-system`

> Modules: Laravel back office · the API · Flutter mobile app · 47 lessons

```
[BLOCK A]

Subject: a three-tier system drawn as one architectural section. At the
base, a wide grid of warehouse shelving in isometric projection, navy,
its bays drawn as open rectangles. Rising from it, a single vertical
gold spine. At the top of the spine, a small rounded-rectangle device.
Between the shelving and the device, three horizontal gold bands
crossing the spine at intervals — the API layer, drawn as a stack of
transfer lines. One shelf bay is filled solid gold: the item being
tracked from floor to phone. The largest, most architectural cover in
the set — this is the flagship.
```

---

## 17 · MarketLink: Multi-Vendor E-Commerce ⭐
`marketlink-multi-vendor-e-commerce-platform-laravel-flutter`

> Modules: platform & products (Laravel) · the shopping app (Flutter) · extensions

```
[BLOCK A]

Subject: many sellers, one marketplace. Seven small navy market-stall
forms — simple geometric awnings on posts, drawn in flat elevation —
arranged in a loose arc across the upper half. From each, a thin navy
line descends and they all converge into a single broad gold channel
that runs to the bottom edge of the paper. The convergence point is a
clean geometric funnel, drafted with a compass. Stalls vary slightly in
width so it never reads as machine-repeated.
```

---

## 18 · HotelPro: Hotel Booking System ⭐
`hotelpro-hotel-booking-management-system-php-mysql`

> Modules: foundations & admin · the customer experience · admin operations

```
[BLOCK A]

Subject: a booking chart. A horizontal timeline grid drawn in fine navy
rule — rooms down the left as blank ruled rows, days across the top as
narrow columns — with several solid gold bars laid across it spanning
different numbers of columns, the way a real occupancy chart shows
stays. One bar overlaps the edge of another and overprints darker. Hang
a single small navy key-shape, drawn as pure geometry, from the corner
of one gold bar. No text in any cell.
```

---

## 19 · Build a Complete Online Shop ⭐
`build-a-complete-online-shop-with-php-mysql`

> Modules: accounts & foundations · products · cart & orders

```
[BLOCK A]

Subject: a shopping cart constructed entirely from database rows. Draw
the classic side-elevation silhouette of a shop trolley — basket, handle,
two wheels — but build its basket out of stacked horizontal ruled bands
like the rows of a table, in navy. The two wheels are precise gold
circles with centre marks, drawn as compass work. Out of the top of the
basket rises a single long gold receipt tape, curling once, its edge
serrated. Everything flat, everything ruled.
```

---

## 20 · Android E-Commerce App with Firebase ⭐
`android-e-commerce-app-with-firebase`

> Modules: Firebase foundations · building the shop · going public

```
[BLOCK A]

Subject: realtime sync, drawn as a signal diagram. A small rounded
rectangle device sits low-left in navy outline. Upper-right, a geometric
cloud form built from three overlapping circles and a flat base, also
navy. Between them, five parallel gold lines of differing lengths run
both directions at once, with small arrowheads at both ends and short
tick marks along them like a timing chart. The lines pass behind the
device and overprint darker. No brand logos of any kind. Strong diagonal composition filling all four corners.
```

---

## 21 · Flutter Mini-Projects: Diary & News
`flutter-mini-projects-local-diary-news-app`

> Modules: local diary (offline-first) · news app (online-first) · 2025 refresher

```
[BLOCK A]

Subject: two projects, opposite natures, side by side as a diptych. Left
half: a closed book seen in flat side-elevation, navy, with a small gold
padlock-shape geometric glyph on its edge and no lines leaving it at all
— entirely self-contained, offline. Right half: an open rectangular
frame with many fine gold lines streaming into it from beyond the right
edge of the paper, arriving from outside — online. A single vertical
navy rule divides the two halves precisely down the middle. Perfectly
balanced weight, deliberately different behaviour.
```

---

## After you generate

1. **Reject any with text on it.** Even convincing lettering. The title is set
   in HTML over the card, and a cover with its own baked-in title will fight it
   and will be wrong the moment a title is edited.
2. **Check the pair reads at card size.** Shrink to 320px wide. If the diagram
   turns to mud, it was too detailed — regenerate asking for fewer elements.
3. **Save as** `public/images/courses/{slug}.png` using the slugs above, at
   1280×720.
4. **Set them** in the admin course form (Cover image), or in bulk once
   `courses:make-covers` exists.

## If a cover still looks AI-made

The usual cause is the generator quietly ignoring the two-ink limit. Add this
to the end of that course's prompt and try again:

```
Reproduce this as a photograph of an actual printed poster lying flat
under even daylight, slight paper texture visible, one corner very
slightly lifted. Two spot inks only — no CMYK rosette, no process
colour, no fourth colour anywhere in the image.
```
