#!/usr/bin/env python3
"""
Eight system screenshots, drawn rather than photographed.

There are no real screenshots of these systems that can be published: they hold
livestock registries, patient records, human-rights case files. So each one is
drawn — the actual screen a user of that system works in, in the site's own two
inks, at 1600x1000.

One design system, eight screens. The chrome is shared so the set reads as one
body of work; the body of each is bespoke, because the whole point is that a
livestock registry does not look like a hospital.

Legibility at card size drives every decision: three KPI numbers set large
enough to read at 300px wide, one diagram per screen that carries the meaning
on its own, and body copy as bars rather than text that would be mush.
"""

import math
import os

W, H = 1600, 1000

NAVY = '#0B1F3A'
NAVY2 = '#152B4C'
NAVY3 = '#1E3A63'
GOLD = '#B8933F'
GOLD_L = '#D9BE7C'
GOLD_P = '#F0E4C6'
PAPER = '#F5F2EA'
WHITE = '#FFFFFF'
LINE = '#E4DFD3'
INK = '#0B1F3A'
MUTE = '#8A8375'
MUTE_L = '#B8B2A4'
GREEN = '#3F7D58'
WATER = '#9DB4C4'
RED = '#B4483C'

FONT = "Helvetica Neue, Helvetica, Arial, sans-serif"

SIDE = 288          # sidebar width
TOP = 52            # title bar height
PAD = 48            # main padding
MX = SIDE + PAD     # main content left edge
MR = W - PAD        # main content right edge


# ── primitives ────────────────────────────────────────────────────────────

def esc(s):
    return (s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))


def rect(x, y, w, h, fill, rx=0, stroke=None, sw=1, op=None):
    a = f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{fill}"'
    if rx:
        a += f' rx="{rx}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def circle(cx, cy, r, fill, stroke=None, sw=1, op=None):
    a = f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def text(x, y, s, size=14, fill=INK, weight='400', anchor='start', ls=0, op=None):
    a = (f'<text x="{x}" y="{y}" font-family="{FONT}" font-size="{size}" '
         f'font-weight="{weight}" fill="{fill}" text-anchor="{anchor}"')
    if ls:
        a += f' letter-spacing="{ls}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + f'>{esc(s)}</text>'


def line(x1, y1, x2, y2, stroke, sw=1, dash=None, cap='butt', op=None):
    a = (f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{stroke}" '
         f'stroke-width="{sw}" stroke-linecap="{cap}"')
    if dash:
        a += f' stroke-dasharray="{dash}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def path(d, fill='none', stroke=None, sw=1, cap='round', join='round', op=None, dash=None):
    a = f'<path d="{d}" fill="{fill}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}" stroke-linejoin="{join}"'
    if dash:
        a += f' stroke-dasharray="{dash}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def bar(x, y, w, h, fill, op=1.0):
    """A line of copy, as the shape it makes rather than as words."""
    return rect(x, y, w, h, fill, rx=h / 2, op=op)


def card(x, y, w, h, rx=6):
    return rect(x, y, w, h, WHITE, rx=rx, stroke=LINE)


def pill(x, y, w, h, fill, label=None, tcol=WHITE, size=13, weight='600'):
    out = [rect(x, y, w, h, fill, rx=4)]
    if label:
        out.append(text(x + w / 2, y + h / 2 + size * 0.36, label, size, tcol, weight, 'middle'))
    return out


def tag(x, y, label, fg, bg, size=11):
    w = len(label) * size * 0.62 + 18
    return [rect(x, y, w, 22, bg, rx=3),
            text(x + w / 2, y + 15.5, label, size, fg, '600', 'middle', 0.4)], w


# ── shared chrome ─────────────────────────────────────────────────────────

def chrome(nav, active, brand, window, title, subtitle, kpis, actions):
    """Everything every one of these screens has in common."""
    o = [rect(0, 0, W, H, PAPER)]

    # Title bar. Enough of a browser to say "this is a screen somebody uses",
    # not so much that it becomes the subject of the picture.
    o.append(rect(0, 0, W, TOP, NAVY))
    for i, op in enumerate((0.22, 0.3, 0.38)):
        o.append(circle(28 + i * 22, TOP / 2, 5, WHITE, op=op))
    # A window title, not a URL. Most of these are internal government systems
    # with no public address, and inventing one to make a picture look real
    # would be putting a claim in the ministry's mouth.
    o.append(text(W / 2, 31, window, 13, WHITE, '500', 'middle', 0.4, op=0.55))
    o.append(circle(W - 40, TOP / 2, 13, GOLD, op=0.9))
    o.append(text(W - 40, TOP / 2 + 4.5, brand['initials'], 11, NAVY, '700', 'middle'))

    # Sidebar.
    o.append(rect(0, TOP, SIDE, H - TOP, NAVY2))
    o.append(rect(28, 84, 38, 38, GOLD, rx=8))
    o.append(brand['glyph'])
    o.append(text(78, 100, brand['name'], 15, WHITE, '700'))
    o.append(text(78, 118, brand['org'], 11, WHITE, '400', ls=0.6, op=0.45))
    o.append(line(28, 150, SIDE - 28, 150, WHITE, 1, op=0.12))

    y = 184
    for i, item in enumerate(nav):
        on = (i == active)
        if on:
            o.append(rect(16, y - 12, SIDE - 32, 40, WHITE, rx=6, op=0.09))
            o.append(rect(0, y - 12, 4, 40, GOLD))
        o.append(rect(32, y - 3, 18, 18, GOLD if on else WHITE, rx=4,
                      op=1 if on else 0.32))
        o.append(text(64, y + 11, item, 13.5, WHITE, '600' if on else '400',
                      op=0.95 if on else 0.5))
        y += 52

    # Whoever is signed in. A system without a named user is a mockup.
    o.append(line(28, H - 96, SIDE - 28, H - 96, WHITE, 1, op=0.12))
    o.append(circle(46, H - 58, 15, WHITE, op=0.16))
    o.append(text(46, H - 53.5, 'MM', 10.5, WHITE, '700', 'middle', op=0.7))
    o.append(bar(72, H - 66, 104, 9, WHITE, 0.4))
    o.append(bar(72, H - 50, 68, 7, WHITE, 0.2))

    # Main header.
    o.append(text(MX, 112, title, 33, INK, '700'))
    o.append(text(MX, 141, subtitle, 14.5, MUTE, '400'))

    bx = MR
    for label, primary in reversed(actions):
        w = len(label) * 8.2 + 34
        bx -= w
        o += pill(bx, 88, w, 42, GOLD if primary else WHITE, label,
                  NAVY if primary else INK, 13.5)
        if not primary:
            o.append(rect(bx, 88, w, 42, 'none', rx=4, stroke=LINE))
            o += pill(bx, 88, 0, 0, 'none')
            o.append(text(bx + w / 2, 88 + 21 + 4.9, label, 13.5, INK, '600', 'middle'))
        bx -= 14

    # Three numbers. The only part of a dashboard anybody reads first.
    cw = (MR - MX - 48) / 3
    for i, (label, value, note) in enumerate(kpis):
        x = MX + i * (cw + 24)
        o.append(card(x, 178, cw, 116))
        o.append(text(x + 22, 208, label, 11, MUTE, '700', ls=1.1))
        o.append(text(x + 22, 252, value, 34, GOLD if i == 2 else INK, '700'))
        o.append(text(x + 22, 274, note, 11.5, MUTE_L, '400'))

    return o


def frame(body):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000" '
            'width="1600" height="1000" role="img">\n  '
            + '\n  '.join(body) + '\n</svg>\n')


def glyph(d, sw=2.4, col=NAVY):
    return path(d, stroke=col, sw=sw)


# ── the map of Uganda ─────────────────────────────────────────────────────
# A coarse national outline. Not a survey boundary — a recognisable country,
# which is all a 300px-wide thumbnail can carry anyway.

UGANDA = [
    (30.83, 3.51), (31.00, 3.72), (31.30, 3.78), (31.80, 3.79), (32.30, 3.72),
    (32.72, 3.79), (33.10, 3.77), (33.50, 3.71), (33.90, 3.67), (34.10, 3.82),
    (34.35, 3.70), (34.55, 3.35), (34.78, 3.02), (34.72, 2.60), (34.60, 2.10),
    (34.48, 1.70), (34.33, 1.28), (34.20, 1.10), (34.05, 0.95), (33.98, 0.72),
    (34.06, 0.48), (33.98, 0.15), (33.93, -0.15), (33.90, -0.55), (33.40, -0.70),
    (32.90, -0.98), (32.20, -1.02), (31.50, -1.02), (30.90, -1.04), (30.45, -1.06),
    (30.05, -1.30), (29.90, -1.47), (29.72, -1.34), (29.65, -1.05), (29.60, -0.72),
    (29.72, -0.44), (29.80, -0.10), (29.71, 0.16), (29.78, 0.48), (29.95, 0.72),
    (30.15, 1.02), (30.35, 1.18), (30.62, 1.40), (30.80, 1.70), (31.02, 1.98),
    (31.28, 2.28), (31.32, 2.60), (31.18, 2.92), (31.02, 3.16), (30.90, 3.36),
]

# The three lakes are what make the outline unmistakable rather than merely
# African. Victoria bites the south-east corner, Albert runs down the western
# border, and Kyoga sprawls across the middle.
VICTORIA = [
    (31.78, -1.02), (32.60, -1.00), (33.30, -0.72), (33.90, -0.55), (33.86, -0.20),
    (33.72, 0.02), (33.35, 0.28), (32.95, 0.36), (32.55, 0.22), (32.22, 0.00),
    (31.98, -0.34), (31.84, -0.70),
]
ALBERT = [
    (30.42, 1.20), (30.68, 1.52), (30.98, 1.86), (31.24, 2.14), (31.14, 2.24),
    (30.86, 1.98), (30.56, 1.62), (30.30, 1.30),
]
KYOGA = [
    (32.00, 1.08), (32.28, 1.06), (32.58, 1.14), (32.88, 1.34), (33.16, 1.46),
    (33.52, 1.50), (33.62, 1.62), (33.30, 1.72), (32.98, 1.60), (32.68, 1.54),
    (32.44, 1.44), (32.30, 1.58), (32.08, 1.64), (31.94, 1.48), (32.06, 1.28),
]


def project(lon, lat, box):
    """lon/lat into a box (x, y, w, h), preserving the country's proportions."""
    x0, y0, bw, bh = box
    lon0, lon1, lat0, lat1 = 29.55, 35.00, -1.60, 4.00
    sx = bw / (lon1 - lon0)
    sy = bh / (lat1 - lat0)
    s = min(sx, sy)
    ox = x0 + (bw - (lon1 - lon0) * s) / 2
    oy = y0 + (bh - (lat1 - lat0) * s) / 2
    return ox + (lon - lon0) * s, oy + (lat1 - lat) * s


def poly_path(points, box):
    pts = [project(lon, lat, box) for lon, lat in points]
    d = f'M {pts[0][0]:.1f} {pts[0][1]:.1f} ' + ' '.join(
        f'L {x:.1f} {y:.1f}' for x, y in pts[1:]) + ' Z'
    return d


def uganda_map(box, pins=(), routes=(), fill=GOLD_P, stroke=GOLD, sw=2.6,
               water=None, lakes=True):
    """The country, with enough of its water to be recognisably itself."""
    water = water or WATER
    o = [path(poly_path(UGANDA, box), fill=fill, stroke=stroke, sw=sw)]

    if lakes:
        for shape in (VICTORIA, ALBERT, KYOGA):
            o.append(path(poly_path(shape, box), fill=water, op=0.85,
                          stroke=NAVY, sw=1.4))

    if routes:
        pts = [project(lon, lat, box) for lon, lat in routes]
        d = f'M {pts[0][0]:.1f} {pts[0][1]:.1f} ' + ' '.join(
            f'L {x:.1f} {y:.1f}' for x, y in pts[1:])
        o.append(path(d, stroke=NAVY, sw=2.4, dash='8 7', op=0.6))

    for lon, lat, big in pins:
        x, y = project(lon, lat, box)
        if big:
            o.append(circle(x, y, 15, GOLD, op=0.25))
            o.append(path(f'M {x:.1f} {y - 20:.1f} c -7 0 -12 5 -12 11 '
                          f'c 0 8 12 20 12 20 s 12 -12 12 -20 c 0 -6 -5 -11 -12 -11 z',
                          fill=NAVY))
            o.append(circle(x, y - 9, 4.4, GOLD))
        else:
            o.append(circle(x, y, 6, NAVY, op=0.8))
            o.append(circle(x, y, 11, NAVY, op=0.18))
    return o


# ── little components the screens share ───────────────────────────────────

def table(x, y, w, cols, rows, rh=44, head=True):
    """A record list. The most honest thing on any government screen."""
    o = [card(x, y, w, (len(rows) + (1 if head else 0)) * rh + 12)]
    cy = y + 6
    if head:
        o.append(rect(x + 1, cy, w - 2, rh, PAPER, rx=0))
        cx = x + 22
        for label, cw in cols:
            o.append(text(cx, cy + rh / 2 + 4, label, 11, MUTE, '700', ls=1))
            cx += cw
        cy += rh
    for r, row in enumerate(rows):
        if r:
            o.append(line(x + 16, cy, x + w - 16, cy, LINE, 1))
        cx = x + 22
        for (label, cw), cell in zip(cols, row):
            kind, val = cell
            if kind == 't':
                o.append(text(cx, cy + rh / 2 + 4.5, val, 13, INK, '600'))
            elif kind == 'm':
                o.append(text(cx, cy + rh / 2 + 4.5, val, 12.5, MUTE, '400'))
            elif kind == 'c':
                o.append(text(cx, cy + rh / 2 + 4.5, val, 12.5, MUTE, '400'))
            elif kind == 'g':
                o.append(text(cx, cy + rh / 2 + 4.5, val, 13, GOLD, '700'))
            elif kind == 'p':
                label2, fg, bg = val
                parts, _ = tag(cx, cy + rh / 2 - 11, label2, fg, bg)
                o += parts
            elif kind == 'b':
                o.append(bar(cx, cy + rh / 2 - 4, val, 8, MUTE, 0.28))
            cx += cw
        cy += rh
    return o


def section_head(x, y, label, right=None):
    o = [text(x, y, label, 12, MUTE, '700', ls=1.2)]
    if right:
        o.append(text(x + right[0], y, right[1], 12, GOLD, '700', 'end'))
    return o


def bars_chart(x, y, w, h, values, labels, hi=None):
    o = []
    n = len(values)
    gap = 14
    bw = (w - gap * (n - 1)) / n
    top = max(values)
    for i, v in enumerate(values):
        bh = (v / top) * h
        bx = x + i * (bw + gap)
        o.append(rect(bx, y + h - bh, bw, bh, GOLD if i == hi else NAVY,
                      rx=3, op=1 if i == hi else 0.78))
        o.append(text(bx + bw / 2, y + h + 20, labels[i], 11, MUTE, '600', 'middle'))
    return o


def spark(x, y, w, h, values, col=GOLD, fill=True):
    lo, hi = min(values), max(values)
    span = (hi - lo) or 1
    pts = [(x + i * w / (len(values) - 1), y + h - (v - lo) / span * h)
           for i, v in enumerate(values)]
    d = f'M {pts[0][0]:.1f} {pts[0][1]:.1f} ' + ' '.join(
        f'L {px:.1f} {py:.1f}' for px, py in pts[1:])
    o = []
    if fill:
        o.append(path(d + f' L {x + w:.1f} {y + h:.1f} L {x:.1f} {y + h:.1f} Z',
                      fill=col, op=0.12))
    o.append(path(d, stroke=col, sw=3))
    o.append(circle(pts[-1][0], pts[-1][1], 5.5, col))
    o.append(circle(pts[-1][0], pts[-1][1], 10, col, op=0.22))
    return o


def donut(cx, cy, r, parts, thickness=26):
    """parts: [(fraction, colour)] — clockwise from 12 o'clock."""
    o = []
    a0 = -90
    for frac, col in parts:
        a1 = a0 + frac * 360
        large = 1 if (a1 - a0) > 180 else 0
        x0 = cx + r * math.cos(math.radians(a0))
        y0 = cy + r * math.sin(math.radians(a0))
        x1 = cx + r * math.cos(math.radians(a1))
        y1 = cy + r * math.sin(math.radians(a1))
        o.append(path(f'M {x0:.1f} {y0:.1f} A {r} {r} 0 {large} 1 {x1:.1f} {y1:.1f}',
                      stroke=col, sw=thickness, cap='butt'))
        a0 = a1
    return o


def qr(x, y, size, seed=7):
    """A QR-shaped block. Deterministic, so the picture never changes."""
    n = 21
    c = size / n
    o = [rect(x, y, size, size, WHITE, rx=4, stroke=LINE)]
    pad = c * 1.5

    def finder(fx, fy):
        s = c * 7
        return [rect(fx, fy, s, s, NAVY, rx=3),
                rect(fx + c, fy + c, s - 2 * c, s - 2 * c, WHITE, rx=2),
                rect(fx + c * 2, fy + c * 2, s - 4 * c, s - 4 * c, NAVY, rx=1)]

    inner = size - pad * 2
    c = inner / n
    o += finder(x + pad, y + pad)
    o += finder(x + pad + inner - c * 7, y + pad)
    o += finder(x + pad, y + pad + inner - c * 7)
    v = seed
    for row in range(n):
        for col in range(n):
            in_finder = ((row < 8 and col < 8) or (row < 8 and col > n - 9)
                         or (row > n - 9 and col < 8))
            v = (v * 1103515245 + 12345) & 0x7FFFFFFF
            if in_finder or (v >> 16) % 100 < 52:
                continue
            o.append(rect(x + pad + col * c, y + pad + row * c, c * 0.92, c * 0.92,
                          NAVY, rx=0.5))
    return o


# ── screen 1 — ULITS ──────────────────────────────────────────────────────

def ulits():
    o = chrome(
        nav=['Dashboard', 'Animal registry', 'Movements', 'Vaccination', 'Disease alerts', 'Districts'],
        active=2,
        brand={'initials': 'MA', 'name': 'ULITS', 'org': 'MAAIF · Livestock',
               'glyph': glyph('M 40 100 q 0 -8 7 -8 q 7 0 7 8 M 40 100 h 14 M 47 92 v -6', col=NAVY)},
        window='u-lits.com — Uganda Livestock Information Tracking System',
        title='Livestock movement tracking',
        subtitle='Live registry across 146 districts · last sync 4 minutes ago',
        kpis=[('ANIMALS REGISTERED', '48,120', 'ear-tagged nationally'),
              ('MOVEMENT PERMITS', '3,402', 'issued this quarter'),
              ('DISTRICTS LIVE', '146 / 146', 'full national coverage')],
        actions=[('Export', False), ('New permit', True)])

    # The map is the screen. Everything else is furniture around it.
    o.append(card(MX, 324, 800, 616))
    o += section_head(MX + 24, 356, 'NATIONAL HERD MOVEMENT', (752, 'LAST 30 DAYS'))
    o += uganda_map(
        (MX + 40, 374, 720, 546),
        pins=[(32.58, 0.58, True), (30.65, 0.65, False), (33.62, 1.98, False),
              (31.42, 2.32, False), (34.18, 1.05, False), (30.35, -0.62, False),
              (32.90, 2.76, False), (31.05, 0.38, False)],
        routes=[(30.65, 0.65), (31.42, 1.32), (32.58, 0.58), (33.62, 1.98)])

    for lon, lat, name in ((32.85, -0.20, 'LAKE VICTORIA'), (32.70, 1.38, 'LAKE KYOGA')):
        tx, ty = project(lon, lat, (MX + 40, 374, 720, 546))
        o.append(text(tx, ty, name, 10, NAVY, '700', 'middle', 1.0, op=0.55))

    # Legend, because a map without one is decoration.
    lx = MX + 40
    o.append(circle(lx + 8, 908, 6, NAVY, op=0.75))
    o.append(text(lx + 24, 912, 'Holding', 12, MUTE, '500'))
    o.append(line(lx + 92, 908, lx + 124, 908, NAVY, 2.4, dash='7 6', op=0.55))
    o.append(text(lx + 134, 912, 'Permitted route', 12, MUTE, '500'))

    # The registry beside it: what a district officer actually types into.
    rx = MX + 828
    rw = MR - rx
    o += section_head(rx, 356, 'RECENT REGISTRATIONS')
    o += table(rx, 374, rw,
               [('TAG', 148), ('DISTRICT', 108), ('', 0)],
               [[('t', 'UG-0442-118'), ('m', 'Mbarara'), ('p', ('SYNCED', GREEN, '#E4EFE8'))],
                [('t', 'UG-0442-119'), ('m', 'Mbarara'), ('p', ('SYNCED', GREEN, '#E4EFE8'))],
                [('t', 'UG-0873-004'), ('m', 'Gulu'), ('p', ('SYNCED', GREEN, '#E4EFE8'))],
                [('t', 'UG-1120-771'), ('m', 'Soroti'), ('p', ('QUEUED', GOLD, GOLD_P))],
                [('t', 'UG-1120-772'), ('m', 'Soroti'), ('p', ('QUEUED', GOLD, GOLD_P))]],
               rh=46)

    # Offline-first is the thing that made this system work. Say so.
    oy = 674
    o.append(rect(rx, oy, rw, 118, NAVY, rx=6))
    o.append(path(f'M {rx + 26} {oy + 52} a 30 30 0 0 1 44 0', stroke=GOLD, sw=3.5))
    o.append(path(f'M {rx + 36} {oy + 40} a 18 18 0 0 1 24 0', stroke=GOLD, sw=3.5, op=0.6))
    o.append(circle(rx + 48, oy + 30, 3.6, GOLD, op=0.35))
    o.append(text(rx + 88, oy + 40, 'Offline capture on', 14, WHITE, '700'))
    o.append(text(rx + 88, oy + 60, '212 records held on device,', 12.5, WHITE, '400', op=0.6))
    o.append(text(rx + 88, oy + 78, 'syncing when signal returns', 12.5, WHITE, '400', op=0.6))

    o += section_head(rx, 838, 'VACCINATION COVERAGE')
    o += bars_chart(rx, 858, rw, 66, [72, 88, 61, 94, 80],
                    ['JAN', 'FEB', 'MAR', 'APR', 'MAY'], hi=3)
    return o


# ── screen 2 — School Dynamics ────────────────────────────────────────────

def school():
    o = chrome(
        nav=['Overview', 'Students', 'Timetable', 'Examinations', 'Fees', 'Library'],
        active=2,
        brand={'initials': 'SD', 'name': 'School Dynamics', 'org': 'Term II · 2026',
               'glyph': glyph('M 36 103 l 11 -6 l 11 6 l -11 6 z M 47 109 v 8', col=NAVY)},
        window='schooldynamics.ug — School Management Information System',
        title='Timetable & attendance',
        subtitle='St. Kizito Secondary · 2,140 students across 38 streams',
        kpis=[('ENROLLED STUDENTS', '2,140', 'across 38 streams'),
              ('ATTENDANCE TODAY', '94.2%', '124 absences logged'),
              ('FEES COLLECTED', 'UGX 38.4M', '81% of term target')],
        actions=[('Print', False), ('Publish term', True)])

    # A timetable is the one screen every school actually lives in.
    o.append(card(MX, 324, 828, 616))
    o += section_head(MX + 24, 356, 'S.3 EAST — WEEK 7', (780, 'AUTO-GENERATED'))

    days = ['MON', 'TUE', 'WED', 'THU', 'FRI']
    gx, gy, gw = MX + 96, 380, 700
    cw = gw / 5
    rh = 78
    for i, d in enumerate(days):
        o.append(text(gx + i * cw + cw / 2, gy + 16, d, 11, MUTE, '700', 'middle', 1))
    periods = ['08:00', '09:20', '10:40', '12:00', '14:00', '15:20']
    # Deterministic but irregular, the way a real timetable is.
    blocks = [
        [1, 0, 2, 0, 1], [0, 2, 0, 1, 0], [2, 1, 1, 0, 2],
        [0, 0, 1, 2, 0], [1, 2, 0, 1, 1], [0, 1, 2, 0, 0],
    ]
    for r, p in enumerate(periods):
        by = gy + 30 + r * rh
        o.append(text(MX + 76, by + rh / 2 + 4, p, 11.5, MUTE_L, '600', 'end'))
        for c in range(5):
            kind = blocks[r][c]
            x = gx + c * cw + 4
            w = cw - 8
            if kind == 0:
                o.append(rect(x, by + 6, w, rh - 12, PAPER, rx=4))
                o.append(bar(x + 14, by + rh / 2 - 10, w - 44, 8, MUTE, 0.22))
                o.append(bar(x + 14, by + rh / 2 + 4, w - 68, 7, MUTE, 0.14))
            elif kind == 1:
                o.append(rect(x, by + 6, w, rh - 12, NAVY, rx=4, op=0.9))
                o.append(bar(x + 14, by + rh / 2 - 10, w - 40, 8, WHITE, 0.72))
                o.append(bar(x + 14, by + rh / 2 + 4, w - 66, 7, WHITE, 0.32))
            else:
                o.append(rect(x, by + 6, w, rh - 12, GOLD, rx=4, op=0.9))
                o.append(bar(x + 14, by + rh / 2 - 10, w - 40, 8, NAVY, 0.75))
                o.append(bar(x + 14, by + rh / 2 + 4, w - 66, 7, NAVY, 0.35))

    lx = MX + 24
    o.append(rect(lx, 906, 12, 12, NAVY, rx=2, op=0.9))
    o.append(text(lx + 22, 916, 'Core subject', 12, MUTE, '500'))
    o.append(rect(lx + 132, 906, 12, 12, GOLD, rx=2, op=0.9))
    o.append(text(lx + 154, 916, 'Practical / lab', 12, MUTE, '500'))
    o.append(rect(lx + 286, 906, 12, 12, PAPER, rx=2, stroke=LINE))
    o.append(text(lx + 308, 916, 'Free period', 12, MUTE, '500'))

    # Fees, because the bursar is the other person who never leaves this app.
    rx = MX + 856
    rw = MR - rx
    o.append(card(rx, 324, rw, 300))
    o += section_head(rx + 22, 356, 'FEE COLLECTION')
    o += donut(rx + rw / 2, 470, 74, [(0.81, GOLD), (0.13, NAVY), (0.06, LINE)])
    o.append(text(rx + rw / 2, 466, '81%', 32, INK, '700', 'middle'))
    o.append(text(rx + rw / 2, 488, 'of target', 11.5, MUTE, '500', 'middle'))
    for i, (label, col, val) in enumerate([('Mobile Money', GOLD, '62%'),
                                           ('Bank / Visa', NAVY, '19%'),
                                           ('Outstanding', LINE, '19%')]):
        yy = 560 + i * 22
        o.append(rect(rx + 24, yy - 9, 11, 11, col, rx=2))
        o.append(text(rx + 44, yy, label, 12.5, MUTE, '500'))
        o.append(text(rx + rw - 24, yy, val, 12.5, INK, '700', 'end'))

    o.append(card(rx, 646, rw, 294))
    o += section_head(rx + 22, 678, 'PORTALS')
    for i, (who, n) in enumerate([('Parents', '1,884 active'),
                                  ('Teachers', '96 active'),
                                  ('Administrators', '12 active')]):
        yy = 706 + i * 76
        o.append(rect(rx + 22, yy, rw - 44, 62, PAPER, rx=5))
        o.append(circle(rx + 54, yy + 31, 17, GOLD, op=0.18))
        o.append(circle(rx + 54, yy + 26, 6, NAVY, op=0.55))
        o.append(path(f'M {rx + 42} {yy + 42} a 12 12 0 0 1 24 0', stroke=NAVY, sw=3, op=0.55))
        o.append(text(rx + 84, yy + 28, who, 13.5, INK, '700'))
        o.append(text(rx + 84, yy + 46, n, 11.5, MUTE, '400'))
    return o


# ── screen 3 — Hospital ───────────────────────────────────────────────────

def hospital():
    o = chrome(
        nav=['Ward board', 'Patients', 'Appointments', 'Laboratory', 'Pharmacy', 'Claims'],
        active=1,
        brand={'initials': 'GH', 'name': 'Global Health', 'org': 'Rescue · EHR',
               'glyph': glyph('M 47 90 v 20 M 37 100 h 20', sw=3.4)},
        window='globalhealthrescue.com — Hospital Management System',
        title='Patient record',
        subtitle='Ward B · admitted 14 Feb · consultant Dr. A. Nsubuga',
        kpis=[('PATIENTS TODAY', '312', '48 admitted overnight'),
              ('BED OCCUPANCY', '87%', '18 wards reporting'),
              ('CLAIMS CLEARED', '97.4%', 'insurance reconciliation')],
        actions=[('Print chart', False), ('New order', True)])

    # The record itself, opened. A hospital system is one patient at a time.
    o.append(card(MX, 324, 560, 616))
    o.append(rect(MX + 1, 325, 558, 128, PAPER, rx=6))
    o.append(circle(MX + 66, 388, 34, NAVY, op=0.1))
    o.append(circle(MX + 66, 376, 12, NAVY, op=0.4))
    o.append(path(f'M {MX + 44} 404 a 22 22 0 0 1 44 0', stroke=NAVY, sw=4, op=0.4))
    o.append(text(MX + 118, 372, 'Nakato, Sarah', 21, INK, '700'))
    o.append(text(MX + 118, 396, 'F · 34 · PID 0084-2261', 13, MUTE, '400'))
    parts, w = tag(MX + 118, 410, 'BLOOD O+', NAVY, '#E5E9EF')
    o += parts
    parts2, _ = tag(MX + 118 + w + 8, 410, 'NO KNOWN ALLERGIES', GREEN, '#E4EFE8')
    o += parts2

    o += section_head(MX + 24, 494, 'VITALS · LAST 12 HOURS')
    o.append(rect(MX + 24, 508, 512, 150, PAPER, rx=5))
    for i in range(1, 4):
        o.append(line(MX + 24, 508 + i * 37.5, MX + 536, 508 + i * 37.5, LINE, 1))
    o += spark(MX + 44, 530, 472, 108, [62, 74, 70, 88, 79, 92, 84, 96, 90], GOLD)
    o += spark(MX + 44, 530, 472, 108, [40, 44, 42, 50, 46, 52, 48, 46, 44], NAVY, fill=False)
    o.append(rect(MX + 24, 674, 11, 11, GOLD, rx=2))
    o.append(text(MX + 44, 684, 'Pulse', 12, MUTE, '500'))
    o.append(rect(MX + 122, 674, 11, 11, NAVY, rx=2))
    o.append(text(MX + 142, 684, 'Temperature', 12, MUTE, '500'))

    o += section_head(MX + 24, 730, 'ACTIVE ORDERS')
    for i, (label, sub, state, fg, bg) in enumerate([
            ('Full blood count', 'Laboratory · 09:20', 'RESULT IN', GREEN, '#E4EFE8'),
            ('Amoxicillin 500mg', 'Pharmacy · t.d.s 5 days', 'DISPENSED', NAVY, '#E5E9EF'),
            ('Chest X-ray', 'Radiology · requested 11:05', 'WAITING', GOLD, GOLD_P)]):
        yy = 748 + i * 64
        o.append(rect(MX + 24, yy, 512, 54, PAPER, rx=5))
        o.append(rect(MX + 24, yy, 4, 54, {'RESULT IN': GREEN, 'DISPENSED': NAVY,
                                           'WAITING': GOLD}[state], rx=2))
        o.append(text(MX + 44, yy + 23, label, 13.5, INK, '600'))
        o.append(text(MX + 44, yy + 41, sub, 11.5, MUTE, '400'))
        parts, w = tag(MX + 536 - 24, yy + 16, state, fg, bg)
        # right-align the pill
        shift = -w
        parts = [p.replace(f'x="{MX + 536 - 24}"', f'x="{MX + 536 - 24 + shift}"')
                 for p in parts]
        parts = [p.replace(f'x="{MX + 536 - 24 + w / 2}"', f'x="{MX + 536 - 24 + shift + w / 2}"')
                 for p in parts]
        o += parts

    # The day, as a column. What reception is looking at.
    rx = MX + 588
    rw = MR - rx
    o.append(card(rx, 324, rw, 616))
    o += section_head(rx + 22, 356, "TODAY'S CLINIC", (rw - 44, '18 SLOTS'))
    slots = [('08:00', 'Ssentongo, J.', 'Review', 1), ('08:30', 'Achieng, P.', 'New', 0),
             ('09:00', 'Nakato, S.', 'Ward round', 2), ('09:30', '', '', -1),
             ('10:00', 'Okot, D.', 'Post-op', 0), ('10:30', 'Birungi, M.', 'Antenatal', 1),
             ('11:00', '', '', -1), ('11:30', 'Kayondo, T.', 'Follow-up', 0),
             ('12:00', 'Amuge, R.', 'Referral', 1)]
    for i, (t, who, kind, k) in enumerate(slots):
        yy = 382 + i * 60
        o.append(text(rx + 22, yy + 26, t, 12, MUTE, '600'))
        if k < 0:
            o.append(rect(rx + 78, yy, rw - 100, 48, PAPER, rx=5))
            o.append(text(rx + 78 + (rw - 100) / 2, yy + 29, 'available', 12, MUTE_L,
                          '500', 'middle'))
            continue
        fillc = GOLD if k == 2 else (NAVY if k == 1 else WHITE)
        o.append(rect(rx + 78, yy, rw - 100, 48, fillc, rx=5,
                      stroke=LINE if k == 0 else None, op=0.95))
        tcol = WHITE if k == 1 else (NAVY if k == 2 else INK)
        o.append(text(rx + 94, yy + 21, who, 13, tcol, '700'))
        o.append(text(rx + 94, yy + 38, kind, 11.5, tcol, '400', op=0.62))
    return o


# ── screen 4 — Wildlife Offenders ─────────────────────────────────────────

def wildlife():
    o = chrome(
        nav=['Case board', 'Offenders', 'Seizures', 'Biometrics', 'Analytics', 'Rangers'],
        active=1,
        brand={'initials': 'UW', 'name': 'Offenders DB', 'org': 'Uganda Wildlife Auth.',
               'glyph': glyph('M 40 108 l 0 -12 a 7 7 0 0 1 14 0 l 0 12 M 37 96 l 10 -8 l 10 8')},
        window='Wildlife Offenders Database — Uganda Wildlife Authority',
        title='Offender file · UWA-4471',
        subtitle='Poaching · Murchison Falls · opened 3 Feb 2026',
        kpis=[('OPEN CASES', '1,286', 'across 10 parks'),
              ('OFFENDERS ON FILE', '4,371', 'biometrically matched'),
              ('CONVICTION RATE', '68%', 'up from 41% in 2021')],
        actions=[('Export brief', False), ('Escalate', True)])

    # Biometrics are what this system has that a spreadsheet does not.
    o.append(card(MX, 324, 400, 400))
    o += section_head(MX + 24, 356, 'BIOMETRIC MATCH')
    o.append(rect(MX + 24, 374, 352, 260, NAVY, rx=6))
    cx, cy = MX + 200, 504
    for i in range(7):
        r = 18 + i * 15
        o.append(path(f'M {cx - r} {cy + 8} a {r} {r * 1.15} 0 0 1 {2 * r} 0',
                      stroke=GOLD, sw=3, op=0.25 + i * 0.09))
    o.append(path(f'M {cx - 6} {cy + 8} a 8 9 0 0 1 12 0', stroke=GOLD, sw=3))
    o.append(line(cx - 92, cy - 74, cx - 92, cy - 44, GOLD, 3))
    o.append(line(cx - 92, cy - 74, cx - 62, cy - 74, GOLD, 3))
    o.append(line(cx + 92, cy + 78, cx + 92, cy + 48, GOLD, 3))
    o.append(line(cx + 92, cy + 78, cx + 62, cy + 78, GOLD, 3))
    o.append(text(MX + 24, 664, 'CONFIDENCE', 11, MUTE, '700', ls=1.1))
    o.append(text(MX + 376, 664, '99.2%', 15, GOLD, '700', 'end'))
    o.append(rect(MX + 24, 676, 352, 8, LINE, rx=4))
    o.append(rect(MX + 24, 676, 349, 8, GOLD, rx=4))
    o.append(text(MX + 24, 706, 'Matched against 4,371 records in 0.4s', 12, MUTE, '400'))

    # Where it happens.
    o.append(card(MX, 748, 400, 192))
    o += section_head(MX + 24, 780, 'INCIDENT HOTSPOTS')
    o += uganda_map((MX + 244, 776, 140, 154),
                    pins=[(31.72, 2.28, True), (31.35, 0.32, False),
                          (33.95, 0.72, False), (30.05, -0.35, False)],
                    fill='#EEEAE0', stroke=MUTE_L, sw=1.8, lakes=False)
    for i, (park, n) in enumerate([('Murchison Falls', '412'), ('Queen Elizabeth', '288'),
                                   ('Kidepo Valley', '134')]):
        yy = 818 + i * 32
        o.append(circle(MX + 30, yy - 4, 4, GOLD if i == 0 else NAVY,
                        op=1 if i == 0 else 0.5))
        o.append(text(MX + 44, yy, park, 12, MUTE, '500'))
        o.append(text(MX + 214, yy, n, 12.5, INK, '700', 'end'))

    # The case board — the actual working surface.
    rx = MX + 428
    rw = MR - rx
    o += section_head(rx, 356, 'CASE BOARD', (rw, 'FILTERED: MURCHISON'))
    o += table(rx, 374, rw,
               [('CASE', 190), ('OFFENCE', 250), ('RANGER POST', 210), ('', 0)],
               [[('t', 'UWA-4471'), ('m', 'Poaching · ivory'), ('m', 'Pakuba'),
                 ('p', ('IN COURT', GOLD, GOLD_P))],
                [('t', 'UWA-4468'), ('m', 'Illegal grazing'), ('m', 'Tangi'),
                 ('p', ('CHARGED', NAVY, '#E5E9EF'))],
                [('t', 'UWA-4462'), ('m', 'Bushmeat trade'), ('m', 'Bugungu'),
                 ('p', ('CLOSED', GREEN, '#E4EFE8'))],
                [('t', 'UWA-4455'), ('m', 'Snaring'), ('m', 'Karuma'),
                 ('p', ('IN COURT', GOLD, GOLD_P))],
                [('t', 'UWA-4450'), ('m', 'Trafficking'), ('m', 'Pakuba'),
                 ('p', ('ESCALATED', RED, '#F2E3E1'))],
                [('t', 'UWA-4441'), ('m', 'Poaching · pangolin'), ('m', 'Chobe'),
                 ('p', ('CHARGED', NAVY, '#E5E9EF'))]],
               rh=48)

    o += section_head(rx, 730, 'SEIZURES BY QUARTER')
    o.append(card(rx, 748, rw, 192))
    o += bars_chart(rx + 32, 786, rw - 64, 108,
                    [46, 62, 51, 78, 94, 71, 88, 112],
                    ['Q1', 'Q2', 'Q3', 'Q4', 'Q1', 'Q2', 'Q3', 'Q4'], hi=7)
    return o


# ── screen 5 — Seed tracking ──────────────────────────────────────────────

def seed():
    o = chrome(
        nav=['Batches', 'Verification', 'Vouchers', 'Agro-dealers', 'Quality', 'Exchange'],
        active=1,
        brand={'initials': 'MA', 'name': 'Seed Trace', 'org': 'MAAIF · Crop Insp.',
               'glyph': glyph('M 47 110 c -12 0 -12 -20 0 -20 c 12 0 12 20 0 20 z M 47 90 v -4')},
        window='National Seed Tracking & Tracing System',
        title='Field verification',
        subtitle='Batch NSC-2026-04471 · scanned at Kapchorwa agro-dealer',
        kpis=[('BATCHES TRACKED', '9,850', 'certified this season'),
              ('FIELD SCANS', '1.24M', 'by 3,100 inspectors'),
              ('COUNTERFEIT FLAGGED', '412', 'removed from market')],
        actions=[('Scan history', False), ('Certify batch', True)])

    # The scan. This whole system exists because a farmer needs to know, in a
    # shop, in thirty seconds, whether the bag in their hand is real seed.
    o.append(card(MX, 324, 392, 616))
    o += section_head(MX + 24, 356, 'SCANNED CODE')
    o += qr(MX + 56, 378, 328, seed=19)
    parts, w = tag(MX + 56, 726, 'VERIFIED AUTHENTIC', GREEN, '#E4EFE8', 12)
    o += parts
    o.append(text(MX + 24, 786, 'NSC-2026-04471', 24, INK, '700'))
    o.append(text(MX + 24, 810, 'Maize · Longe 10H · 2kg', 13, MUTE, '400'))
    for i, (k, v) in enumerate([('Certified', '14 Jan 2026'),
                                ('Germination', '94% · pass'),
                                ('Lot origin', 'Masindi multiplication')]):
        yy = 846 + i * 30
        o.append(text(MX + 24, yy, k, 12, MUTE, '500'))
        o.append(text(MX + 368, yy, v, 12, INK, '600', 'end'))

    # The chain of custody, which is the actual product.
    rx = MX + 420
    rw = MR - rx
    o.append(card(rx, 324, rw, 292))
    o += section_head(rx + 24, 356, 'CHAIN OF CUSTODY')
    stages = [('Breeder', 'NARO Namulonge', 1), ('Multiplier', 'Masindi farm', 1),
              ('Processor', 'NSC Kampala', 1), ('Agro-dealer', 'Kapchorwa', 2),
              ('Farmer', 'awaiting scan', 0)]
    sx = rx + 44
    step = (rw - 88) / (len(stages) - 1)
    o.append(line(sx, 452, sx + step * 4, 452, LINE, 4, cap='round'))
    o.append(line(sx, 452, sx + step * 3, 452, GOLD, 4, cap='round'))
    for i, (name, where, state) in enumerate(stages):
        x = sx + i * step
        if state == 2:
            o.append(circle(x, 452, 22, GOLD, op=0.2))
            o.append(circle(x, 452, 13, GOLD))
            o.append(path(f'M {x - 5} {452} l 4 4 l 7 -8', stroke=NAVY, sw=2.6))
        elif state == 1:
            o.append(circle(x, 452, 13, NAVY))
            o.append(path(f'M {x - 5} {452} l 4 4 l 7 -8', stroke=WHITE, sw=2.6))
        else:
            o.append(circle(x, 452, 13, WHITE, stroke=LINE, sw=3))
        o.append(text(x, 500, name, 12.5, INK if state else MUTE_L, '700', 'middle'))
        o.append(text(x, 518, where, 11, MUTE if state else MUTE_L, '400', 'middle'))
    o.append(text(rx + 24, 578, 'Every hand-off is signed and timestamped. A bag that '
                                'skips a stage cannot be sold.', 12.5, MUTE, '400'))

    # Vouchers — the money side.
    o.append(card(rx, 640, (rw - 24) / 2, 300))
    o += section_head(rx + 24, 672, 'VOUCHER REDEMPTION')
    o += donut(rx + (rw - 24) / 4, 790, 70, [(0.68, GOLD), (0.22, NAVY), (0.10, LINE)])
    o.append(text(rx + (rw - 24) / 4, 786, '68%', 30, INK, '700', 'middle'))
    o.append(text(rx + (rw - 24) / 4, 808, 'redeemed', 11.5, MUTE, '500', 'middle'))
    o.append(text(rx + 24, 900, '842,000 of 1.24M vouchers claimed', 12, MUTE, '400'))

    lx = rx + (rw - 24) / 2 + 24
    o.append(card(lx, 640, (rw - 24) / 2, 300))
    o += section_head(lx + 24, 672, 'FLAGGED THIS WEEK')
    for i, (code, why) in enumerate([('NSC-2026-03318', 'duplicate scan · Mbale'),
                                     ('NSC-2025-99120', 'expired certificate'),
                                     ('NSC-2026-04102', 'unregistered dealer'),
                                     ('NSC-2026-01877', 'germination below 80%')]):
        yy = 694 + i * 56
        o.append(rect(lx + 24, yy, (rw - 24) / 2 - 48, 46, PAPER, rx=5))
        o.append(rect(lx + 24, yy, 4, 46, RED, rx=2))
        o.append(text(lx + 42, yy + 20, code, 12.5, INK, '600'))
        o.append(text(lx + 42, yy + 36, why, 11, MUTE, '400'))
    return o


# ── screen 6 — PWD Observatory ────────────────────────────────────────────

def pwd():
    o = chrome(
        nav=['Observatory', 'Population', 'Access to ICT', 'Policy briefs', 'Regions', 'Sources'],
        active=2,
        brand={'initials': 'UC', 'name': 'ICT4PWD', 'org': 'UCC · NUDIPU',
               'glyph': glyph('M 47 92 v 8 M 47 100 l -8 12 M 47 100 l 8 12 M 39 96 h 16')},
        window='ICT for Persons with Disabilities Observatory',
        title='Access to ICT, by region',
        subtitle='National disability observatory · 2026 round · 1.4M records',
        kpis=[('PEOPLE ON RECORD', '1.42M', 'self-reported, consented'),
              ('DISTRICTS COVERED', '146', 'every district in Uganda'),
              ('OWN A SMARTPHONE', '31.4%', 'up 9 points since 2023')],
        actions=[('Methodology', False), ('Download data', True)])

    # A national chart, because this system's whole output is evidence.
    o.append(card(MX, 324, 806, 616))
    o += section_head(MX + 24, 356, 'SMARTPHONE OWNERSHIP BY SUB-REGION',
                      (758, '2023 → 2026'))
    regions = ['Central', 'Kampala', 'Eastern', 'Northern', 'Western', 'Karamoja',
               'West Nile', 'Elgon']
    then = [26, 41, 18, 12, 21, 6, 11, 16]
    now = [38, 57, 27, 19, 31, 11, 18, 24]
    bx, by, bw, bh = MX + 72, 402, 710, 420
    for i in range(5):
        yy = by + bh - i * (bh / 4)
        o.append(line(bx, yy, bx + bw, yy, LINE, 1))
        o.append(text(bx - 14, yy + 4, f'{i * 15}%', 11, MUTE_L, '500', 'end'))
    gw = bw / len(regions)
    for i, r in enumerate(regions):
        x = bx + i * gw
        h1 = then[i] / 60 * bh
        h2 = now[i] / 60 * bh
        o.append(rect(x + gw * 0.16, by + bh - h1, gw * 0.28, h1, NAVY, rx=3, op=0.28))
        o.append(rect(x + gw * 0.5, by + bh - h2, gw * 0.28, h2, GOLD if i == 1 else NAVY,
                      rx=3, op=1 if i == 1 else 0.85))
        o.append(text(x + gw / 2, by + bh + 24, r, 11.5, MUTE, '600', 'middle'))
    o.append(rect(MX + 24, 878, 11, 11, NAVY, rx=2, op=0.28))
    o.append(text(MX + 44, 888, '2023 round', 12, MUTE, '500'))
    o.append(rect(MX + 148, 878, 11, 11, NAVY, rx=2, op=0.85))
    o.append(text(MX + 168, 888, '2026 round', 12, MUTE, '500'))
    o.append(text(MX + 24, 918, 'Weighted to the national population. '
                                'Collected with NUDIPU district associations.', 12, MUTE_L, '400'))

    # What the evidence is for.
    rx = MX + 834
    rw = MR - rx
    o.append(card(rx, 324, rw, 292))
    o += section_head(rx + 22, 356, 'ASSISTIVE TECHNOLOGY')
    for i, (label, pct) in enumerate([('Screen reader', 22), ('Hearing aid', 34),
                                      ('Braille display', 7), ('Mobility aid', 58)]):
        yy = 386 + i * 54
        o.append(text(rx + 22, yy + 12, label, 12.5, INK, '600'))
        o.append(text(rx + rw - 22, yy + 12, f'{pct}%', 12.5, GOLD, '700', 'end'))
        o.append(rect(rx + 22, yy + 22, rw - 44, 9, LINE, rx=5))
        o.append(rect(rx + 22, yy + 22, (rw - 44) * pct / 100, 9, GOLD, rx=5))

    o.append(rect(rx, 640, rw, 300, NAVY, rx=6))
    o += section_head(rx + 22, 672, 'POLICY BRIEFS')
    for i, (t, d) in enumerate([('Universal service fund', 'tabled · Mar 2026'),
                                ('Accessible procurement', 'adopted · Nov 2025'),
                                ('Sign language on TV', 'in review')]):
        yy = 700 + i * 74
        o.append(rect(rx + 22, yy, rw - 44, 60, WHITE, rx=5, op=0.07))
        o.append(rect(rx + 22, yy, 4, 60, GOLD, rx=2))
        o.append(text(rx + 42, yy + 26, t, 13, WHITE, '700'))
        o.append(text(rx + 42, yy + 44, d, 11.5, WHITE, '400', op=0.55))
    return o


# ── screen 7 — Human rights reporting ─────────────────────────────────────

def rights():
    o = chrome(
        nav=['Case register', 'Intake', 'Evidence vault', 'Referrals', 'Trends', 'Audit log'],
        active=2,
        brand={'initials': 'CE', 'name': 'CEHURD Cases', 'org': 'Restricted access',
               'glyph': glyph('M 40 100 v -6 a 7 7 0 0 1 14 0 v 6 M 37 100 h 20 v 12 h -20 z')},
        window='CEHURD — Human Rights Reporting System',
        title='Evidence vault',
        subtitle='Case 2026-0918 · sealed · 3 people hold access',
        kpis=[('CASES DOCUMENTED', '4,930', 'since 2019'),
              ('EVIDENCE ITEMS', '18,240', 'encrypted at rest'),
              ('ENCRYPTION', 'AES-256', 'keys held off-platform')],
        actions=[('Audit log', False), ('Add evidence', True)])

    # The lock is the product. Everything else follows from it.
    o.append(rect(MX, 324, 440, 340, NAVY, rx=6))
    lx, ly = MX + 220, 452
    o.append(circle(lx, ly, 74, GOLD, op=0.1))
    o.append(circle(lx, ly, 56, GOLD, op=0.14))
    o.append(path(f'M {lx - 26} {ly - 4} v -18 a 26 26 0 0 1 52 0 v 18',
                  stroke=GOLD, sw=6))
    o.append(rect(lx - 40, ly - 6, 80, 62, GOLD, rx=8))
    o.append(circle(lx, ly + 18, 7, NAVY))
    o.append(rect(lx - 3, ly + 22, 6, 14, NAVY, rx=3))
    o.append(text(MX + 220, 588, 'Sealed evidence', 19, WHITE, '700', 'middle'))
    o.append(text(MX + 220, 612, 'Opened 4 times · every open is logged',
                  12.5, WHITE, '400', 'middle', op=0.55))
    o.append(text(MX + 220, 634, 'against a named person and a reason',
                  12.5, WHITE, '400', 'middle', op=0.55))

    o.append(card(MX, 688, 440, 252))
    o += section_head(MX + 24, 720, 'ATTACHED EVIDENCE')
    for i, (kind, name, size) in enumerate([('IMG', 'Clinic intake photograph', '2.4 MB'),
                                            ('PDF', 'Medical report, redacted', '840 KB'),
                                            ('AUD', 'Witness statement', '11 min'),
                                            ('DOC', 'Referral letter', '96 KB')]):
        yy = 740 + i * 48
        o.append(rect(MX + 24, yy, 392, 40, PAPER, rx=5))
        o.append(rect(MX + 32, yy + 8, 34, 24, NAVY, rx=3, op=0.85))
        o.append(text(MX + 49, yy + 24, kind, 9.5, WHITE, '700', 'middle', 0.6))
        o.append(text(MX + 78, yy + 25, name, 12.5, INK, '500'))
        o.append(text(MX + 404, yy + 25, size, 11.5, MUTE_L, '400', 'end'))

    # The case's life, and what the register is for.
    rx = MX + 468
    rw = MR - rx
    o.append(card(rx, 324, rw, 340))
    o += section_head(rx + 24, 356, 'CASE TIMELINE')
    events = [('12 Jan', 'Intake recorded', 'Kampala field office', 1),
              ('19 Jan', 'Evidence sealed', '4 items · AES-256', 2),
              ('02 Feb', 'Referred to counsel', 'Legal aid partner', 1),
              ('21 Feb', 'Filed in court', 'High Court, civil division', 1),
              ('—', 'Hearing scheduled', 'awaiting date', 0)]
    tx = rx + 116
    o.append(line(tx, 396, tx, 396 + (len(events) - 1) * 54, LINE, 2))
    for i, (when, what, note, state) in enumerate(events):
        yy = 396 + i * 54
        o.append(text(rx + 96, yy + 5, when, 11.5, MUTE, '600', 'end'))
        if state == 2:
            o.append(circle(tx, yy, 9, GOLD))
            o.append(circle(tx, yy, 16, GOLD, op=0.2))
        elif state == 1:
            o.append(circle(tx, yy, 7, NAVY))
        else:
            o.append(circle(tx, yy, 7, WHITE, stroke=LINE, sw=2.5))
        o.append(text(tx + 26, yy + 1, what, 13.5, INK if state else MUTE_L, '700'))
        o.append(text(tx + 26, yy + 18, note, 11.5, MUTE, '400'))

    o.append(card(rx, 688, rw, 252))
    o += section_head(rx + 24, 720, 'CASES DOCUMENTED, BY MONTH',
                      (rw - 48, 'TREND'))
    o += spark(rx + 40, 750, rw - 80, 150,
               [22, 31, 27, 44, 38, 52, 47, 61, 58, 72, 66, 84], GOLD)
    o.append(text(rx + 24, 926, 'A rising line here is reporting getting easier, '
                                'not rights getting worse.', 12, MUTE_L, '400'))
    return o


# ── screen 8 — E-commerce & real estate ───────────────────────────────────

def commerce():
    o = chrome(
        nav=['Storefront', 'Listings', 'Orders', 'Inventory', 'Customers', 'Payouts'],
        active=1,
        brand={'initials': 'AI', 'name': 'AfriInventions', 'org': 'Commerce · Estates',
               'glyph': glyph('M 38 96 h 18 l -3 14 h -12 z M 42 96 v -4 a 5 5 0 0 1 10 0 v 4')},
        window='afriinventions.com — commerce & estates back office',
        title='Listings & orders',
        subtitle='Two storefronts on one back office · 1,860 live listings',
        kpis=[('REVENUE THIS MONTH', 'UGX 214M', 'across both platforms'),
              ('LIVE LISTINGS', '1,860', '412 properties · 1,448 goods'),
              ('CHECKOUT SUCCESS', '96.8%', 'Mobile Money & Visa')],
        actions=[('Reports', False), ('Add listing', True)])

    # The grid a merchant manages, with real prices — the thing they check.
    o += section_head(MX, 356, 'RECENTLY LISTED', (826, 'VIEW ALL 1,860'))
    items = [('Kololo · 4 bed', 'UGX 780M', 2, 'PROPERTY'),
             ('Solar home kit', 'UGX 1.2M', 0, 'STOCK 34'),
             ('Naguru · plot', 'UGX 240M', 1, 'LAND'),
             ('Water pump, 2HP', 'UGX 640K', 0, 'STOCK 8'),
             ('Ntinda · 2 bed', 'UGX 320M', 2, 'PROPERTY'),
             ('Irrigation set', 'UGX 2.4M', 0, 'STOCK 12')]
    cw2, ch = 262, 268
    for i, (name, price, kind, meta) in enumerate(items):
        cx = MX + (i % 3) * (cw2 + 20)
        cy = 374 + (i // 3) * (ch + 20)
        o.append(card(cx, cy, cw2, ch))
        o.append(rect(cx + 1, cy + 1, cw2 - 2, 150, PAPER, rx=5))
        if kind == 2:
            # A house, drawn.
            o.append(path(f'M {cx + 78} {cy + 96} l 53 -40 l 53 40 v 44 h -106 z',
                          fill=GOLD, op=0.9))
            o.append(rect(cx + 118, cy + 108, 26, 32, NAVY, rx=2))
            o.append(path(f'M {cx + 70} {cy + 100} l 61 -46 l 61 46', stroke=NAVY, sw=4))
        elif kind == 1:
            # A surveyed plot: a parcel boundary with a pin on it.
            o.append(path(f'M {cx + 80} {cy + 118} l 22 -60 l 82 12 l -18 60 z',
                          fill=GOLD, op=0.45, stroke=GOLD, sw=3))
            o.append(line(cx + 91, cy + 88, cx + 165, cy + 100, NAVY, 1.4, dash='6 5', op=0.4))
            o.append(path(f'M {cx + 131} {cy + 74} c -8 0 -14 6 -14 14 '
                          f'c 0 10 14 24 14 24 s 14 -14 14 -24 c 0 -8 -6 -14 -14 -14 z',
                          fill=NAVY))
            o.append(circle(cx + 131, cy + 88, 5, GOLD))
        else:
            # A carton, because these are things that get shipped.
            o.append(path(f'M {cx + 88} {cy + 74} l 43 -20 l 43 20 v 50 l -43 20 '
                          f'l -43 -20 z', fill=NAVY, op=0.88))
            o.append(path(f'M {cx + 88} {cy + 74} l 43 20 l 43 -20', stroke=WHITE,
                          sw=2.4, op=0.4))
            o.append(line(cx + 131, cy + 94, cx + 131, cy + 144, WHITE, 2.4, op=0.4))
            o.append(rect(cx + 116, cy + 46, 30, 20, GOLD, rx=2))
        o.append(text(cx + 20, cy + 186, name, 14, INK, '700'))
        o.append(text(cx + 20, cy + 212, price, 17, GOLD, '700'))
        parts, _ = tag(cx + 20, cy + 226, meta, MUTE, PAPER, 10)
        o += parts

    # The money, over time.
    rx = MX + 854
    rw = MR - rx
    o.append(card(rx, 324, rw, 300))
    o += section_head(rx + 22, 356, 'REVENUE, LAST 12 MONTHS')
    o += spark(rx + 34, 386, rw - 68, 152,
               [58, 64, 61, 79, 86, 74, 98, 112, 104, 138, 152, 214], GOLD)
    o.append(text(rx + 22, 578, 'UGX 214M', 26, INK, '700'))
    o.append(text(rx + 22, 600, '+41% on the same month last year', 12, GREEN, '600'))

    o.append(card(rx, 646, rw, 294))
    o += section_head(rx + 22, 678, 'ORDERS TODAY')
    for i, (ref, who, amt, state, fg, bg) in enumerate([
            ('#48210', 'MTN MoMo', 'UGX 1.2M', 'PAID', GREEN, '#E4EFE8'),
            ('#48209', 'Visa', 'UGX 640K', 'PAID', GREEN, '#E4EFE8'),
            ('#48208', 'Airtel Money', 'UGX 2.4M', 'PENDING', GOLD, GOLD_P),
            ('#48207', 'MTN MoMo', 'UGX 320K', 'PAID', GREEN, '#E4EFE8')]):
        yy = 700 + i * 58
        o.append(rect(rx + 22, yy, rw - 44, 48, PAPER, rx=5))
        o.append(text(rx + 38, yy + 21, ref, 12.5, INK, '700'))
        o.append(text(rx + 38, yy + 37, who, 11, MUTE, '400'))
        o.append(text(rx + rw - 38, yy + 21, amt, 12.5, INK, '600', 'end'))
        parts, w = tag(rx + rw - 38 - 74, yy + 26, state, fg, bg, 9.5)
        o += parts
    return o


SCREENS = {
    'ulits': ulits,
    'school-dynamics': school,
    'hospital-management': hospital,
    'wildlife-offenders': wildlife,
    'seed-tracking': seed,
    'pwd-observatory': pwd,
    'human-rights-reporting': rights,
    'ecommerce-realestate': commerce,
}


if __name__ == '__main__':
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       '..', 'public', 'images', 'systems')
    os.makedirs(out, exist_ok=True)
    for slug, fn in SCREENS.items():
        svg = frame(fn())
        p = os.path.join(out, f'{slug}.svg')
        with open(p, 'w', encoding='utf-8') as f:
            f.write(svg)
        print(f'{slug:26} {len(svg) / 1024:6.1f} KB')
