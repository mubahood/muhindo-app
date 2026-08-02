# Course 09 — Modern HTML & CSS Deep Dive

**Tier 2 · Frameworks & Specialisation · Level: Beginner–Intermediate · Prerequisites: Course 01 or 02**

Bootstrap is a great start — but real front-end developers can style *anything* by
hand. This course goes deep on CSS: flexbox, grid, responsive design and polish. The
structure follows the proven W3Schools progression, powered by hand-picked video
lessons.

**What you will learn**

- CSS selectors, the box model, colors, units and typography
- Flexbox and CSS Grid — the two layout systems that changed everything
- Media queries and true mobile-first responsive design
- Transitions and small animations that make sites feel professional

---

## Module 1 — HTML refresher (fast)

1. **HTML crash course** — a tight, modern refresher.
   ▶ https://www.youtube.com/watch?v=qz0aGYrrlhU *(Programming with Mosh)*
2. **Learn HTML in 1 hour** — alternative refresher; pick one of the two.
   ▶ https://www.youtube.com/watch?v=HD13eq_Pmp8 *(Bro Code)*
3. **Muhindo's foundation session** — tools + first page, in his teaching style.
   ▶ https://www.youtube.com/watch?v=y7mC6h1wPL4

## Module 2 — CSS fundamentals

4. **CSS from zero to hero (companion course)** — watch alongside modules 2–4.
   ▶ https://www.youtube.com/watch?v=1Rs2ND1ryYc *(freeCodeCamp)*
5. **Selectors & specificity** (text lesson) — element, class, id; who wins and why.
6. **The box model** (text lesson) — margin, border, padding, content. Draw it once,
   never be confused again.

```css
.card {
  max-width: 320px;
  padding: 16px;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,.08);
}
```

## Module 3 — Layout: flexbox & grid (the heart of the course)

7. **Flexbox** (text lesson + practice) — one dimension: navbars, card rows, centering.
8. **CSS Grid** (text lesson + practice) — two dimensions: photo galleries, dashboards.
9. **Challenge:** rebuild your Bootstrap Course-01 homepage *without* Bootstrap.

```css
.gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
```

## Module 4 — Responsive design

10. **Media queries & mobile-first** (text lesson) — start narrow, enhance wider.
11. **Responsive units** — `%`, `rem`, `vw`; when to use each.
12. **Test like a pro** — DevTools device mode; the 360px rule.

```css
/* Mobile first: */
.nav { flex-direction: column; }
@media (min-width: 768px) {
  .nav { flex-direction: row; }
}
```

## Module 5 — Polish

13. **Transitions & hover effects** — buttons that feel alive (text lesson).
14. **Google Fonts & typography scale** — instant professionalism (text lesson).
15. **Full front-end practice run** — HTML/CSS/JS together, project-based.
    ▶ https://www.youtube.com/watch?v=zJSY8tbf_ys *(freeCodeCamp bootcamp — use the HTML/CSS sections)*

## Final project

Clone a real homepage you admire (pick one: a bank, an airline, a university) using
only hand-written HTML + CSS — flexbox/grid layout, responsive at 360/768/1200px,
hover states. Submit code + phone screenshot + desktop screenshot.

**Quiz ideas:** specificity battles ("which rule wins?") · box-model math questions ·
flexbox property matching · practical graded on responsive behaviour.
