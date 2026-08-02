# Brand Logo Spec — Muhindo Mubaraka

Agent guide for integrating the new brand logo across muhindo-app. The SVG source
files live in `public/brand/`. They are hand-built vectors (not traced images) —
edit coordinates, don't re-export.

## 1. The mark, explained

A monogram "M" that carries three ideas in one shape:

- **The letter M** — the outer navy structure (Muhindo Mubaraka).
- **Book pages / columns** — four gold vertical bars inside the counters, tops cut
  parallel to the M's diagonals (outer bars taller): learning, structure, systems.
- **A fountain-pen nib** — the gold pentagon hanging from the M's valley, with a
  white slit and breather hole, tapering to a point: the teacher/author.

Nothing else. No gradients, no shadows, no extra detail — flat, geometric, two
colors + white, legible at 16px.

## 2. Color tokens

| Token | Hex | Use |
|---|---|---|
| Brand navy | `#1F2A44` | Outer M, primary text, dark backgrounds |
| Brand gold | `#B08D3E` | Bars, nib, accents, second name line |
| White | `#FFFFFF` | Nib slit + breather hole, mark on dark |

These must match (or replace) the site's CSS custom properties in `td-admin.css` /
the marketing layout — single source of truth. If the site's existing navy/gold
tokens differ slightly, update the SVG fills to the site tokens, not the reverse.

## 3. Files (`public/brand/`)

| File | ViewBox | What it is | Use it for |
|---|---|---|---|
| `logo-icon.svg` | 512×512 | Full-detail mark (4 bars, nib, slit+dot), transparent bg | Header badge, avatars, watermarks, any size ≥ 32px |
| `logo-icon-dark.svg` | 512×512 | Same mark, white M for navy/dark surfaces | Dark header, footer, dark-mode |
| `logo-mono.svg` | 512×512 | Single color via `currentColor`, no slit/dot | Stamps, engraving-style PDF headers, favicons in pinned tabs |
| `favicon.svg` | 512×512 | **Simplified**: one bar per side, no slit/dot | Favicon + any use < 32px (the detail version smears at tiny sizes) |
| `logo-horizontal.svg` | 1660×512 | Mark + gold divider + "MUHINDO / MUBARAKA" wordmark, light | Marketing header, email header, invoice/certificate headers |
| `logo-horizontal-dark.svg` | 1660×512 | Same lockup on navy | Hero/dark bands, OG image basis |

**Wordmark caveat:** the lockups use `<text>` with
`font-family="Montserrat, Inter, 'Segoe UI', Arial, sans-serif"`. In the browser
that renders with whatever the site loads (load Montserrat 500/600 or substitute the
site's heading font). For PDFs (DomPDF) `<text>` fallback varies — for the
certificate/invoice headers either render mark + HTML text side by side (preferred,
already the codebase's pattern) or convert the lockup text to paths first. Do NOT
ship a PDF that silently falls back to Times.

## 4. Usage rules

- **Clear space:** keep a margin of at least the stem width (≈ 14% of mark height)
  around the mark on all sides.
- **Minimum sizes:** `logo-icon.svg` ≥ 32px; below that always `favicon.svg`.
  Lockups ≥ 140px wide; below that use the icon alone.
- **Backgrounds:** light/cream → `logo-icon.svg`; navy/photo/dark → `logo-icon-dark.svg`.
  Never place the navy version on navy.
- **Never:** stretch, rotate, recolor outside the two tokens, add shadows/gradients/
  outlines, put the mark in a circle, or regenerate it with an image model.

## 5. Integration task list (execute with AGENT_COMMAND.md discipline)

1. **Replace placeholder branding** (README flags logos as inherited placeholders):
   the "MM" text badge in the marketing header/footer and any true-doctor logo
   assets under `public/`. Use `logo-icon.svg` (28–32px) + name text in the header
   per the redesign's slim-header sizing; footer uses the dark icon.
2. **Blade component:** rewrite `components/application-logo.blade.php` to inline
   `logo-mono.svg`'s paths (inherits `currentColor`), so auth pages and layouts get
   the real mark.
3. **Favicon set:** from `favicon.svg` generate `favicon.ico` (16/32/48),
   `apple-touch-icon.png` (180, navy bg, white/gold mark), and
   `icon-192.png`/`icon-512.png`; wire `<link rel>` tags in every layout
   (marketing, app, admin, auth). Remove the old favicon.
4. **PDFs:** certificate, invoice, receipt headers switch to the mark + text
   pattern (see §3 caveat). The certificate keeps its QR block — mark top-left,
   QR bottom-right.
5. **Emails:** `emails/_header.blade.php` uses the horizontal lockup as an inline
   image (PNG export at 2x, emails can't rely on SVG support).
6. **Social/OG default image:** compose a 1200×630 PNG from
   `logo-horizontal-dark.svg` centered on navy; set as the default `og:image` in
   the SEO component (PUBLIC_SITE_PLAN §6.1).
7. **Verification:** screenshot header (light + dark), an auth page, one PDF of
   each type, and the favicon at 16px in a browser tab; check the mark is crisp,
   colors match the site tokens, and no placeholder "MM" badge or true-doctor
   asset remains (`grep` for the old asset filenames). Log in the build log.

## 6. Provenance

Concept generated with an image model from the owner's brief (simple, professional,
MM monogram, navy/gold); geometry rebuilt by hand as clean SVG paths on a 512-unit
grid (mark bounds x 56–456, y 48–464; stems 72u; valley flat at y 274; nib point
y 448). The SVGs in `public/brand/` are the canonical brand source — the original
PNG is reference only.

## Signature

`resources/brand/signature.png` — Muhindo's handwritten signature, used on issued
documents (certificates, invoices) via `resources/views/pdf/partials/signature.blade.php`.

Made from the original scan with a transparent background and no surrounding
whitespace, so it sits on a ruled line rather than in a white box:

```sh
magick muhindo-signature-1.png \
  -fuzz 12% -transparent white \
  -trim +repage \
  -resize 900x -strip \
  -colors 32 -define png:compression-level=9 \
  PNG8:resources/brand/signature.png
```

PNG8 rather than PNG32: it is two-colour line art, and the palette version is
17KB against 154KB with no visible difference at print size.

**Kept out of `public/`.** DomPDF reads it from the filesystem, so it never needs
to be web-reachable, and a signature at a guessable URL is one anyone can lift.
It lives in `resources/` rather than `storage/` because `storage/app/.gitignore`
ignores everything in it — the asset has to ship with the code.

**Not on receipts.** A receipt names a specific "Received by" person who may not
be Muhindo; his signature there would misstate who took the money.
