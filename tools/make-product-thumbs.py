#!/usr/bin/env python3
"""
Item thumbnails for the source-code shop.

A marketplace thumbnail has about one second to answer four questions: what is
it, what is it built in, what does it look like running, and why this one. The
ones that work on ThemeForest all solve it the same way — a wordmark, a single
promise, a short list of what you get, a device showing the real interface,
the stack across the bottom, and a corner flag for the version or the offer.

So that is the grammar here. What changes between the six is everything else:
each has its own palette, its own device arrangement and its own screen
content, because a hotel booking system and a marketplace should not look like
two colourways of one product.

    python3 tools/make-product-thumbs.py
"""

import math
import os

W, H = 1600, 1000
FONT = "Helvetica Neue, Helvetica, Arial, sans-serif"
MONO = "SFMono-Regular, Menlo, Consolas, monospace"


# ── primitives ────────────────────────────────────────────────────────────

def esc(s):
    return s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')


def rect(x, y, w, h, fill, rx=0, stroke=None, sw=1, op=None):
    a = f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" fill="{fill}"'
    if rx:
        a += f' rx="{rx}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def circle(cx, cy, r, fill, stroke=None, sw=1, op=None):
    a = f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{r:.1f}" fill="{fill}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def text(x, y, s, size=14, fill='#FFFFFF', weight='400', anchor='start', ls=0,
         op=None, mono=False):
    a = (f'<text x="{x:.1f}" y="{y:.1f}" font-family="{MONO if mono else FONT}" '
         f'font-size="{size}" font-weight="{weight}" fill="{fill}" text-anchor="{anchor}"')
    if ls:
        a += f' letter-spacing="{ls}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + f'>{esc(s)}</text>'


def line(x1, y1, x2, y2, stroke, sw=1, dash=None, cap='butt', op=None):
    a = (f'<line x1="{x1:.1f}" y1="{y1:.1f}" x2="{x2:.1f}" y2="{y2:.1f}" '
         f'stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}"')
    if dash:
        a += f' stroke-dasharray="{dash}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def path(d, fill='none', stroke=None, sw=1, cap='round', join='round', op=None):
    a = f'<path d="{d}" fill="{fill}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}" stroke-linejoin="{join}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def tw(s, size, weight='400'):
    narrow = sum(1 for c in s if c in 'iljtfrI.,:;()[]| ')
    wide = sum(1 for c in s if c.isupper() or c in 'mwMW@%')
    base = size * (0.57 if weight in ('600', '700', '800') else 0.53)
    return len(s) * base + wide * size * 0.10 - narrow * size * 0.19


def grad(uid, a, b, angle='diag'):
    coords = ('x1="0" y1="0" x2="1" y2="1"' if angle == 'diag'
              else 'x1="0" y1="0" x2="0" y2="1"')
    return (f'<linearGradient id="{uid}" {coords}>'
            f'<stop offset="0" stop-color="{a}"/><stop offset="1" stop-color="{b}"/>'
            f'</linearGradient>')


def check(x, y, col, size=9):
    return path(f'M {x - size * 0.45} {y} l {size * 0.32} {size * 0.36} l {size * 0.68} {-size * 0.72}',
                stroke=col, sw=2.6)


# ── the shared grammar ────────────────────────────────────────────────────

LEFT = 78          # text column starts here
COL = 700          # and is this wide


def frame(body, defs):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000" '
            'width="1600" height="1000" role="img">\n  <defs>'
            + ''.join(defs) + '</defs>\n  '
            + '\n  '.join(body) + '\n</svg>\n')


def backdrop(p, uid):
    """
    Brand colour, and something behind it.

    A flat fill reads as a placeholder; the marketplace cards that work all
    have some geometry under the type. This is the same trick eight times with
    different maths, so the set stays a set.
    """
    o = [rect(0, 0, W, H, f'url(#{uid}-bg)')]
    kind = p['texture']

    if kind == 'rings':
        for i in range(9):
            o.append(circle(W * 0.14, H * 0.86, 90 + i * 92, 'none',
                            stroke='#FFFFFF', sw=1.6, op=0.055))
    elif kind == 'grid':
        for i in range(24):
            o.append(line(i * 70, 0, i * 70 - 260, H, '#FFFFFF', 1, op=0.05))
        for i in range(11):
            o.append(line(0, i * 100, W, i * 100, '#FFFFFF', 1, op=0.04))
    elif kind == 'blobs':
        for cx, cy, r in ((180, 180, 260), (1420, 820, 320), (1180, 90, 180)):
            o.append(circle(cx, cy, r, '#FFFFFF', op=0.05))
    elif kind == 'bars':
        for i in range(16):
            o.append(rect(-120 + i * 130, -60, 46, H + 120, '#FFFFFF', op=0.035))
    elif kind == 'dots':
        for r in range(11):
            for c in range(23):
                o.append(circle(40 + c * 70, 40 + r * 94, 3, '#FFFFFF', op=0.07))
    else:  # arcs
        for i in range(7):
            rr = 220 + i * 120
            o.append(path(f'M {W} {H - rr} A {rr} {rr} 0 0 0 {W - rr} {H}',
                          stroke='#FFFFFF', sw=1.8, op=0.06))

    # A wash under the type so the copy never fights the texture.
    o.append(rect(0, 0, 820, H, '#000000', op=0.18))
    return o


def ribbon(p):
    """The corner flag. Version, because it is a fact — not an invented sale."""
    label = 'v' + p['version']
    w = tw(label, 15, '800') + 44
    o = [path(f'M {W} 0 L {W} 92 L {W - w - 46} 92 L {W - w - 6} 0 Z', fill='#000000', op=0.2),
         path(f'M {W} 0 L {W} 84 L {W - w - 38} 84 L {W - w} 0 Z', fill=p['accent'])]
    o.append(text(W - w / 2 - 12, 52, label, 17, p['on_accent'], '800', 'middle', 0.5))
    return o


def wordmark(p, y):
    """The logo lockup: a mark, the name, and who made it."""
    o = [rect(LEFT, y, 62, 62, p['accent'], rx=15)]
    o += p['mark'](LEFT + 31, y + 31, p)
    o.append(text(LEFT + 80, y + 27, p['brand'], 25, '#FFFFFF', '800', ls=-0.3))
    o.append(text(LEFT + 80, y + 50, p['kicker'], 13, '#FFFFFF', '600', ls=1.6, op=0.62))
    return o


def bullets(p, y):
    o = []
    for i, item in enumerate(p['bullets']):
        by = y + i * 42
        o.append(circle(LEFT + 11, by, 12, p['accent'], op=0.22))
        o.append(check(LEFT + 11, by, p['accent'], 11))
        o.append(text(LEFT + 34, by + 6, item, 17.5, '#FFFFFF', '500', op=0.94))
    return o


def chips(p, y):
    """The stack, across the bottom, the way every marketplace card ends."""
    o = []
    x = LEFT
    for label in p['stack']:
        w = tw(label, 13, '700') + 32
        o.append(rect(x, y, w, 34, '#FFFFFF', rx=17, op=0.14))
        o.append(rect(x, y, w, 34, 'none', rx=17, stroke='#FFFFFF', sw=1, op=0.22))
        o.append(text(x + w / 2, y + 22, label, 13, '#FFFFFF', '700', 'middle', 0.4, op=0.92))
        x += w + 10
    return o


# ── devices ───────────────────────────────────────────────────────────────

def browser(x, y, w, screen, p, tilt=0):
    """A desktop window. The chrome is what says 'this is software running'."""
    h = w * 0.66
    g = (f'<g transform="translate({x:.0f} {y:.0f})'
         + (f' rotate({tilt} 0 0)' if tilt else '') + '">')
    o = [g]
    o.append(rect(10, 16, w, h, '#000000', rx=12, op=0.28))
    o.append(rect(0, 0, w, h, '#FFFFFF', rx=12))
    o.append(rect(0, 0, w, 34, '#EEF0F3', rx=12))
    o.append(rect(0, 22, w, 12, '#EEF0F3'))
    for i, c in enumerate(('#FF5F57', '#FEBC2E', '#28C840')):
        o.append(circle(20 + i * 17, 17, 5, c))
    o.append(rect(70, 8, w - 96, 18, '#FFFFFF', rx=9))
    o.append(text(84, 21, p['url'], 10.5, '#9AA1AC', '500'))
    o.append(f'<g transform="translate(0 34)">')
    o += screen(w, h - 34, p)
    o.append('</g>')
    o.append('</g>')
    return o


def handset(x, y, w, screen, p):
    h = w * 2.03
    o = [f'<g transform="translate({x:.0f} {y:.0f})">']
    o.append(rect(8, 14, w + 16, h + 16, '#000000', rx=34, op=0.3))
    o.append(rect(-8, -8, w + 16, h + 16, '#12151A', rx=34))
    o.append(rect(0, 0, w, h, '#FFFFFF', rx=27))
    o.append(f'<g transform="translate(0 0)">')
    o += screen(w, h, p)
    o.append('</g>')
    o.append(rect(w / 2 - 24, 7, 48, 9, '#12151A', rx=5))
    o.append('</g>')
    return o


# ── screen fragments the mockups are built from ───────────────────────────

def ui_sidebar(w, h, p, items=6, active=1):
    sw_ = w * 0.21
    o = [rect(0, 0, sw_, h, p['ui_dark'])]
    o.append(rect(14, 14, 20, 20, p['accent'], rx=6))
    o.append(rect(40, 20, sw_ - 56, 8, '#FFFFFF', rx=4, op=0.55))
    for i in range(items):
        yy = 52 + i * 26
        if i == active:
            o.append(rect(7, yy - 7, sw_ - 14, 22, p['accent'], rx=5, op=0.25))
        o.append(rect(14, yy - 2, 9, 9, '#FFFFFF', rx=2, op=0.9 if i == active else 0.35))
        o.append(rect(30, yy, sw_ - 46, 7, '#FFFFFF', rx=3.5, op=0.8 if i == active else 0.25))
    return o, sw_


def ui_head(x, w, p, title, cta):
    o = [text(x + 18, 32, title, 15, '#1B2430', '700')]
    cw = tw(cta, 9.5, '700') + 22
    o.append(rect(x + w - cw - 18, 18, cw, 22, p['accent'], rx=5))
    o.append(text(x + w - cw / 2 - 18, 33, cta, 9.5, p['on_accent'], '700', 'middle'))
    return o


def ui_kpis(x, y, w, p, values):
    o = []
    cw = (w - 30) / 3
    for i, (label, value) in enumerate(values):
        bx = x + i * (cw + 10)
        o.append(rect(bx, y, cw, 46, '#F6F7F9', rx=6))
        o.append(text(bx + 10, y + 17, label, 7, '#9AA1AC', '700', ls=0.6))
        o.append(text(bx + 10, y + 36, value, 15, p['ui_dark'] if i < 2 else p['accent'], '800'))
    return o


def ui_rows(x, y, w, p, n=4, cols=(0.42, 0.24, 0.18), pill_at=None):
    o = []
    for r in range(n):
        ry = y + r * 26
        o.append(rect(x, ry, w, 22, '#FFFFFF' if r % 2 else '#F9FAFB', rx=4))
        cx = x + 10
        for i, frac in enumerate(cols):
            cw = w * frac - 12
            o.append(rect(cx, ry + 8, cw, 6, '#1B2430', rx=3,
                          op=0.62 if i == 0 else 0.22))
            cx += w * frac
        if pill_at is not None:
            o.append(rect(x + w - 46, ry + 5, 38, 12, p['accent'], rx=6,
                          op=0.9 if r == pill_at else 0.28))
    return o


def ui_chart(x, y, w, h, p, values):
    o = [rect(x, y, w, h, '#F6F7F9', rx=6)]
    n = len(values)
    bw = (w - 24 - (n - 1) * 7) / n
    top = max(values)
    for i, v in enumerate(values):
        bh = (v / top) * (h - 24)
        o.append(rect(x + 12 + i * (bw + 7), y + h - 12 - bh, bw, bh,
                      p['accent'] if i == n - 2 else p['ui_dark'],
                      rx=2, op=1 if i == n - 2 else 0.5))
    return o


def ui_cards(x, y, w, p, n=3, rows=2):
    o = []
    cw = (w - (n - 1) * 8) / n
    for r in range(rows):
        for c in range(n):
            bx, by = x + c * (cw + 8), y + r * 62
            o.append(rect(bx, by, cw, 54, '#FFFFFF', rx=5, stroke='#E7EAEE', sw=1))
            o.append(rect(bx, by, cw, 28, p['accent'], rx=5, op=0.16))
            o.append(rect(bx + 8, by + 34, cw - 30, 6, '#1B2430', rx=3, op=0.55))
            o.append(rect(bx + 8, by + 44, cw * 0.4, 5, p['accent'], rx=2.5))
    return o


# ── the six items ─────────────────────────────────────────────────────────

def mk_box(x, y, p):
    return [path(f'M {x} {y - 13} l 12 6.5 v 13 l -12 6.5 l -12 -6.5 v -13 z',
                 stroke=p['on_accent'], sw=2.4),
            path(f'M {x - 12} {y - 6.5} l 12 6.5 l 12 -6.5', stroke=p['on_accent'], sw=2.4)]


def mk_store(x, y, p):
    return [path(f'M {x - 13} {y - 3} h 26 l -2.5 17 h -21 z', stroke=p['on_accent'], sw=2.4),
            path(f'M {x - 6} {y - 3} v -4 a 6 6 0 0 1 12 0 v 4', stroke=p['on_accent'], sw=2.4),
            path(f'M {x - 13} {y - 3} l 3 -9 h 20 l 3 9', stroke=p['on_accent'], sw=2.4)]


def mk_bed(x, y, p):
    return [path(f'M {x - 14} {y + 10} v -18 M {x - 14} {y + 2} h 28 v 8',
                 stroke=p['on_accent'], sw=2.6),
            circle(x - 6, y - 4, 3.4, 'none', stroke=p['on_accent'], sw=2.2),
            path(f'M {x - 1} {y + 2} v -5 h 15 v 5', stroke=p['on_accent'], sw=2.2)]


def mk_cart(x, y, p):
    return [path(f'M {x - 13} {y - 9} h 4 l 4 15 h 14 l 3 -10 h -18',
                 stroke=p['on_accent'], sw=2.4),
            circle(x - 3, y + 11, 2.6, p['on_accent']),
            circle(x + 8, y + 11, 2.6, p['on_accent'])]


def mk_flame(x, y, p):
    return [path(f'M {x} {y - 14} c 8 8 10 12 10 18 a 10 10 0 0 1 -20 0 '
                 f'c 0 -5 3 -8 5 -11 c 1 4 3 5 5 7 z', stroke=p['on_accent'], sw=2.4)]


def mk_panel(x, y, p):
    return [rect(x - 13, y - 12, 26, 24, 'none', rx=4, stroke=p['on_accent'], sw=2.4),
            line(x - 3, y - 12, x - 3, y + 12, p['on_accent'], 2.4),
            line(x - 3, y - 2, x + 13, y - 2, p['on_accent'], 2.4)]


# ---- screen bodies ------------------------------------------------------

def scr_inventory(w, h, p):
    o, sw_ = ui_sidebar(w, h, p, active=2)
    o += ui_head(sw_, w - sw_, p, 'Stock overview', 'New sale')
    o += ui_kpis(sw_ + 18, 48, w - sw_ - 36, p,
                 [('SKUS', '1,284'), ('LOW STOCK', '17'), ('TODAY', '2.4M')])
    o += ui_chart(sw_ + 18, 108, (w - sw_ - 46) * 0.52, 96, p, [40, 62, 48, 78, 92, 70])
    o += ui_rows(sw_ + 18 + (w - sw_ - 46) * 0.52 + 14, 108,
                 (w - sw_ - 46) * 0.48, p, n=4, pill_at=1)
    o += ui_rows(sw_ + 18, 218, w - sw_ - 36, p, n=3, pill_at=0)
    return o


def scr_market(w, h, p):
    o, sw_ = ui_sidebar(w, h, p, active=1)
    o += ui_head(sw_, w - sw_, p, 'Vendors & orders', 'Approve')
    o += ui_kpis(sw_ + 18, 48, w - sw_ - 36, p,
                 [('SELLERS', '312'), ('ORDERS', '4,908'), ('PAYOUTS', '18.6M')])
    o += ui_cards(sw_ + 18, 108, w - sw_ - 36, p, n=4, rows=1)
    o += ui_rows(sw_ + 18, 182, w - sw_ - 36, p, n=5, pill_at=2)
    return o


def scr_hotel(w, h, p):
    o, sw_ = ui_sidebar(w, h, p, active=1)
    o += ui_head(sw_, w - sw_, p, 'Availability', 'New booking')
    # A room-by-night grid, which is the screen a hotel actually lives in.
    gx, gy = sw_ + 18, 54
    gw = w - sw_ - 36
    cw = gw / 14
    for c in range(14):
        o.append(text(gx + c * cw + cw / 2, gy - 4, str(11 + c), 7, '#9AA1AC', '700', 'middle'))
    booked = {(0, 2), (0, 3), (0, 4), (1, 0), (1, 1), (2, 5), (2, 6), (2, 7), (2, 8),
              (3, 3), (3, 4), (4, 9), (4, 10), (4, 11), (5, 1), (5, 2), (0, 9), (0, 10),
              (3, 11), (3, 12), (1, 7), (1, 8), (5, 6), (2, 12), (4, 2)}
    hot = {(2, 6), (2, 7), (3, 3)}
    for r in range(6):
        o.append(rect(gx - 16, gy + r * 26, 14, 18, p['ui_dark'], rx=3, op=0.18))
        for c in range(14):
            key = (r, c)
            fill = p['accent'] if key in hot else (p['ui_dark'] if key in booked else '#EEF1F4')
            op = 1 if key in hot else (0.55 if key in booked else 1)
            o.append(rect(gx + c * cw + 1, gy + r * 26, cw - 2, 18, fill, rx=3, op=op))
    o += ui_rows(gx, gy + 172, gw, p, n=2, pill_at=0)
    return o


def scr_shop(w, h, p):
    o = [rect(0, 0, w, h, '#FFFFFF')]
    o.append(rect(0, 0, w, 34, p['ui_dark']))
    o.append(rect(16, 12, 46, 10, '#FFFFFF', rx=5, op=0.85))
    for i in range(4):
        o.append(rect(84 + i * 46, 14, 32, 7, '#FFFFFF', rx=3.5, op=0.4))
    o.append(rect(w - 92, 10, 72, 14, '#FFFFFF', rx=7, op=0.18))
    o.append(circle(w - 30, 17, 8, p['accent']))
    # Hero, then the catalogue.
    o.append(rect(16, 46, w - 32, 66, p['accent'], rx=6, op=0.18))
    o.append(rect(32, 62, 150, 11, p['ui_dark'], rx=5, op=0.7))
    o.append(rect(32, 80, 96, 8, p['ui_dark'], rx=4, op=0.35))
    o.append(rect(32, 94, 54, 12, p['accent'], rx=6))
    o += ui_cards(16, 124, w - 32, p, n=4, rows=2)
    return o


def scr_firebase_a(w, h, p):
    o = [rect(0, 0, w, h, '#FFFFFF', rx=27)]
    o.append(rect(0, 0, w, 74, p['ui_dark']))
    o.append(rect(18, 34, 74, 11, '#FFFFFF', rx=5, op=0.9))
    o.append(rect(18, 52, 46, 7, '#FFFFFF', rx=3.5, op=0.4))
    o.append(circle(w - 28, 44, 11, p['accent']))
    o.append(rect(16, 88, w - 32, 30, '#F1F3F6', rx=15))
    o.append(circle(36, 103, 6, '#9AA1AC'))
    o.append(rect(50, 99, 88, 8, '#9AA1AC', rx=4, op=0.6))
    for r in range(3):
        for c in range(2):
            bx, by = 16 + c * ((w - 40) / 2 + 8), 130 + r * 92
            bw = (w - 40) / 2
            o.append(rect(bx, by, bw, 82, '#FFFFFF', rx=8, stroke='#E7EAEE', sw=1))
            o.append(rect(bx, by, bw, 48, p['accent'], rx=8, op=0.16))
            o.append(rect(bx + 8, by + 56, bw - 26, 6, '#1B2430', rx=3, op=0.55))
            o.append(rect(bx + 8, by + 68, bw * 0.42, 6, p['accent'], rx=3))
    o.append(rect(0, h - 52, w, 52, '#FFFFFF'))
    o.append(line(0, h - 52, w, h - 52, '#E7EAEE', 1))
    for i in range(4):
        cx = w / 8 + i * w / 4
        o.append(rect(cx - 8, h - 36, 16, 16, p['accent'] if i == 0 else '#C7CDD4', rx=4))
    return o


def scr_firebase_b(w, h, p):
    o = [rect(0, 0, w, h, '#FFFFFF', rx=27)]
    o.append(rect(0, 0, w, 210, p['accent'], op=0.2))
    o.append(circle(w / 2, 108, 52, p['accent'], op=0.4))
    o.append(rect(0, 226, w, 12, '#FFFFFF'))
    o.append(rect(18, 232, w - 100, 12, '#1B2430', rx=6, op=0.75))
    o.append(rect(18, 254, 76, 14, p['accent'], rx=7))
    o.append(rect(18, 286, w - 36, 7, '#9AA1AC', rx=3.5, op=0.5))
    o.append(rect(18, 300, w - 70, 7, '#9AA1AC', rx=3.5, op=0.5))
    o.append(rect(18, 314, w - 110, 7, '#9AA1AC', rx=3.5, op=0.5))
    for i in range(3):
        yy = 348 + i * 40
        o.append(rect(18, yy, w - 36, 30, '#F6F7F9', rx=6))
        o.append(circle(38, yy + 15, 8, p['accent'], op=0.5))
        o.append(rect(56, yy + 11, 90, 7, '#1B2430', rx=3.5, op=0.45))
    o.append(rect(18, h - 78, w - 36, 42, p['ui_dark'], rx=10))
    o.append(rect(w / 2 - 40, h - 62, 80, 10, '#FFFFFF', rx=5, op=0.9))
    return o


def scr_hotel_phone(w, h, p):
    """The same system on a phone. These are Bootstrap builds; saying so with
       a device is more honest than a badge that claims it."""
    o = [rect(0, 0, w, h, '#FFFFFF', rx=27)]
    o.append(rect(0, 0, w, 66, p['ui_dark']))
    o.append(rect(18, 30, 84, 10, '#FFFFFF', rx=5, op=0.9))
    o.append(circle(w - 28, 38, 10, '#FFFFFF', op=0.22))
    o.append(rect(16, 82, w - 32, 96, p['accent'], rx=10, op=0.16))
    o.append(rect(28, 96, 70, 8, p['ui_dark'], rx=4, op=0.6))
    o.append(rect(28, 112, w - 88, 22, '#FFFFFF', rx=6))
    o.append(rect(28, 140, w - 88, 22, '#FFFFFF', rx=6))
    o.append(rect(w - 52, 112, 24, 50, p['accent'], rx=6))
    for i in range(3):
        yy = 196 + i * 86
        o.append(rect(16, yy, w - 32, 76, '#FFFFFF', rx=8, stroke='#E7EAEE', sw=1))
        o.append(rect(16, yy, 62, 76, p['accent'], rx=8, op=0.22))
        o.append(rect(90, yy + 14, w - 130, 8, '#1B2430', rx=4, op=0.6))
        o.append(rect(90, yy + 30, w - 170, 6, '#9AA1AC', rx=3, op=0.7))
        o.append(rect(90, yy + 48, 54, 16, p['accent'], rx=8))
    o.append(rect(16, h - 76, w - 32, 44, p['ui_dark'], rx=10))
    o.append(rect(w / 2 - 42, h - 60, 84, 11, '#FFFFFF', rx=5, op=0.9))
    return o


def scr_shop_phone(w, h, p):
    o = [rect(0, 0, w, h, '#FFFFFF', rx=27)]
    o.append(rect(0, 0, w, 62, p['ui_dark']))
    o.append(rect(18, 28, 60, 10, '#FFFFFF', rx=5, op=0.9))
    o.append(circle(w - 30, 34, 9, p['accent']))
    o.append(rect(16, 76, w - 32, 26, '#F1F3F6', rx=13))
    o.append(circle(34, 89, 5, '#9AA1AC'))
    o.append(rect(46, 85, 76, 7, '#9AA1AC', rx=3.5, op=0.6))
    for i in range(4):
        o.append(rect(16 + i * ((w - 32) / 4 + 2), 114, (w - 44) / 4, 20,
                      p['accent'] if i == 0 else '#F1F3F6', rx=10, op=1 if i == 0 else 1))
    for r in range(3):
        for c in range(2):
            bw = (w - 42) / 2
            bx, by = 16 + c * (bw + 10), 148 + r * 104
            o.append(rect(bx, by, bw, 94, '#FFFFFF', rx=8, stroke='#E7EAEE', sw=1))
            o.append(rect(bx, by, bw, 56, p['accent'], rx=8, op=0.18))
            o.append(rect(bx + 8, by + 64, bw - 28, 6, '#1B2430', rx=3, op=0.55))
            o.append(rect(bx + 8, by + 76, bw * 0.4, 7, p['accent'], rx=3.5))
    o.append(rect(0, h - 54, w, 54, '#FFFFFF'))
    o.append(line(0, h - 54, w, h - 54, '#E7EAEE', 1))
    for i in range(4):
        cx = w / 8 + i * w / 4
        o.append(rect(cx - 8, h - 38, 16, 16, p['accent'] if i == 0 else '#C7CDD4', rx=4))
    return o


def scr_admin(w, h, p):
    o, sw_ = ui_sidebar(w, h, p, items=7, active=3)
    o += ui_head(sw_, w - sw_, p, 'Users & roles', 'Add user')
    # Toolbar: search, filters, export. The furniture of a real data grid.
    o.append(rect(sw_ + 18, 48, (w - sw_ - 36) * 0.4, 22, '#F1F3F6', rx=11))
    o.append(circle(sw_ + 34, 59, 5, '#9AA1AC'))
    o.append(rect(sw_ + 46, 55, 60, 7, '#9AA1AC', rx=3.5, op=0.55))
    for i in range(3):
        o.append(rect(sw_ + 18 + (w - sw_ - 36) * 0.44 + i * 62, 48, 54, 22,
                      '#FFFFFF', rx=5, stroke='#E7EAEE', sw=1))
        o.append(rect(sw_ + 28 + (w - sw_ - 36) * 0.44 + i * 62, 55, 34, 7,
                      '#9AA1AC', rx=3.5, op=0.5))
    o.append(rect(sw_ + 18, 80, w - sw_ - 36, 22, '#F6F7F9', rx=4))
    for i, frac in enumerate((0.06, 0.3, 0.52, 0.74)):
        o.append(rect(sw_ + 28 + (w - sw_ - 36) * frac, 88, 34, 6, '#9AA1AC', rx=3, op=0.7))
    o += ui_rows(sw_ + 18, 108, w - sw_ - 36, p, n=6,
                 cols=(0.06, 0.24, 0.24, 0.2), pill_at=1)
    o.append(rect(sw_ + 18, 274, 120, 7, '#9AA1AC', rx=3.5, op=0.4))
    for i in range(4):
        o.append(rect(w - 120 + i * 26, 268, 20, 18, p['accent'] if i == 1 else '#FFFFFF',
                      rx=4, stroke='#E7EAEE', sw=1))
    return o


def scr_doc(w, h, p):
    """A page of the document itself, so a reader can see it is written."""
    o = [rect(0, 0, w, h, '#FFFFFF')]
    o.append(rect(0, 0, w, 8, p['accent']))
    o.append(rect(46, 44, w * 0.5, 15, '#1B2430', rx=7, op=0.82))
    o.append(rect(46, 68, w * 0.3, 8, p['accent'], rx=4))
    for block in range(4):
        by = 100 + block * 74
        o.append(rect(46, by, 14, 14, p['accent'], rx=3, op=0.85))
        o.append(rect(68, by + 3, w * 0.32, 8, '#1B2430', rx=4, op=0.6))
        for k in range(3):
            o.append(rect(68, by + 22 + k * 13, w * (0.5 - k * 0.06), 6, '#9AA1AC',
                          rx=3, op=0.45))
    return o


def doc_stack(x, y, w, p):
    """Three pages, fanned. What a checklist or a workbook actually is."""
    h = w * 1.32
    o = []
    for i, (dx, dy, rot, op) in enumerate(((44, 30, 6, 0.35), (22, 15, 3, 0.6), (0, 0, 0, 1))):
        o.append(f'<g transform="translate({x + dx:.0f} {y + dy:.0f}) '
                 f'rotate({rot} {w / 2:.0f} {h / 2:.0f})" opacity="{op}">')
        o.append(rect(9, 14, w, h, '#000000', rx=8, op=0.25))
        o.append(rect(0, 0, w, h, '#FFFFFF', rx=8))
        if i == 2:
            o += scr_doc(w, h, p)
            o.append(rect(0, 0, w, h, 'none', rx=8, stroke='#00000012', sw=1))
        o.append('</g>')
    return o


PRODUCTS = [
    {
        'slug': 'invetotrack-inventory-management-system',
        'brand': 'InvetoTrack',
        'kicker': 'INVENTORY MANAGEMENT SYSTEM',
        'headline': ['Stock, sales and reports —', 'and it works offline'],
        'sub': 'Laravel back office, REST API and a Flutter app that keeps selling when the shop loses signal.',
        'bullets': ['Laravel admin — stock, suppliers, sales, roles',
                    'REST API the mobile app actually consumes',
                    'Flutter app, offline-first, with a sync queue',
                    'Migrations, seeders and a Postman collection'],
        'stack': ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Flutter 3', 'REST'],
        'version': '2.1',
        'bg': ('#0B3B2E', '#04170F'), 'accent': '#F59E0B', 'on_accent': '#1A1206',
        'ui_dark': '#0B3B2E', 'texture': 'rings', 'mark': mk_box,
        'url': 'invetotrack.test/stock',
        'layout': 'desk+phone', 'screen': scr_inventory, 'phone': scr_hotel_phone,
    },
    {
        'slug': 'marketlink-multi-vendor-marketplace',
        'brand': 'MarketLink',
        'kicker': 'MULTI-VENDOR MARKETPLACE',
        'headline': ['Many sellers.', 'One platform.'],
        'sub': 'Vendor onboarding, split orders, commissions and payouts — with a Flutter shopping app on the front.',
        'bullets': ['Vendor approval, dashboards and payouts',
                    'One order across three sellers, split properly',
                    'Commission ledger with a running balance',
                    'Flutter customer app — browse, cart, checkout'],
        'stack': ['Laravel 11', 'Flutter 3', 'MySQL 8', 'Mobile Money', 'Visa'],
        'version': '1.4',
        'bg': ('#3B1A78', '#160831'), 'accent': '#22D3EE', 'on_accent': '#06202A',
        'ui_dark': '#3B1A78', 'texture': 'grid', 'mark': mk_store,
        'url': 'marketlink.test/vendors',
        'layout': 'desk+phone', 'screen': scr_market, 'phone': scr_firebase_a,
    },
    {
        'slug': 'hotelpro-booking-management-system',
        'brand': 'HotelPro',
        'kicker': 'HOTEL BOOKING SYSTEM',
        'headline': ['Rooms, rates and', 'real availability'],
        'sub': 'Plain PHP and MySQL, with no framework hiding the logic — so you can read every query.',
        'bullets': ['Date-range availability, not a flag on the room',
                    'Guest booking site and staff back office',
                    'Check-in, check-out and occupancy reports',
                    'A .sql dump with rooms, rates and bookings'],
        'stack': ['PHP 8', 'MySQL', 'Bootstrap 5', 'JavaScript'],
        'version': '1.2',
        'bg': ('#7C3F12', '#2A1406'), 'accent': '#2DD4BF', 'on_accent': '#04211D',
        'ui_dark': '#7C3F12', 'texture': 'arcs', 'mark': mk_bed,
        'url': 'hotelpro.test/availability',
        'layout': 'desk+phone', 'screen': scr_hotel, 'phone': scr_hotel_phone,
    },
    {
        'slug': 'online-shop-php-mysql',
        'brand': 'Online Shop',
        'kicker': 'PHP & MYSQL E-COMMERCE',
        'headline': ['Catalogue, cart', 'and real orders'],
        'sub': 'Built by hand, nothing hidden — including a cart that survives a closed browser.',
        'bullets': ['Accounts, password reset and order history',
                    'Categories, galleries and image zoom',
                    'Database-backed cart, not a session',
                    'Checkout, shipping and an admin side'],
        'stack': ['PHP 8', 'MySQL', 'Bootstrap 5', 'jQuery'],
        'version': '1.1',
        'bg': ('#8B1030', '#2C0510'), 'accent': '#FBBF24', 'on_accent': '#2B1A00',
        'ui_dark': '#8B1030', 'texture': 'bars', 'mark': mk_cart,
        'url': 'onlineshop.test/products',
        'layout': 'desk+phone', 'screen': scr_shop, 'phone': scr_shop_phone,
    },
    {
        'slug': 'android-ecommerce-firebase',
        'brand': 'Shop for Android',
        'kicker': 'FIREBASE E-COMMERCE APP',
        'headline': ['A complete shop with', 'no server at all'],
        'sub': 'Firestore holds the catalogue, carts and orders. Firebase Auth handles accounts. Nothing to rent.',
        'bullets': ['Catalogue, search, cart, checkout and orders',
                    'Email and Google sign-in, wired up',
                    'Cart syncs across devices for free',
                    'Firestore security rules that actually restrict'],
        'stack': ['Android', 'Java', 'Firebase Auth', 'Firestore'],
        'version': '1.0',
        'bg': ('#4C1D95', '#1A0938'), 'accent': '#FBBF24', 'on_accent': '#2B1A00',
        'ui_dark': '#4C1D95', 'texture': 'blobs', 'mark': mk_flame,
        'url': '', 'layout': 'phones', 'screen': None,
        'phone': scr_firebase_a, 'phone2': scr_firebase_b,
    },
    {
        'slug': 'systems-handover-checklist',
        'brand': 'Handover Checklist',
        'kicker': 'BEFORE YOU CALL IT DELIVERED',
        'headline': ['The checklist I run', 'before I walk away'],
        'sub': 'Everything a client team needs so the system survives without the person who built it.',
        'bullets': ['Access, credentials and who holds the keys',
                    'Backups — taken, tested and restorable',
                    'Documentation the next developer can follow',
                    'Training signed off by the people who use it'],
        'stack': ['PDF', '18 pages', 'Free'],
        'version': '2.0',
        'bg': ('#0F3B52', '#04161F'), 'accent': '#38BDF8', 'on_accent': '#04202B',
        'ui_dark': '#0F3B52', 'texture': 'arcs', 'mark': mk_panel,
        'url': '', 'layout': 'doc', 'screen': None, 'phone': None,
    },
    {
        'slug': 'database-design-workbook',
        'brand': 'Database Workbook',
        'kicker': 'DESIGN IT BEFORE YOU BUILD IT',
        'headline': ['Think in tables', 'before you write SQL'],
        'sub': 'Real schemas from real systems, worked through — with the mistakes shown, not hidden.',
        'bullets': ['Normalisation, worked through step by step',
                    'Schemas from a shop, a school and a clinic',
                    'The mistakes that cost you later, shown early',
                    'Exercises with answers at the back'],
        'stack': ['PDF', '64 pages', 'MySQL'],
        'version': '1.3',
        'bg': ('#4A2A6B', '#1A0E27'), 'accent': '#A3E635', 'on_accent': '#152003',
        'ui_dark': '#4A2A6B', 'texture': 'dots', 'mark': mk_panel,
        'url': '', 'layout': 'doc', 'screen': None, 'phone': None,
    },
    {
        'slug': 'laravel-admin-panel-starter',
        'brand': 'Admin Starter',
        'kicker': 'LARAVEL BACK-OFFICE SKELETON',
        'headline': ['The boring 30%,', 'done properly'],
        'sub': 'The project I start every Laravel build from — users, roles, grids, uploads and an audit trail.',
        'bullets': ['Roles and permissions with real policies',
                    'Data grids — search, filter, sort, export',
                    'Forms, validation, uploads and images',
                    'Activity log: who changed what, and when'],
        'stack': ['Laravel 11', 'PHP 8.2', 'MySQL 8', 'Blade', 'Alpine.js'],
        'version': '3.0',
        'bg': ('#1E293B', '#070C14'), 'accent': '#F43F5E', 'on_accent': '#2A0410',
        'ui_dark': '#1E293B', 'texture': 'dots', 'mark': mk_panel,
        'url': 'admin-starter.test/users',
        'layout': 'stack', 'screen': scr_admin, 'phone': None,
    },
]


def build(p):
    uid = p['slug'][:14]
    defs = [grad(uid + '-bg', p['bg'][0], p['bg'][1])]
    o = backdrop({**p}, uid)

    o += wordmark(p, 132)

    y = 312
    for i, line_text in enumerate(p['headline']):
        o.append(text(LEFT, y + i * 62, line_text, 53, '#FFFFFF', '800', ls=-1.3))
    y += len(p['headline']) * 62 + 8

    # The promise, then the proof.
    wrapped = _wrap(p['sub'], 50)
    for i, chunk in enumerate(wrapped):
        o.append(text(LEFT, y + i * 29, chunk, 18.5, '#FFFFFF', '400', op=0.74))
    y += len(wrapped) * 29 + 40

    o += bullets(p, y + 8)

    # What every item in this shop comes with, said once, above the stack.
    o.append(line(LEFT, H - 158, LEFT + 300, H - 158, '#FFFFFF', 1, op=0.18))
    o.append(text(LEFT, H - 128, 'FULL SOURCE  ·  INSTALL GUIDE  ·  LIFETIME RE-DOWNLOAD',
                  11.5, p['accent'], '800', ls=1.4, op=0.95))
    o += chips(p, H - 104)

    if p['layout'] == 'desk+phone':
        # The window is anchored high and the handset breaks its lower edge,
        # which is the arrangement every marketplace card uses because it
        # reads as depth rather than as two pictures side by side.
        o += browser(824, 168, 726, p['screen'], p)
        o += handset(1246, 448, 240, p['phone'], p)
    elif p['layout'] == 'stack':
        # A dimmed window behind a bright one: more screens than fit here.
        o.append('<g opacity="0.45">')
        o += browser(902, 132, 620, p['screen'], p)
        o.append('</g>')
        o += browser(806, 330, 700, p['screen'], p)
    elif p['layout'] == 'doc':
        o += doc_stack(958, 190, 480, p)
    else:  # phones
        o += handset(918, 186, 268, p['phone'], p)
        o += handset(1250, 314, 268, p['phone2'], p)

    o += ribbon(p)
    return frame(o, defs)


def _wrap(s, n):
    out, cur = [], ''
    for word in s.split():
        if len(cur) + len(word) + 1 > n:
            out.append(cur)
            cur = word
        else:
            cur = (cur + ' ' + word).strip()
    if cur:
        out.append(cur)
    return out


if __name__ == '__main__':
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       '..', 'public', 'images', 'products')
    os.makedirs(out, exist_ok=True)
    for p in PRODUCTS:
        svg = build(p)
        with open(os.path.join(out, f"{p['slug']}.svg"), 'w', encoding='utf-8') as f:
            f.write(svg)
        print(f"{p['slug']:44} {len(svg) / 1024:6.1f} KB   {p['bg'][0]} / {p['accent']}")
