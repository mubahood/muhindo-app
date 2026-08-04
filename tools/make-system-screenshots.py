#!/usr/bin/env python3
"""
Eight system screenshots, drawn rather than captured.

There are no publishable screengrabs of these systems and there never will be:
they hold a national livestock registry, patient records, wildlife enforcement
files and human-rights case documentation.

The first version drew all eight in the portfolio's own navy and gold, which
made them look like eight pages of one product rather than eight products
built years apart for different people. So each screen now has its own:

  * brand colour and accent, chosen for what the system is
  * shell — dark sidebar, light top nav, narrow icon rail, or a full dark app
  * device, where that is the truth of it: ULITS and Seed Trace are field
    systems, so a handset sits beside the desktop

What they share is the discipline, not the paint: real labels instead of grey
bars, three numbers big enough to read at 300px wide, and one diagram per
screen that carries the meaning on its own.

    python3 tools/make-system-screenshots.py
"""

import math
import os

W, H = 1600, 1000
FONT = "Helvetica Neue, Helvetica, Arial, sans-serif"
MONO = "SFMono-Regular, Menlo, Consolas, monospace"


class Theme:
    """One product's paint and shell."""

    def __init__(self, primary, primary_d, accent, on_accent='#FFFFFF',
                 bg='#F6F7F9', surface='#FFFFFF', ink='#111827', sub='#4B5563',
                 mute='#9CA3AF', line='#E5E7EB', shell='sidebar', dark=False,
                 ok='#15803D', ok_bg='#E4F0E7', warn='#B45309', warn_bg='#FBF0DC',
                 bad='#B91C1C', bad_bg='#F7E4E3'):
        self.primary = primary        # brand colour: chrome and headings
        self.primary_d = primary_d    # darker step, for the shell
        self.accent = accent          # where the eye should land
        self.on_accent = on_accent
        self.bg, self.surface = bg, surface
        self.ink, self.sub, self.mute, self.line = ink, sub, mute, line
        self.shell, self.dark = shell, dark
        self.ok, self.ok_bg = ok, ok_bg
        self.warn, self.warn_bg = warn, warn_bg
        self.bad, self.bad_bg = bad, bad_bg

    @property
    def top(self):
        return 44

    @property
    def side(self):
        return {'sidebar': 268, 'rail': 92, 'dark': 252, 'topnav': 0}[self.shell]

    @property
    def head(self):
        """Top of the content area — below the window bar, and below the top
           nav where there is one."""
        return self.top + (96 if self.shell == 'topnav' else 0)

    @property
    def mx(self):
        return self.side + 44

    @property
    def mr(self):
        return W - 44


THEMES = {
    # Agriculture ministry. Green for the sector, amber for what needs a look.
    'ulits': Theme(primary='#14532D', primary_d='#0B3A1E', accent='#D97706',
                   bg='#F7F6F0', ink='#15211A', sub='#4A5A50', mute='#93A29A',
                   line='#E2E4DB', shell='sidebar'),
    # A SaaS product sold to schools: light, friendly, navigation across the top.
    'school-dynamics': Theme(primary='#3730A3', primary_d='#2E2A86', accent='#0EA5E9',
                             bg='#F5F6FC', ink='#1B1B32', sub='#4E4E70', mute='#9A9AB5',
                             line='#E4E4F0', shell='topnav'),
    # Clinical software. Teal reads as care; rose is kept for what is urgent.
    'hospital-management': Theme(primary='#0F766E', primary_d='#115E59', accent='#E11D48',
                                 bg='#F3F8F7', ink='#10231F', sub='#456460', mute='#93AEAB',
                                 line='#DCE9E7', shell='rail'),
    # Enforcement, worked at night and in the field. A dark application.
    'wildlife-offenders': Theme(primary='#1B4332', primary_d='#0B1D16', accent='#F97316',
                                bg='#101B15', surface='#18291F', ink='#ECFDF5',
                                sub='#9CBDAC', mute='#6E8D7B', line='#24392D',
                                shell='dark', dark=True,
                                ok='#4ADE80', ok_bg='#16351F', warn='#FBBF24',
                                warn_bg='#382E12', bad='#F87171', bad_bg='#3A1D1D'),
    # Crop inspection. Olive and wheat, and a scanner in somebody's hand.
    'seed-tracking': Theme(primary='#3F6212', primary_d='#33520F', accent='#CA8A04',
                           bg='#F8F7EE', ink='#1C2410', sub='#4F5B3B', mute='#9AA383',
                           line='#E5E4D2', shell='sidebar', ok_bg='#E7EFD8'),
    # A public evidence platform. Violet, and deliberately high contrast.
    'pwd-observatory': Theme(primary='#5B21B6', primary_d='#4C1D95', accent='#0891B2',
                             bg='#F7F5FC', ink='#1A1030', sub='#4C4266', mute='#9990B3',
                             line='#E7E2F2', shell='topnav'),
    # Restricted case files. Slate and crimson, and nothing decorative.
    'human-rights-reporting': Theme(primary='#334155', primary_d='#0F172A', accent='#E11D48',
                                    bg='#0F172A', surface='#1B2537', ink='#E9EDF5',
                                    sub='#94A3B8', mute='#64748B', line='#2A3449',
                                    shell='dark', dark=True,
                                    ok='#4ADE80', ok_bg='#16351F', warn='#FBBF24',
                                    warn_bg='#382E12', bad='#FB7185', bad_bg='#3A1D24'),
    # Two storefronts on one back office. Commerce orange.
    'ecommerce-realestate': Theme(primary='#9A3412', primary_d='#7C2D12', accent='#EA580C',
                                  bg='#FAF7F4', ink='#231610', sub='#5B4A42', mute='#A79A93',
                                  line='#EDE4DE', shell='sidebar'),
}


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


def text(x, y, s, size=14, fill='#111827', weight='400', anchor='start', ls=0,
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


def path(d, fill='none', stroke=None, sw=1, cap='round', join='round', op=None, dash=None):
    a = f'<path d="{d}" fill="{fill}"'
    if stroke:
        a += f' stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}" stroke-linejoin="{join}"'
    if dash:
        a += f' stroke-dasharray="{dash}"'
    if op is not None:
        a += f' opacity="{op}"'
    return a + '/>'


def tw(s, size, weight='400'):
    """Roughly how wide a string sets — enough to size a pill around it."""
    narrow = sum(1 for c in s if c in 'iljtfrI.,:;()[]| ')
    wide = sum(1 for c in s if c.isupper() or c in 'mwMW@%')
    base = size * (0.56 if weight in ('600', '700') else 0.53)
    return len(s) * base + wide * size * 0.10 - narrow * size * 0.19


def pill(x, y, label, fg, bg, size=10.5, h=22, pad=11, anchor='start'):
    w = tw(label, size, '700') + pad * 2
    if anchor == 'end':
        x -= w
    return [rect(x, y, w, h, bg, rx=4),
            text(x + w / 2, y + h / 2 + size * 0.36, label, size, fg, '700', 'middle', 0.3)], w


def btn(x, y, label, t, primary=True, size=13, h=38):
    w = tw(label, size, '600') + 32
    if primary:
        return [rect(x, y, w, h, t.accent, rx=5),
                text(x + w / 2, y + h / 2 + 4.6, label, size, t.on_accent, '600', 'middle')], w
    return [rect(x, y, w, h, t.surface, rx=5, stroke=t.line, sw=1.4),
            text(x + w / 2, y + h / 2 + 4.6, label, size, t.sub, '600', 'middle')], w


def card(x, y, w, h, t, rx=8, fill=None):
    return rect(x, y, w, h, fill or t.surface, rx=rx, stroke=t.line, sw=1.4)


def sec(x, y, label, t, right=None, width=None):
    o = [text(x, y, label, 11, t.mute, '700', ls=1.2)]
    if right and width:
        o.append(text(x + width, y, right, 11, t.accent, '700', 'end', 1))
    return o


# ── icons ─────────────────────────────────────────────────────────────────

def ic_grid(x, y, c, o):
    return [rect(x - 8, y - 8, 7, 7, c, rx=1.5, op=o), rect(x + 1, y - 8, 7, 7, c, rx=1.5, op=o),
            rect(x - 8, y + 1, 7, 7, c, rx=1.5, op=o), rect(x + 1, y + 1, 7, 7, c, rx=1.5, op=o)]


def ic_list(x, y, c, o):
    return [rect(x - 8, y - 7, 16, 2.4, c, rx=1.2, op=o),
            rect(x - 8, y - 1, 16, 2.4, c, rx=1.2, op=o),
            rect(x - 8, y + 5, 11, 2.4, c, rx=1.2, op=o)]


def ic_map(x, y, c, o):
    return [path(f'M {x - 8} {y - 7} l 5.5 -2 l 5 3 l 5.5 -2 v 15 l -5.5 2 l -5 -3 l -5.5 2 z',
                 stroke=c, sw=1.9, op=o)]


def ic_pin(x, y, c, o):
    return [path(f'M {x} {y - 9} c -4 0 -6.5 3 -6.5 6 c 0 5 6.5 12 6.5 12 s 6.5 -7 6.5 -12 '
                 f'c 0 -3 -2.5 -6 -6.5 -6 z', stroke=c, sw=1.9, op=o)]


def ic_chart(x, y, c, o):
    return [rect(x - 8, y - 1, 4, 9, c, rx=1, op=o), rect(x - 2, y - 6, 4, 14, c, rx=1, op=o),
            rect(x + 4, y - 9, 4, 17, c, rx=1, op=o)]


def ic_cal(x, y, c, o):
    return [rect(x - 8, y - 6, 16, 14, 'none', rx=2, stroke=c, sw=1.8, op=o),
            line(x - 8, y - 1, x + 8, y - 1, c, 1.8, op=o),
            line(x - 4, y - 9, x - 4, y - 5, c, 1.8, cap='round', op=o),
            line(x + 4, y - 9, x + 4, y - 5, c, 1.8, cap='round', op=o)]


def ic_user(x, y, c, o):
    return [circle(x, y - 4, 3.6, 'none', stroke=c, sw=1.8, op=o),
            path(f'M {x - 7} {y + 8} a 7 7 0 0 1 14 0', stroke=c, sw=1.8, op=o)]


def ic_lock(x, y, c, o):
    return [rect(x - 7, y - 1, 14, 10, 'none', rx=2, stroke=c, sw=1.8, op=o),
            path(f'M {x - 4} {y - 1} v -4 a 4 4 0 0 1 8 0 v 4', stroke=c, sw=1.8, op=o)]


def ic_shield(x, y, c, o):
    return [path(f'M {x} {y - 9} l 7 3 v 5 c 0 5 -4 8 -7 9 c -3 -1 -7 -4 -7 -9 v -5 z',
                 stroke=c, sw=1.8, op=o)]


def ic_flask(x, y, c, o):
    return [path(f'M {x - 3} {y - 9} v 6 l -5 9 a 2 2 0 0 0 2 3 h 12 a 2 2 0 0 0 2 -3 l -5 -9 v -6',
                 stroke=c, sw=1.8, op=o),
            line(x - 4, y - 9, x + 4, y - 9, c, 1.8, cap='round', op=o)]


def ic_pillbox(x, y, c, o):
    return [rect(x - 9, y - 4, 18, 9, 'none', rx=4.5, stroke=c, sw=1.8, op=o),
            line(x, y - 4, x, y + 5, c, 1.8, op=o)]


def ic_card_i(x, y, c, o):
    return [rect(x - 9, y - 6, 18, 13, 'none', rx=2, stroke=c, sw=1.8, op=o),
            line(x - 9, y - 2, x + 9, y - 2, c, 1.8, op=o)]


def ic_box(x, y, c, o):
    return [path(f'M {x} {y - 9} l 8 4 v 9 l -8 4 l -8 -4 v -9 z', stroke=c, sw=1.8, op=o),
            path(f'M {x - 8} {y - 5} l 8 4 l 8 -4', stroke=c, sw=1.8, op=o)]


def ic_qr(x, y, c, o):
    return [rect(x - 8, y - 8, 6, 6, 'none', rx=1, stroke=c, sw=1.7, op=o),
            rect(x + 2, y - 8, 6, 6, 'none', rx=1, stroke=c, sw=1.7, op=o),
            rect(x - 8, y + 2, 6, 6, 'none', rx=1, stroke=c, sw=1.7, op=o),
            rect(x + 3, y + 3, 4, 4, c, op=o)]


def ic_leaf(x, y, c, o):
    return [path(f'M {x + 8} {y - 8} c -12 0 -16 6 -16 11 a 5 5 0 0 0 10 0 c 0 -5 6 -8 6 -11 z',
                 stroke=c, sw=1.8, op=o), line(x - 8, y + 8, x + 2, y - 2, c, 1.6, op=o)]


def ic_cow(x, y, c, o):
    return [path(f'M {x - 8} {y - 3} q 0 -6 8 -6 q 8 0 8 6 v 5 q 0 4 -8 4 q -8 0 -8 -4 z',
                 stroke=c, sw=1.8, op=o),
            line(x - 8, y - 4, x - 10, y - 8, c, 1.8, cap='round', op=o),
            line(x + 8, y - 4, x + 10, y - 8, c, 1.8, cap='round', op=o)]


def ic_book(x, y, c, o):
    return [path(f'M {x - 8} {y - 8} h 7 a 2 2 0 0 1 2 2 v 14 a 2 2 0 0 0 -2 -2 h -7 z',
                 stroke=c, sw=1.7, op=o),
            path(f'M {x + 8} {y - 8} h -7 a 2 2 0 0 0 -2 2 v 14 a 2 2 0 0 1 2 -2 h 7 z',
                 stroke=c, sw=1.7, op=o)]


def ic_bell(x, y, c, o):
    return [path(f'M {x - 6} {y + 4} v -6 a 6 6 0 0 1 12 0 v 6 z', stroke=c, sw=1.8, op=o),
            line(x - 8, y + 4, x + 8, y + 4, c, 1.8, cap='round', op=o),
            path(f'M {x - 2} {y + 7} a 2.5 2.5 0 0 0 4 0', stroke=c, sw=1.8, op=o)]


def ic_folder(x, y, c, o):
    return [path(f'M {x - 9} {y + 7} v -13 h 6 l 2 3 h 10 v 10 z', stroke=c, sw=1.8, op=o)]


def ic_scale(x, y, c, o):
    return [line(x, y - 8, x, y + 8, c, 1.8, cap='round', op=o),
            line(x - 8, y - 5, x + 8, y - 5, c, 1.8, cap='round', op=o),
            path(f'M {x - 8} {y - 5} l -3 6 h 6 z', stroke=c, sw=1.6, op=o),
            path(f'M {x + 8} {y - 5} l -3 6 h 6 z', stroke=c, sw=1.6, op=o)]


def ic_bag(x, y, c, o):
    return [path(f'M {x - 8} {y - 3} h 16 l -1.5 11 h -13 z', stroke=c, sw=1.8, op=o),
            path(f'M {x - 4} {y - 3} v -3 a 4 4 0 0 1 8 0 v 3', stroke=c, sw=1.8, op=o)]


def ic_home(x, y, c, o):
    return [path(f'M {x - 9} {y + 1} l 9 -8 l 9 8 v 8 h -18 z', stroke=c, sw=1.8, op=o)]


def ic_truck(x, y, c, o):
    return [rect(x - 9, y - 5, 11, 9, 'none', rx=1.5, stroke=c, sw=1.7, op=o),
            path(f'M {x + 2} {y - 2} h 4 l 3 4 v 2 h -7 z', stroke=c, sw=1.7, op=o),
            circle(x - 5, y + 6, 2, 'none', stroke=c, sw=1.6, op=o),
            circle(x + 5, y + 6, 2, 'none', stroke=c, sw=1.6, op=o)]


def ic_search(x, y, c, o):
    return [circle(x - 1, y - 2, 6, 'none', stroke=c, sw=1.9, op=o),
            line(x + 3.5, y + 2.5, x + 8, y + 7, c, 2.1, cap='round', op=o)]


def ic_cross(x, y, c, o):
    return [path(f'M {x} {y - 9} v 18 M {x - 9} {y} h 18', stroke=c, sw=2.6, op=o, cap='round')]


# ── shells ────────────────────────────────────────────────────────────────

def window_bar(t, title):
    """A window title, never a URL. Four of these are internal systems with no
       public address; inventing one would be a claim, not a picture."""
    o = [rect(0, 0, W, t.top, t.primary_d)]
    for i, op in enumerate((0.28, 0.38, 0.48)):
        o.append(circle(24 + i * 20, t.top / 2, 4.5, '#FFFFFF', op=op))
    o.append(text(W / 2, t.top / 2 + 4, title, 12, '#FFFFFF', '500', 'middle', 0.3, op=0.62))

    # A search field and an alert count. Every system anybody actually uses
    # has both, and a chrome without them is the tell.
    o.append(rect(W - 292, 9, 168, 26, '#FFFFFF', rx=13, op=0.1))
    o += _ic_search_small(W - 276, 22, '#FFFFFF', 0.5)
    o.append(text(W - 262, 26, 'Search', 11, '#FFFFFF', '400', op=0.42))
    o.append(path(f'M {W - 100} {26} v -5 a 6 6 0 0 1 12 0 v 5 z', stroke='#FFFFFF', sw=1.6, op=0.55))
    o.append(line(W - 103, 26, W - 85, 26, '#FFFFFF', 1.6, cap='round', op=0.55))
    o.append(circle(W - 88, 15, 5, '#EF4444'))
    o.append(text(W - 88, 18.5, '3', 8, '#FFFFFF', '700', 'middle'))
    o.append(circle(W - 62, t.top / 2, 4, '#FFFFFF', op=0.35))
    o.append(circle(W - 52, t.top / 2, 4, '#FFFFFF', op=0.35))
    o.append(circle(W - 42, t.top / 2, 4, '#FFFFFF', op=0.35))
    return o


def _ic_search_small(x, y, c, o):
    return [circle(x, y - 1, 4.4, 'none', stroke=c, sw=1.5, op=o),
            line(x + 3.2, y + 2.2, x + 6.4, y + 5.4, c, 1.7, cap='round', op=o)]


def sidebar(t, brand, org, nav, active, mark, user):
    o = [rect(0, t.top, t.side, H - t.top, t.primary_d)]
    o.append(rect(24, t.top + 26, 34, 34, t.accent, rx=8))
    o += mark(41, t.top + 43)
    o.append(text(68, t.top + 42, brand, 15, '#FFFFFF', '700'))
    o.append(text(68, t.top + 58, org, 10.5, '#FFFFFF', '500', ls=0.5, op=0.5))
    o.append(line(24, t.top + 84, t.side - 24, t.top + 84, '#FFFFFF', 1, op=0.14))

    y = t.top + 116
    for i, (label, icon) in enumerate(nav):
        on = i == active
        if on:
            o.append(rect(14, y - 13, t.side - 28, 40, t.accent, rx=6, op=0.18))
            o.append(rect(0, y - 13, 3, 40, t.accent))
        o += icon(32, y + 6, t.accent if on else '#FFFFFF', 1 if on else 0.45)
        o.append(text(58, y + 10, label, 13, '#FFFFFF', '600' if on else '400',
                      op=0.98 if on else 0.55))
        y += 48

    o.append(line(24, H - 80, t.side - 24, H - 80, '#FFFFFF', 1, op=0.14))
    o.append(circle(40, H - 48, 14, t.accent))
    o.append(text(40, H - 43.5, user[2], 10.5, t.on_accent, '700', 'middle'))
    o.append(text(64, H - 52, user[0], 12.5, '#FFFFFF', '600', op=0.9))
    o.append(text(64, H - 37, user[1], 10.5, '#FFFFFF', '400', op=0.45))
    return o


def rail(t, nav, active, mark, user):
    o = [rect(0, t.top, t.side, H - t.top, t.primary_d)]
    o.append(rect(t.side / 2 - 17, t.top + 26, 34, 34, t.accent, rx=8))
    o += mark(t.side / 2, t.top + 43)
    y = t.top + 116
    for i, (label, icon) in enumerate(nav):
        on = i == active
        if on:
            o.append(rect(10, y - 22, t.side - 20, 58, t.accent, rx=8, op=0.2))
        o += icon(t.side / 2, y, t.accent if on else '#FFFFFF', 1 if on else 0.5)
        o.append(text(t.side / 2, y + 23, label, 8.5, '#FFFFFF',
                      '700' if on else '500', 'middle', 0.4, op=0.95 if on else 0.45))
        y += 78
    o.append(circle(t.side / 2, H - 48, 15, t.accent))
    o.append(text(t.side / 2, H - 43.5, user[2], 10.5, t.on_accent, '700', 'middle'))
    return o


def topnav(t, brand, org, nav, active, mark, user):
    o = [rect(0, t.top, W, 96, t.primary)]
    o.append(rect(44, t.top + 30, 34, 34, t.accent, rx=8))
    o += mark(61, t.top + 47)
    o.append(text(88, t.top + 44, brand, 16, '#FFFFFF', '700'))
    o.append(text(88, t.top + 61, org, 10.5, '#FFFFFF', '500', ls=0.5, op=0.5))

    x = 330
    for i, (label, _) in enumerate(nav):
        on = i == active
        w = tw(label, 13.5, '600') + 36
        o.append(text(x + w / 2, t.top + 52, label, 13.5, '#FFFFFF',
                      '700' if on else '400', 'middle', op=1 if on else 0.6))
        if on:
            o.append(rect(x + 16, t.top + 72, w - 32, 3, t.accent, rx=2))
        x += w

    o.append(circle(W - 66, t.top + 48, 15, '#FFFFFF', op=0.2))
    o.append(text(W - 66, t.top + 52.5, user[2], 10.5, '#FFFFFF', '700', 'middle'))
    o.append(text(W - 92, t.top + 52.5, user[0], 12.5, '#FFFFFF', '500', 'end', op=0.78))
    return o


def shell(t, window, brand, org, nav, active, mark, user, title, subtitle, kpis, actions):
    o = [rect(0, 0, W, H, t.bg)]
    o += window_bar(t, window)

    if t.shell == 'topnav':
        o += topnav(t, brand, org, nav, active, mark, user)
    elif t.shell == 'rail':
        o += rail(t, nav, active, mark, user)
    else:
        o += sidebar(t, brand, org, nav, active, mark, user)

    hy = t.head
    o.append(text(t.mx, hy + 52, title, 30, t.ink, '700'))
    o.append(text(t.mx, hy + 79, subtitle, 13.5, t.sub, '400'))

    bx = t.mr
    for label, primary in reversed(actions):
        w = tw(label, 13, '600') + 32
        parts, _ = btn(bx - w, hy + 34, label, t, primary)
        o += parts
        bx -= w + 12

    cw = (t.mr - t.mx - 44) / 3
    for i, (label, value, note) in enumerate(kpis):
        x = t.mx + i * (cw + 22)
        o.append(card(x, hy + 112, cw, 108, t))
        o.append(text(x + 20, hy + 141, label, 10.5, t.mute, '700', ls=1.1))
        o.append(text(x + 20, hy + 182, value, 30, t.accent if i == 2 else t.ink, '700'))
        o.append(text(x + 20, hy + 203, note, 11, t.mute, '400'))
    return o


def frame(body):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000" '
            'width="1600" height="1000" role="img">\n  '
            + '\n  '.join(body) + '\n</svg>\n')


# ── shared components ─────────────────────────────────────────────────────

def table(x, y, w, cols, rows, t, rh=44, head=True):
    o = [card(x, y, w, (len(rows) + (1 if head else 0)) * rh + 10, t)]
    cy = y + 5
    if head:
        o.append(rect(x + 1.4, cy, w - 2.8, rh, t.bg))
        o.append(line(x, cy + rh, x + w, cy + rh, t.line, 1.4))
        cx = x + 20
        for label, cwid in cols:
            o.append(text(cx, cy + rh / 2 + 4, label, 10.5, t.mute, '700', ls=1))
            cx += cwid
        cy += rh
    for r, row in enumerate(rows):
        if r:
            o.append(line(x + 14, cy, x + w - 14, cy, t.line, 1))
        cx = x + 20
        for (label, cwid), cell in zip(cols, row):
            kind, val = cell
            base = cy + rh / 2 + 4.5
            if kind == 't':
                o.append(text(cx, base, val, 12.5, t.ink, '600'))
            elif kind == 'm':
                o.append(text(cx, base, val, 12, t.sub, '400'))
            elif kind == 'n':
                o.append(text(cx, base, val, 12, t.ink, '600', mono=True))
            elif kind == 'a':
                o.append(text(cx, base, val, 12.5, t.accent, '700'))
            elif kind == 'p':
                lab, fg, bg = val
                o += pill(cx, cy + rh / 2 - 11, lab, fg, bg)[0]
            cx += cwid
        cy += rh
    return o


def bars(x, y, w, h, values, labels, t, hi=None, axis=False):
    o = []
    n = len(values)
    top = max(values)
    ax = 26 if axis else 0

    # Bars floating on nothing read as decoration. A gridline and a number
    # turn the same shapes into a chart.
    if axis:
        for i in range(3):
            gy = y + h - i * (h / 2)
            o.append(line(x + ax, gy, x + w, gy, t.line, 1))
            o.append(text(x + ax - 6, gy + 3.5, str(int(top / 2 * i)), 9, t.mute, '500', 'end'))

    gap = max(8, (w - ax) / n * 0.3)
    bw = (w - ax - gap * (n - 1)) / n
    base = t.sub if t.dark else t.primary
    for i, v in enumerate(values):
        bh = max(3, v / top * h)
        bx = x + ax + i * (bw + gap)
        o.append(rect(bx, y + h - bh, bw, bh, t.accent if i == hi else base,
                      rx=3, op=1 if i == hi else (0.55 if t.dark else 0.82)))
        if axis:
            o.append(text(bx + bw / 2, y + h - bh - 7, str(v), 9.5,
                          t.accent if i == hi else t.mute, '700', 'middle'))
        o.append(text(bx + bw / 2, y + h + 19, labels[i], 10.5, t.mute, '600', 'middle'))
    return o


def spark(x, y, w, h, values, t, col=None, fill=True):
    col = col or t.accent
    lo, hi = min(values), max(values)
    span = (hi - lo) or 1
    pts = [(x + i * w / (len(values) - 1), y + h - (v - lo) / span * h)
           for i, v in enumerate(values)]
    d = f'M {pts[0][0]:.1f} {pts[0][1]:.1f} ' + ' '.join(f'L {a:.1f} {b:.1f}' for a, b in pts[1:])
    o = []
    if fill:
        o.append(path(d + f' L {x + w:.1f} {y + h:.1f} L {x:.1f} {y + h:.1f} Z', fill=col, op=0.12))
    o.append(path(d, stroke=col, sw=3))
    o.append(circle(pts[-1][0], pts[-1][1], 5, col))
    o.append(circle(pts[-1][0], pts[-1][1], 10, col, op=0.22))
    return o


def donut(cx, cy, r, parts, thickness=24):
    o = []
    a0 = -90
    for frac, col in parts:
        a1 = a0 + frac * 360
        large = 1 if (a1 - a0) > 180 else 0
        x0, y0 = cx + r * math.cos(math.radians(a0)), cy + r * math.sin(math.radians(a0))
        x1, y1 = cx + r * math.cos(math.radians(a1)), cy + r * math.sin(math.radians(a1))
        o.append(path(f'M {x0:.1f} {y0:.1f} A {r} {r} 0 {large} 1 {x1:.1f} {y1:.1f}',
                      stroke=col, sw=thickness, cap='butt'))
        a0 = a1
    return o


def handset(x, y, w, t, screen):
    """A phone beside the desktop, for the systems that live in a field bag."""
    h = w * 2.05
    o = [rect(x - 8, y - 8, w + 16, h + 16, '#1B1B1B', rx=32),
         rect(x - 8, y - 8, w + 16, h + 16, 'none', rx=32, stroke='#000000', sw=1.4, op=0.3),
         rect(x, y, w, h, t.bg, rx=25)]
    o += screen(x, y, w, h)
    o.append(rect(x + w / 2 - 26, y + 8, 52, 9, '#1B1B1B', rx=5))
    return o


def qr_block(x, y, size, t, seed=19, ink=None):
    ink = ink or t.primary
    n = 21
    o = [rect(x, y, size, size, '#FFFFFF', rx=6, stroke=t.line, sw=1.4)]
    pad = size * 0.07
    inner = size - pad * 2
    c = inner / n

    def finder(fx, fy):
        s = c * 7
        return [rect(fx, fy, s, s, ink, rx=3),
                rect(fx + c, fy + c, s - 2 * c, s - 2 * c, '#FFFFFF', rx=2),
                rect(fx + c * 2, fy + c * 2, s - 4 * c, s - 4 * c, ink, rx=1)]

    o += finder(x + pad, y + pad)
    o += finder(x + pad + inner - c * 7, y + pad)
    o += finder(x + pad, y + pad + inner - c * 7)
    v = seed
    for row in range(n):
        for col in range(n):
            skip = ((row < 8 and col < 8) or (row < 8 and col > n - 9)
                    or (row > n - 9 and col < 8))
            v = (v * 1103515245 + 12345) & 0x7FFFFFFF
            if skip or (v >> 16) % 100 < 52:
                continue
            o.append(rect(x + pad + col * c, y + pad + row * c, c * 0.92, c * 0.92, ink, rx=0.5))
    return o


# ── the map of Uganda ─────────────────────────────────────────────────────

# The border, traced closely enough to be recognised rather than guessed at:
# the West Nile shoulder, the spike to 4.2°N in Karamoja, the Elgon bulge, the
# point at Kisoro, and the long smooth western run past Albert and Edward.
UGANDA = [
    (30.83, 3.41), (31.15, 3.62), (31.55, 3.72), (31.95, 3.62), (32.25, 3.72),
    (32.60, 3.80), (32.95, 3.92), (33.25, 4.05), (33.60, 4.22), (33.95, 4.15),
    (34.15, 3.87), (34.35, 3.72), (34.55, 3.55), (34.72, 3.30), (34.85, 3.10),
    (34.98, 2.85), (34.90, 2.40), (34.78, 2.00), (34.65, 1.70), (34.50, 1.45),
    (34.35, 1.20), (34.20, 1.10), (34.08, 0.95), (33.98, 0.78), (34.02, 0.60),
    (34.12, 0.42), (34.05, 0.22), (33.98, 0.02), (33.93, -0.20), (33.90, -0.42),
    (33.60, -0.55), (33.20, -0.72), (32.85, -0.95), (32.40, -1.00), (31.90, -1.02),
    (31.40, -1.02), (30.90, -1.05), (30.55, -1.07), (30.35, -1.06), (30.10, -1.22),
    (29.93, -1.47), (29.78, -1.38), (29.68, -1.20), (29.62, -0.95), (29.58, -0.72),
    (29.65, -0.50), (29.72, -0.28), (29.78, -0.05), (29.72, 0.18), (29.75, 0.42),
    (29.88, 0.65), (30.02, 0.85), (30.20, 1.05), (30.38, 1.22), (30.55, 1.38),
    (30.75, 1.58), (30.95, 1.78), (31.10, 1.98), (31.25, 2.18), (31.30, 2.42),
    (31.22, 2.65), (31.15, 2.88), (31.05, 3.08), (30.95, 3.25),
]

# Uganda's water is most of what makes its outline legible.
VICTORIA = [
    (31.90, -1.02), (32.40, -1.00), (32.85, -0.95), (33.20, -0.72), (33.60, -0.55),
    (33.90, -0.42), (33.85, -0.10), (33.70, 0.12), (33.45, 0.28), (33.15, 0.38),
    (32.80, 0.40), (32.50, 0.32), (32.25, 0.12), (32.05, -0.15), (31.92, -0.45),
    (31.88, -0.75),
]
ALBERT = [
    (30.48, 1.28), (30.72, 1.55), (31.00, 1.85), (31.22, 2.10), (31.30, 2.22),
    (31.16, 2.20), (30.92, 1.94), (30.62, 1.60), (30.38, 1.34),
]
KYOGA = [
    (32.15, 1.05), (32.45, 1.02), (32.72, 1.12), (32.95, 1.30), (33.20, 1.42),
    (33.48, 1.48), (33.62, 1.58), (33.70, 1.72), (33.45, 1.78), (33.15, 1.66),
    (32.90, 1.55), (32.65, 1.48), (32.48, 1.55), (32.30, 1.68), (32.12, 1.60),
    (32.02, 1.42), (32.05, 1.20),
]
EDWARD = [
    (29.62, -0.62), (29.80, -0.55), (29.92, -0.35), (29.88, -0.15), (29.72, -0.12),
    (29.60, -0.30),
]
LAKES = (VICTORIA, ALBERT, KYOGA, EDWARD)


def project(lon, lat, box):
    x0, y0, bw, bh = box
    lon0, lon1, lat0, lat1 = 29.42, 35.14, -1.66, 4.36
    s = min(bw / (lon1 - lon0), bh / (lat1 - lat0))
    ox = x0 + (bw - (lon1 - lon0) * s) / 2
    oy = y0 + (bh - (lat1 - lat0) * s) / 2
    return ox + (lon - lon0) * s, oy + (lat1 - lat) * s


def poly(points, box):
    pts = [project(a, b, box) for a, b in points]
    return (f'M {pts[0][0]:.1f} {pts[0][1]:.1f} '
            + ' '.join(f'L {a:.1f} {b:.1f}' for a, b in pts[1:]) + ' Z')


# Places, so the map has names on it. Real coordinates.
TOWNS = [
    ('Kampala', 32.58, 0.32, 1), ('Mbarara', 30.66, -0.61, 1), ('Gulu', 32.30, 2.77, 1),
    ('Soroti', 33.61, 1.71, 1), ('Mbale', 34.18, 1.08, 1), ('Arua', 30.91, 3.02, 1),
    ('Fort Portal', 30.27, 0.65, 0), ('Masaka', 31.73, -0.34, 0), ('Jinja', 33.20, 0.44, 0),
    ('Lira', 32.90, 2.25, 0), ('Hoima', 31.35, 1.43, 0), ('Kabale', 29.99, -1.25, 0),
    ('Moroto', 34.66, 2.53, 0), ('Kasese', 30.09, 0.18, 0),
]

# The trunk roads, by the towns they run through.
ROADS = [
    ['Kampala', 'Masaka', 'Mbarara', 'Kabale'],
    ['Kampala', 'Jinja', 'Mbale', 'Soroti'],
    ['Kampala', 'Hoima', 'Gulu', 'Arua'],
    ['Gulu', 'Lira', 'Soroti'],
    ['Mbarara', 'Kasese', 'Fort Portal', 'Hoima'],
    ['Soroti', 'Moroto'],
]

_TOWN = {name: (lon, lat) for name, lon, lat, _ in TOWNS}


def _lcg(seed):
    """Deterministic noise. The picture must not change between runs."""
    v = seed
    while True:
        v = (v * 1103515245 + 12345) & 0x7FFFFFFF
        yield (v >> 8) / 0x7FFFFF


def district_lines(seed=41):
    """
    Boundaries, as lon/lat polylines.

    Uganda has 146 districts and drawing them from a real shapefile is not
    something a 700px panel would show anyway. What a map at this zoom shows
    is *that* the country is subdivided — so these are irregular lines across
    the whole bounding box, clipped to the border. Deterministic, so the
    picture is the same every run.
    """
    rnd = _lcg(seed)
    lines = []

    # Roughly north-south, then roughly east-west, so they cross into cells.
    for i in range(7):
        lon = 29.7 + i * 0.75 + next(rnd) * 0.3
        pts = []
        for j in range(9):
            lat = -1.7 + j * 0.75
            pts.append((lon + (next(rnd) - 0.5) * 0.55, lat))
        lines.append(pts)

    for i in range(6):
        lat = -1.3 + i * 0.95 + next(rnd) * 0.3
        pts = []
        for j in range(10):
            lon = 29.4 + j * 0.65
            pts.append((lon, lat + (next(rnd) - 0.5) * 0.5))
        lines.append(pts)

    return lines


def uganda(box, t, pins=(), route=(), land=None, water='#AFC7D6', labels=(), lakes=True,
           border=None, chrome=True, uid='map', towns=True, roads=True, dense=True,
           inset=None):
    """
    A map panel, not a silhouette.

    The first version drew the country as a flat shape floating in white,
    which is a logo. What makes a map read as a map is everything around the
    outline: subdivision, roads, place names, and the controls that say it can
    be panned and zoomed. Those are what this adds.
    """
    x0, y0, bw, bh = box
    # Everything on the old map was one value of beige, which is why it read
    # as a shape rather than a surface. Land, water, roads and boundaries now
    # sit at genuinely different values.
    land = land or '#EFEADC'
    ink = border or t.primary
    o = []

    # Everything inside the country is drawn once and clipped, so the roads
    # and boundaries stop at the border without any per-line geometry.
    o.append(f'<clipPath id="{uid}-clip"><path d="{poly(UGANDA, box)}"/></clipPath>')

    # Outside the border is water and neighbours, not the page.
    o.append(rect(x0, y0, bw, bh, water, op=0.55))
    o.append(f'<g clip-path="url(#{uid}-clip)">')
    o.append(rect(x0, y0, bw, bh, land))

    if dense:
        for pts in district_lines():
            proj = [project(a, b, box) for a, b in pts]
            d = f'M {proj[0][0]:.1f} {proj[0][1]:.1f} ' + ' '.join(
                f'L {a:.1f} {b:.1f}' for a, b in proj[1:])
            o.append(path(d, stroke=ink, sw=1, op=0.3))

    if lakes:
        for shape in LAKES:
            o.append(path(poly(shape, box), fill=water))
            o.append(path(poly(shape, box), fill='none', stroke='#5E8098', sw=1, op=0.5))

    if roads:
        for route_names in ROADS:
            proj = [project(*_TOWN[n], box) for n in route_names]
            d = f'M {proj[0][0]:.1f} {proj[0][1]:.1f} ' + ' '.join(
                f'L {a:.1f} {b:.1f}' for a, b in proj[1:])
            # White casing under a warm core, which is how every road on
            # every map is drawn and why they read at a glance.
            o.append(path(d, stroke='#FFFFFF', sw=5, op=0.9))
            o.append(path(d, stroke='#B07C2A', sw=2.2, op=0.85))

    o.append('</g>')

    # The border itself, over the top of everything it contains.
    o.append(path(poly(UGANDA, box), fill='none', stroke=ink, sw=2.2, op=0.85))

    for lon, lat, name in labels:
        x, y = project(lon, lat, box)
        o.append(text(x, y, name, 9, '#3D5A6C', '700', 'middle', 0.9, op=0.9))

    if route:
        proj = [project(a, b, box) for a, b in route]
        d = f'M {proj[0][0]:.1f} {proj[0][1]:.1f} ' + ' '.join(
            f'L {a:.1f} {b:.1f}' for a, b in proj[1:])
        o.append(path(d, stroke='#FFFFFF', sw=6, op=0.85))
        o.append(path(d, stroke=t.accent, sw=3, dash='10 7'))

    for lon, lat, big in pins:
        x, y = project(lon, lat, box)
        if big:
            o.append(circle(x, y, 15, t.accent, op=0.3))
            o.append(path(f'M {x:.1f} {y - 20:.1f} c -7 0 -12 5 -12 11 c 0 8 12 20 12 20 '
                          f's 12 -12 12 -20 c 0 -6 -5 -11 -12 -11 z', fill=t.accent))
            o.append(circle(x, y - 9, 4.2, '#FFFFFF'))
        else:
            o.append(circle(x, y, 5, '#FFFFFF', stroke=t.accent, sw=2.4))

    if towns:
        marked = [project(a, b, box) for a, b, big in pins if big]
        small = [project(a, b, box) for a, b, big in pins if not big]
        for name, lon, lat, major in TOWNS:
            x, y = project(lon, lat, box)
            # A pin already names the place it is standing on.
            if any(abs(x - mx) < 22 and abs(y - my) < 26 for mx, my in marked):
                continue
            pinned = any(abs(x - px) < 3 and abs(y - py) < 3 for px, py in small)
            if major:
                if not pinned:
                    o.append(circle(x, y, 3.6, '#FFFFFF', stroke=ink, sw=1.6))
                o.append(text(x + (13 if pinned else 7), y + 3.4, name, 10, ink, '700', op=0.95))
            else:
                if not pinned:
                    o.append(circle(x, y, 2, ink, op=0.5))
                o.append(text(x + (13 if pinned else 5.5), y + 2.8, name, 8.5, ink, '600', op=0.7))

    if inset:
        # The country is taller than it is wide, so fitting it to a landscape
        # panel leaves a column of water on either side. A real map UI puts
        # something in that column — here, the consignment the screen is
        # about, with where it has actually been.
        ix, iy, iw = x0 + 14, y0 + 96, 214
        rows = inset['stops']
        ih = 74 + len(rows) * 40
        o.append(rect(ix + 2, iy + 3, iw, ih, '#000000', rx=6, op=0.13))
        o.append(rect(ix, iy, iw, ih, '#FFFFFF', rx=6, stroke='#00000018', sw=1))
        o.append(text(ix + 14, iy + 24, inset['title'], 10, '#6B7280', '700', ls=1.1))
        o.append(text(ix + 14, iy + 45, inset['subject'], 14.5, t.ink, '700', mono=True))
        o.append(line(ix + 14, iy + 58, ix + iw - 14, iy + 58, '#00000012', 1))

        for i, (when, place, state) in enumerate(rows):
            ry = iy + 82 + i * 40
            done = state != 'next'
            o.append(line(ix + 24, ry - 12, ix + 24, ry + 28, '#00000015', 1.6)
                     if i < len(rows) - 1 else '')
            if state == 'now':
                o.append(circle(ix + 24, ry, 9, t.accent, op=0.25))
                o.append(circle(ix + 24, ry, 5, t.accent))
            elif done:
                o.append(circle(ix + 24, ry, 5, t.primary))
                o.append(path(f'M {ix + 21} {ry} l 2.4 2.4 l 4.4 -5', stroke='#FFFFFF', sw=1.8))
            else:
                o.append(circle(ix + 24, ry, 5, '#FFFFFF', stroke='#00000025', sw=2))
            o.append(text(ix + 40, ry - 1, place, 12, t.ink if done else '#9CA3AF', '600'))
            o.append(text(ix + iw - 14, ry - 1, when, 10.5, '#9CA3AF', '600', 'end', mono=True))

    if chrome:
        # Zoom, scale and a coordinate readout. A map you cannot move is a
        # picture of a map, and the controls are what say otherwise.
        zx, zy = x0 + bw - 34, y0 + 12
        o.append(rect(zx + 1, zy + 2, 26, 52, '#000000', rx=4, op=0.12))
        o.append(rect(zx, zy, 26, 52, '#FFFFFF', rx=4, stroke='#00000022', sw=1))
        o.append(line(zx + 5, zy + 26, zx + 21, zy + 26, '#00000018', 1))
        o.append(text(zx + 13, zy + 18, '+', 15, ink, '600', 'middle'))
        o.append(text(zx + 13, zy + 44, '\u2212', 15, ink, '600', 'middle'))

        o.append(rect(x0, y0 + bh - 24, bw, 24, '#FFFFFF', op=0.82))
        sx, sy = x0 + 14, y0 + bh - 9
        o.append(line(sx, sy, sx + 58, sy, ink, 2, op=0.7))
        o.append(line(sx, sy - 4, sx, sy, ink, 2, op=0.7))
        o.append(line(sx + 58, sy - 4, sx + 58, sy, ink, 2, op=0.7))
        o.append(text(sx + 64, sy + 3, '100 km', 9, ink, '600', op=0.6))
        o.append(text(x0 + bw - 12, sy + 3, '0.3476\u00b0 N, 32.5825\u00b0 E',
                      8.5, ink, '500', 'end', op=0.45, mono=True))

    return o


# ══════════════════════════════════════════════════════════════════════════
# The eight screens
# ══════════════════════════════════════════════════════════════════════════

def ulits(t):
    o = shell(
        t, window='ULITS — Uganda Livestock Information Tracking System',
        brand='ULITS', org='MAAIF · Livestock',
        nav=[('Dashboard', ic_grid), ('Animal registry', ic_cow), ('Movement permits', ic_map),
             ('Vaccination', ic_flask), ('Disease alerts', ic_bell), ('Districts', ic_pin)],
        active=2, mark=lambda x, y: ic_cow(x, y, '#FFFFFF', 1),
        user=('D. Kirunda', 'District officer', 'DK'),
        title='Movement permit UG-MP-11284',
        subtitle='Mbarara → Soroti · 5 animals · issued 4 minutes ago, valid 72 hours',
        kpis=[('ANIMALS ON THE REGISTER', '48,120', 'ear-tagged nationally'),
              ('PERMITS THIS QUARTER', '3,402', '112 awaiting inspection'),
              ('DISTRICTS REPORTING', '146 / 146', 'every district in the country')],
        actions=[('Export CSV', False), ('Issue permit', True)])

    hy = t.head
    mw = 700
    my, mh = hy + 246, 620
    o.append(card(t.mx, my, mw, mh, t))

    # The map fills its panel. Floating a country in the middle of a white
    # card is a logo; a map runs to the edges of the thing that holds it.
    o.append(rect(t.mx + 1.4, my + 1.4, mw - 2.8, 40, t.bg))
    o.append(line(t.mx, my + 41, t.mx + mw, my + 41, t.line, 1.4))
    o.append(text(t.mx + 16, my + 26, 'ROUTE AND CHECKPOINTS', 10.5, t.mute, '700', ls=1.1))
    o.append(circle(t.mx + mw - 84, my + 21, 3.4, t.accent))
    o.append(text(t.mx + mw - 16, my + 25, 'LIVE GPS', 10.5, t.accent, '700', 'end', 1))

    # Layer chips, over the map rather than beside it, the way every map UI
    # puts them.
    o += uganda((t.mx + 1.4, my + 42, mw - 2.8, mh - 84), t, uid='ulits',
                pins=[(30.66, -0.61, False), (31.35, 1.43, False), (32.58, 0.32, True),
                      (33.61, 1.71, False), (34.18, 1.08, False), (30.91, 3.02, False)],
                route=[(30.66, -0.61), (31.73, -0.34), (32.58, 0.32), (33.20, 0.44),
                       (34.18, 1.08), (33.61, 1.71)],
                labels=[(32.90, -0.15, 'LAKE VICTORIA'), (32.85, 1.38, 'LAKE KYOGA')],
                inset={
                    'title': 'THIS CONSIGNMENT',
                    'subject': 'UG-MP-11284',
                    'stops': [
                        ('09:14', 'Mbarara — departed', 'done'),
                        ('10:52', 'Masaka checkpoint', 'done'),
                        ('12:07', 'Kampala checkpoint', 'now'),
                        ('—', 'Jinja checkpoint', 'next'),
                        ('—', 'Soroti — arrival', 'next'),
                    ],
                })

    cx = t.mx + 16
    for label, on in (('Districts', True), ('Roads', True), ('Holdings', True), ('Markets', False)):
        w = tw(label, 10, '600') + 22
        o.append(rect(cx, my + 54, w, 24, '#FFFFFF' if on else '#FFFFFFCC', rx=4,
                      stroke=t.accent if on else '#00000022', sw=1.4 if on else 1))
        o.append(text(cx + w / 2, my + 70, label, 10, t.primary if on else t.mute,
                      '700' if on else '500', 'middle', 0.3))
        cx += w + 6

    # Legend, on the map's own footer strip.
    ly = my + mh - 42
    o.append(rect(t.mx + 1.4, ly, mw - 2.8, 40, t.surface))
    o.append(line(t.mx, ly, t.mx + mw, ly, t.line, 1.4))
    o.append(circle(t.mx + 22, ly + 20, 5, '#FFFFFF', stroke=t.accent, sw=2.4))
    o.append(text(t.mx + 34, ly + 24, 'Registered holding', 11, t.sub, '500'))
    o.append(line(t.mx + 156, ly + 20, t.mx + 184, ly + 20, t.accent, 3, dash='9 6'))
    o.append(text(t.mx + 192, ly + 24, 'Permitted route', 11, t.sub, '500'))
    o.append(text(t.mx + mw - 16, ly + 24, '6 checkpoints on this route', 11, t.mute, '500', 'end'))

    rx = t.mx + mw + 24
    rw = t.mr - rx - 236
    o += sec(rx, hy + 280, 'ANIMALS ON THIS PERMIT', t)
    o += table(rx, hy + 296, rw,
               [('EAR TAG', 152), ('', 0)],
               [[('n', 'UG-0442-118'), ('p', ('CLEARED', t.ok, t.ok_bg))],
                [('n', 'UG-0442-119'), ('p', ('CLEARED', t.ok, t.ok_bg))],
                [('n', 'UG-0873-004'), ('p', ('CLEARED', t.ok, t.ok_bg))],
                [('n', 'UG-1120-771'), ('p', ('QUEUED', t.warn, t.warn_bg))],
                [('n', 'UG-1120-772'), ('p', ('QUEUED', t.warn, t.warn_bg))]],
               t, rh=46)

    oy = hy + 590
    o.append(rect(rx, oy, rw, 132, t.primary_d, rx=8))
    o.append(path(f'M {rx + 26} {oy + 64} a 28 28 0 0 1 42 0', stroke=t.accent, sw=3.6))
    o.append(path(f'M {rx + 35} {oy + 52} a 17 17 0 0 1 24 0', stroke=t.accent, sw=3.6, op=0.55))
    o.append(circle(rx + 47, oy + 42, 3.4, t.accent, op=0.3))
    o.append(text(rx + 86, oy + 46, 'Working offline', 14, '#FFFFFF', '700'))
    o.append(text(rx + 86, oy + 66, '212 records held on this device.', 11.5, '#FFFFFF', '400', op=0.62))
    o.append(text(rx + 86, oy + 84, 'They upload themselves the moment', 11.5, '#FFFFFF', '400', op=0.62))
    o.append(text(rx + 86, oy + 102, 'a signal comes back.', 11.5, '#FFFFFF', '400', op=0.62))

    o += sec(rx, hy + 758, 'VACCINATION COVERAGE', t, 'MBARARA \u00b7 %', rw)
    o += bars(rx, hy + 778, rw, 66, [72, 88, 61, 94, 80],
              ['JAN', 'FEB', 'MAR', 'APR', 'MAY'], t, hi=3, axis=True)

    def field_app(px, py, pw, ph):
        s = [rect(px, py, pw, 78, t.primary, rx=0)]
        s.append(rect(px, py, pw, 25, t.primary, rx=25))
        s.append(text(px + 20, py + 42, 'Scan a tag', 15, '#FFFFFF', '700'))
        s.append(text(px + 20, py + 61, 'Mbarara · offline', 10.5, '#FFFFFF', '400', op=0.6))
        s.append(circle(px + pw - 30, py + 50, 12, '#FFFFFF', op=0.18))
        s.append(text(px + pw - 30, py + 54, 'DK', 9, '#FFFFFF', '700', 'middle'))

        s.append(rect(px + 14, py + 94, pw - 28, 148, '#FFFFFF', rx=10, stroke=t.line, sw=1.4))
        cx2, cy2 = px + pw / 2, py + 152
        # Bar, gap, bar, gap — with the widths a real symbology actually uses,
        # because an even picket fence is the tell on every fake barcode.
        pattern = [3, 1, 1, 2, 4, 1, 2, 1, 1, 3, 1, 4, 2, 1, 3, 1, 1, 2, 1, 5,
                   2, 1, 1, 3, 1, 2, 4, 1, 1, 2]
        bx2 = cx2 - 47
        for k, wdt in enumerate(pattern):
            if k % 2 == 0:
                s.append(rect(bx2, cy2 - 28, wdt * 1.6, 54, t.primary))
            bx2 += wdt * 1.6
        s.append(text(cx2, py + 208, 'UG-1120-772', 13, t.ink, '700', 'middle'))
        s.append(text(cx2, py + 226, 'read in 0.3 seconds', 10, t.mute, '400', 'middle'))

        for i, (k, v) in enumerate([('Breed', 'Friesian'), ('Owner', 'J. Tumusiime'),
                                    ('Vaccinated', '11 Mar')]):
            yy = py + 262 + i * 26
            s.append(text(px + 18, yy, k, 10.5, t.mute, '500'))
            s.append(text(px + pw - 18, yy, v, 11, t.ink, '600', 'end'))

        s.append(rect(px + 14, py + 330, pw - 28, 44, t.accent, rx=8))
        s.append(text(px + pw / 2, py + 358, 'Add to permit', 13.5, '#FFFFFF', '700', 'middle'))
        s.append(rect(px + 14, py + 380, pw - 28, 28, t.warn, rx=6, op=0.18))
        s.append(text(px + pw / 2, py + 399, '212 waiting to sync', 10.5, t.warn, '700', 'middle'))
        return s

    o += handset(t.mr - 210, hy + 268, 202, t, field_app)
    return o


def school(t):
    o = shell(
        t, window='schooldynamics.ug — School Management Information System',
        brand='School Dynamics', org='St. Kizito S.S. · Term II',
        nav=[('Overview', ic_grid), ('Students', ic_user), ('Timetable', ic_cal),
             ('Examinations', ic_book), ('Fees', ic_card_i), ('Library', ic_book)],
        active=2,
        mark=lambda x, y: [path(f'M {x - 11} {y + 2} l 11 -6 l 11 6 l -11 6 z',
                                stroke='#FFFFFF', sw=2.2),
                           path(f'M {x + 7} {y + 5} v 7', stroke='#FFFFFF', sw=2.2)],
        user=('A. Nabirye', 'Head teacher', 'AN'),
        title='Timetable & attendance',
        subtitle='S.3 East · week 7 of 13 · generated in 2 seconds, no room or teacher clashes',
        kpis=[('STUDENTS ENROLLED', '2,140', 'across 38 streams'),
              ('PRESENT TODAY', '94.2%', '124 absences, every one logged'),
              ('FEES COLLECTED', 'UGX 38.4M', '81% of the term target')],
        actions=[('Print', False), ('Publish to parents', True)])

    hy = t.head
    gw = 828
    o.append(card(t.mx, hy + 246, gw, 580, t))
    o += sec(t.mx + 22, hy + 280, 'S.3 EAST — WEEK 7', t, 'NO CLASHES', gw - 44)

    days = ['MON', 'TUE', 'WED', 'THU', 'FRI']
    gx, gy = t.mx + 94, hy + 296
    cw = (gw - 116) / 5
    rh = 80
    for i, d in enumerate(days):
        o.append(text(gx + i * cw + cw / 2, gy + 16, d, 10.5, t.mute, '700', 'middle', 1))
    periods = ['08:00', '09:20', '10:40', '12:00', '14:00']
    grid = [
        [('Mathematics', 'Mr Okello', 1), ('English', 'Ms Aber', 0), ('Physics lab', 'Mr Ojok', 2),
         ('History', 'Ms Nalwoga', 0), ('Mathematics', 'Mr Okello', 1)],
        [('Biology', 'Ms Kemigisa', 0), ('Chemistry lab', 'Mr Ssali', 2), ('English', 'Ms Aber', 0),
         ('Mathematics', 'Mr Okello', 1), ('Geography', 'Mr Wandera', 0)],
        [('Computer lab', 'Mr Mubaraka', 2), ('Mathematics', 'Mr Okello', 1),
         ('Biology', 'Ms Kemigisa', 0), ('', '', -1), ('Physics', 'Mr Ojok', 0)],
        [('', '', -1), ('History', 'Ms Nalwoga', 0), ('Chemistry', 'Mr Ssali', 0),
         ('English', 'Ms Aber', 0), ('', '', -1)],
        [('Games', 'Coach Mugisha', 3), ('Games', 'Coach Mugisha', 3), ('Library', 'Ms Adong', 3),
         ('Computer lab', 'Mr Mubaraka', 2), ('Assembly', 'All staff', 3)],
    ]
    for r, p in enumerate(periods):
        by = gy + 30 + r * rh
        o.append(text(t.mx + 76, by + rh / 2 + 4, p, 11, t.mute, '600', 'end', mono=True))
        for c in range(5):
            subject, who, kind = grid[r][c]
            x = gx + c * cw + 3
            w = cw - 6
            if kind < 0:
                o.append(rect(x, by + 5, w, rh - 10, t.bg, rx=6))
                o.append(text(x + w / 2, by + rh / 2 + 4, 'free', 11, t.mute, '500', 'middle'))
                continue
            fills = {0: (t.surface, t.ink, t.line), 1: (t.primary, '#FFFFFF', None),
                     2: (t.accent, '#FFFFFF', None), 3: (t.bg, t.sub, t.line)}
            bgc, fg, stroke = fills[kind]
            o.append(rect(x, by + 5, w, rh - 10, bgc, rx=6, stroke=stroke, sw=1.4 if stroke else 1))
            o.append(text(x + 12, by + 30, subject, 11.5, fg, '700'))
            o.append(text(x + 12, by + 47, who, 10, fg, '400', op=0.68))
            o.append(text(x + 12, by + 64, f'Room {12 + r * 3 + c}', 9.5, fg, '400', op=0.45))

    ly = hy + 798
    for i, (col, label) in enumerate([(t.primary, 'Core subject'), (t.accent, 'Practical / lab'),
                                      (t.bg, 'Non-teaching')]):
        lx = t.mx + 22 + i * 178
        o.append(rect(lx, ly - 10, 12, 12, col, rx=3,
                      stroke=t.line if col == t.bg else None, sw=1.4))
        o.append(text(lx + 20, ly, label, 11.5, t.sub, '500'))

    rx = t.mx + gw + 24
    rw = t.mr - rx
    o.append(card(rx, hy + 246, rw, 292, t))
    o += sec(rx + 20, hy + 280, 'FEE COLLECTION, TERM II', t)
    o += donut(rx + rw / 2, hy + 388, 66, [(0.62, t.primary), (0.19, t.accent), (0.19, t.line)])
    o.append(text(rx + rw / 2, hy + 384, '81%', 29, t.ink, '700', 'middle'))
    o.append(text(rx + rw / 2, hy + 405, 'collected', 11, t.mute, '500', 'middle'))
    for i, (label, col, val) in enumerate([('MTN & Airtel Money', t.primary, 'UGX 29.4M'),
                                           ('Bank and Visa', t.accent, 'UGX 9.0M'),
                                           ('Still outstanding', t.line, 'UGX 9.1M')]):
        yy = hy + 476 + i * 22
        o.append(rect(rx + 20, yy - 9, 11, 11, col, rx=2))
        o.append(text(rx + 38, yy, label, 11.5, t.sub, '500'))
        o.append(text(rx + rw - 20, yy, val, 11.5, t.ink, '700', 'end'))

    o.append(card(rx, hy + 562, rw, 264, t))
    o += sec(rx + 20, hy + 594, 'SIGNED IN RIGHT NOW', t)
    for i, (who, n, note) in enumerate([('Parents', '1,884', 'results, fees, absences'),
                                        ('Teachers', '96', 'marks and attendance'),
                                        ('Administrators', '12', 'full access, audited')]):
        yy = hy + 612 + i * 70
        o.append(rect(rx + 20, yy, rw - 40, 64, t.bg, rx=8))
        o.append(circle(rx + 52, yy + 32, 18, t.primary, op=0.12))
        o += ic_user(rx + 52, yy + 32, t.primary, 0.85)
        o.append(text(rx + 82, yy + 26, who, 13, t.ink, '700'))
        o.append(text(rx + 82, yy + 44, note, 10.5, t.mute, '400'))
        o.append(text(rx + rw - 34, yy + 38, n, 16, t.accent, '700', 'end'))
    return o


def hospital(t):
    o = shell(
        t, window='globalhealthrescue.com — Hospital Management System',
        brand='GHR', org='Health',
        nav=[('WARDS', ic_grid), ('PATIENTS', ic_user), ('CLINIC', ic_cal),
             ('LAB', ic_flask), ('PHARMACY', ic_pillbox), ('CLAIMS', ic_card_i)],
        active=1, mark=lambda x, y: ic_cross(x, y, '#FFFFFF', 1),
        user=('Dr A. Nsubuga', 'Consultant', 'AN'),
        title='Sarah Nakato · PID 0084-2261',
        subtitle='Ward B, bed 12 · admitted 14 Feb 2026 · under Dr A. Nsubuga',
        kpis=[('PATIENTS SEEN TODAY', '312', '48 admitted overnight'),
              ('BEDS OCCUPIED', '87%', '212 of 244, across 18 wards'),
              ('CLAIMS CLEARED', '97.4%', 'reconciled with six insurers')],
        actions=[('Print chart', False), ('New order', True)])

    hy = t.head
    lw = 556
    o.append(card(t.mx, hy + 246, lw, 172, t))
    o.append(circle(t.mx + 60, hy + 320, 31, t.primary, op=0.1))
    o += ic_user(t.mx + 60, hy + 320, t.primary, 0.5)
    o.append(text(t.mx + 112, hy + 300, 'Sarah Nakato', 20, t.ink, '700'))
    o.append(text(t.mx + 112, hy + 321, 'Female · 34 years · 62 kg · next of kin on file',
                  12, t.sub, '400'))
    parts, w1 = pill(t.mx + 112, hy + 334, 'BLOOD O+', t.primary, '#DCEDEB', 10)
    o += parts
    parts, w2 = pill(t.mx + 112 + w1 + 8, hy + 334, 'NO KNOWN ALLERGIES', t.ok, t.ok_bg, 10)
    o += parts
    o += pill(t.mx + 112 + w1 + w2 + 16, hy + 334, 'NHIF · ACTIVE', t.accent, '#FBE4E9', 10)[0]

    o.append(card(t.mx, hy + 434, lw, 250, t))
    o += sec(t.mx + 20, hy + 466, 'OBSERVATIONS · LAST 12 HOURS', t, 'RECORDED 4-HOURLY', lw - 40)
    o.append(rect(t.mx + 20, hy + 482, lw - 40, 152, t.bg, rx=6))
    for i in range(1, 4):
        o.append(line(t.mx + 20, hy + 482 + i * 38, t.mx + lw - 20, hy + 482 + i * 38, t.line, 1))
    o += spark(t.mx + 38, hy + 498, lw - 76, 120, [78, 84, 81, 92, 88, 96, 90, 99, 94], t, t.accent)
    o += spark(t.mx + 38, hy + 498, lw - 76, 120, [37, 38, 37, 39, 38, 39, 38, 38, 37],
               t, t.primary, fill=False)
    for i, (col, label, val) in enumerate([(t.accent, 'Pulse', '94 bpm'),
                                           (t.primary, 'Temperature', '37.4 °C')]):
        lx = t.mx + 20 + i * 210
        o.append(rect(lx, hy + 652, 11, 11, col, rx=2))
        o.append(text(lx + 18, hy + 662, label, 11.5, t.sub, '500'))
        o.append(text(lx + 108, hy + 662, val, 11.5, t.ink, '700'))

    o += sec(t.mx, hy + 724, 'ACTIVE ORDERS', t, '3 OPEN', lw)
    for i, (label, sub, state, col, bgc) in enumerate([
            ('Full blood count', 'Laboratory · requested 09:20', 'RESULT IN', t.ok, t.ok_bg),
            ('Amoxicillin 500 mg', 'Pharmacy · t.d.s. for 5 days', 'DISPENSED', t.primary, '#DCEDEB'),
            ('Chest X-ray', 'Radiology · requested 11:05', 'WAITING', t.warn, t.warn_bg)]):
        yy = hy + 742 + i * 62
        o.append(rect(t.mx, yy, lw, 52, t.surface, rx=8, stroke=t.line, sw=1.4))
        o.append(rect(t.mx, yy, 4, 52, col, rx=2))
        o.append(text(t.mx + 20, yy + 22, label, 13, t.ink, '600'))
        o.append(text(t.mx + 20, yy + 40, sub, 11, t.mute, '400'))
        o += pill(t.mx + lw - 18, yy + 15, state, col, bgc, anchor='end')[0]

    rx = t.mx + lw + 26
    rw = t.mr - rx
    o.append(card(rx, hy + 246, rw, 620, t))
    o += sec(rx + 20, hy + 280, "TODAY'S CLINIC", t, '18 SLOTS · 4 FREE', rw - 40)
    slots = [('08:00', 'Ssentongo, J.', 'Review · hypertension', 0),
             ('08:30', 'Achieng, P.', 'New patient', 0),
             ('09:00', 'Nakato, S.', 'Ward round', 2),
             ('09:30', '', '', -1),
             ('10:00', 'Okot, D.', 'Post-operative check', 0),
             ('10:30', 'Birungi, M.', 'Antenatal · 28 weeks', 1),
             ('11:00', '', '', -1),
             ('11:30', 'Kayondo, T.', 'Follow-up · diabetes', 0),
             ('12:00', 'Amuge, R.', 'Referral from Mulago', 1)]
    for i, (tm, who, kind, k) in enumerate(slots):
        yy = hy + 300 + i * 62
        o.append(text(rx + 20, yy + 28, tm, 11.5, t.mute, '600', mono=True))
        bx, bw = rx + 76, rw - 96
        if k < 0:
            o.append(rect(bx, yy, bw, 48, t.bg, rx=8))
            o.append(text(bx + bw / 2, yy + 29, 'available', 11.5, t.mute, '500', 'middle'))
            continue
        fill = {0: t.surface, 1: t.primary, 2: t.accent}[k]
        fg = t.ink if k == 0 else '#FFFFFF'
        o.append(rect(bx, yy, bw, 48, fill, rx=8, stroke=t.line if k == 0 else None, sw=1.4))
        o.append(text(bx + 16, yy + 21, who, 12.5, fg, '700'))
        o.append(text(bx + 16, yy + 38, kind, 10.5, fg, '400', op=0.68))
    return o


def wildlife(t):
    o = shell(
        t, window='Wildlife Offenders Database — Uganda Wildlife Authority',
        brand='Offenders DB', org='UWA · Enforcement',
        nav=[('Case board', ic_folder), ('Offenders', ic_user), ('Seizures', ic_box),
             ('Biometrics', ic_search), ('Analytics', ic_chart), ('Ranger posts', ic_pin)],
        active=1,
        mark=lambda x, y: [path(f'M {x - 9} {y + 6} v -8 a 9 9 0 0 1 18 0 v 8',
                                stroke='#FFFFFF', sw=2.2),
                           path(f'M {x - 11} {y - 3} l 11 -8 l 11 8', stroke='#FFFFFF', sw=2.2)],
        user=('S. Openy', 'Enforcement officer', 'SO'),
        title='Offender file UWA-4471',
        subtitle='Poaching, ivory · Murchison Falls · opened 3 Feb 2026 · now in court',
        kpis=[('CASES OPEN', '1,286', 'across ten national parks'),
              ('OFFENDERS ON FILE', '4,371', 'fingerprint and photograph'),
              ('BROUGHT TO COURT', '68%', 'of cases opened since 2021')],
        actions=[('Export brief', False), ('Escalate', True)])

    hy = t.head
    lw = 388
    o.append(card(t.mx, hy + 246, lw, 392, t))
    o += sec(t.mx + 20, hy + 280, 'FINGERPRINT MATCH', t)
    o.append(rect(t.mx + 20, hy + 296, lw - 40, 246, t.primary_d, rx=8))
    cx, cy = t.mx + lw / 2, hy + 422
    for i in range(7):
        r = 15 + i * 14
        o.append(path(f'M {cx - r} {cy + 10} a {r} {r * 1.15} 0 0 1 {2 * r} 0',
                      stroke=t.accent, sw=2.8, op=0.22 + i * 0.1))
    o.append(path(f'M {cx - 6} {cy + 10} a 8 9 0 0 1 12 0', stroke=t.accent, sw=2.8))
    for dx, dy in ((-1, -1), (1, 1)):
        o.append(line(cx + dx * 94, cy + dy * 72, cx + dx * 94, cy + dy * 46, t.accent, 3, cap='round'))
        o.append(line(cx + dx * 94, cy + dy * 72, cx + dx * 68, cy + dy * 72, t.accent, 3, cap='round'))
    o.append(text(t.mx + 20, hy + 572, 'CONFIDENCE', 10.5, t.mute, '700', ls=1.1))
    o.append(text(t.mx + lw - 20, hy + 572, '99.2%', 15, t.accent, '700', 'end'))
    o.append(rect(t.mx + 20, hy + 584, lw - 40, 8, t.line, rx=4))
    o.append(rect(t.mx + 20, hy + 584, (lw - 40) * 0.992, 8, t.accent, rx=4))
    o.append(text(t.mx + 20, hy + 616, 'Matched against 4,371 records in 0.4 seconds',
                  11.5, t.mute, '400'))

    o.append(card(t.mx, hy + 658, lw, 208, t))
    o += sec(t.mx + 20, hy + 692, 'WHERE THIS IS HAPPENING', t)
    o += uganda((t.mx + 234, hy + 696, 136, 154), t, uid='uwa', land=t.primary_d,
                water=t.bg, border=t.sub, lakes=False, chrome=False, towns=False,
                roads=False, dense=False,
                pins=[(31.72, 2.28, True), (31.35, 0.32, False), (33.95, 0.72, False),
                      (30.05, -0.35, False)])
    for i, (park, n, share) in enumerate([('Murchison Falls', '412', 1.0),
                                          ('Queen Elizabeth', '288', 0.70),
                                          ('Kidepo Valley', '134', 0.33)]):
        yy = hy + 722 + i * 44
        o.append(text(t.mx + 20, yy, park, 11.5, t.sub, '500'))
        o.append(text(t.mx + 200, yy, n, 12.5, t.ink, '700', 'end'))
        o.append(rect(t.mx + 20, yy + 8, 180, 5, t.line, rx=3))
        o.append(rect(t.mx + 20, yy + 8, 180 * share, 5,
                      t.accent if i == 0 else t.sub, rx=3, op=1 if i == 0 else 0.6))

    rx = t.mx + lw + 26
    rw = t.mr - rx
    o += sec(rx, hy + 280, 'CASE BOARD', t, 'FILTER: MURCHISON FALLS', rw)
    o += table(rx, hy + 296, rw,
               [('CASE', 172), ('OFFENCE', 250), ('RANGER POST', 196), ('', 0)],
               [[('n', 'UWA-4471'), ('m', 'Poaching · ivory'), ('m', 'Pakuba'),
                 ('p', ('IN COURT', t.warn, t.warn_bg))],
                [('n', 'UWA-4468'), ('m', 'Illegal grazing'), ('m', 'Tangi'),
                 ('p', ('CHARGED', t.sub, '#22392C'))],
                [('n', 'UWA-4462'), ('m', 'Bushmeat trade'), ('m', 'Bugungu'),
                 ('p', ('CLOSED', t.ok, t.ok_bg))],
                [('n', 'UWA-4455'), ('m', 'Snaring'), ('m', 'Karuma'),
                 ('p', ('IN COURT', t.warn, t.warn_bg))],
                [('n', 'UWA-4450'), ('m', 'Trafficking · pangolin'), ('m', 'Pakuba'),
                 ('p', ('ESCALATED', t.bad, t.bad_bg))],
                [('n', 'UWA-4441'), ('m', 'Poaching · pangolin'), ('m', 'Chobe'),
                 ('p', ('CHARGED', t.sub, '#22392C'))]],
               t, rh=48)

    o += sec(rx, hy + 652, 'SEIZURES BY QUARTER', t, '2025 → 2026', rw)
    o.append(card(rx, hy + 668, rw, 198, t))
    o += bars(rx + 30, hy + 702, rw - 60, 114, [46, 62, 51, 78, 94, 71, 88, 112],
              ['Q1', 'Q2', 'Q3', 'Q4', 'Q1', 'Q2', 'Q3', 'Q4'], t, hi=7)
    return o


def seed(t):
    o = shell(
        t, window='National Seed Tracking & Tracing System',
        brand='Seed Trace', org='MAAIF · Crop Inspection',
        nav=[('Batches', ic_box), ('Field verification', ic_qr), ('Vouchers', ic_card_i),
             ('Agro-dealers', ic_truck), ('Quality control', ic_flask), ('Data exchange', ic_grid)],
        active=1, mark=lambda x, y: ic_leaf(x, y, '#FFFFFF', 1),
        user=('J. Chelangat', 'Crop inspector', 'JC'),
        title='Field verification',
        subtitle='Batch NSC-2026-04471 · scanned at a Kapchorwa agro-dealer at 11:04',
        kpis=[('BATCHES CERTIFIED', '9,850', 'this planting season'),
              ('FIELD SCANS', '1.24M', 'by 3,100 inspectors and dealers'),
              ('COUNTERFEITS STOPPED', '412', 'pulled from sale this season')],
        actions=[('Scan history', False), ('Certify batch', True)])

    hy = t.head
    lw = 366
    o.append(card(t.mx, hy + 246, lw, 620, t))
    o += sec(t.mx + 20, hy + 280, 'CODE ON THE BAG', t)
    o += qr_block(t.mx + 46, hy + 296, 274, t, seed=19)
    o += pill(t.mx + 46, hy + 588, 'VERIFIED AUTHENTIC', t.ok, t.ok_bg, 11.5, h=26)[0]
    o.append(text(t.mx + 20, hy + 656, 'NSC-2026-04471', 22, t.ink, '700', mono=True))
    o.append(text(t.mx + 20, hy + 680, 'Maize · Longe 10H · 2 kg bag', 12.5, t.sub, '400'))
    for i, (k, v) in enumerate([('Certified', '14 Jan 2026'),
                                ('Germination', '94% · pass'),
                                ('Moisture', '11.2% · pass'),
                                ('Multiplied at', 'Masindi, NARO'),
                                ('Expires', '30 Sep 2026')]):
        yy = hy + 716 + i * 28
        o.append(text(t.mx + 20, yy, k, 11.5, t.mute, '500'))
        o.append(text(t.mx + lw - 20, yy, v, 11.5, t.ink, '600', 'end'))

    rx = t.mx + lw + 24
    rw = t.mr - rx - 236
    o.append(card(rx, hy + 246, rw, 292, t))
    o += sec(rx + 22, hy + 280, 'CHAIN OF CUSTODY', t, 'EVERY HAND-OFF SIGNED', rw - 44)
    stages = [('Breeder', 'NARO Namulonge', '02 Nov', 1), ('Multiplier', 'Masindi farm', '18 Dec', 1),
              ('Processor', 'NSC Kampala', '14 Jan', 1), ('Agro-dealer', 'Kapchorwa', 'today', 2),
              ('Farmer', 'not yet scanned', '—', 0)]
    sx = rx + 58
    step = (rw - 116) / (len(stages) - 1)
    o.append(line(sx, hy + 370, sx + step * 4, hy + 370, t.line, 4, cap='round'))
    o.append(line(sx, hy + 370, sx + step * 3, hy + 370, t.primary, 4, cap='round'))
    for i, (name, where, when, state) in enumerate(stages):
        x = sx + i * step
        if state == 2:
            o.append(circle(x, hy + 370, 21, t.accent, op=0.24))
            o.append(circle(x, hy + 370, 12, t.accent))
            o.append(path(f'M {x - 5} {hy + 370} l 4 4 l 7 -8', stroke='#FFFFFF', sw=2.6))
        elif state:
            o.append(circle(x, hy + 370, 12, t.primary))
            o.append(path(f'M {x - 5} {hy + 370} l 4 4 l 7 -8', stroke='#FFFFFF', sw=2.6))
        else:
            o.append(circle(x, hy + 370, 12, t.surface, stroke=t.line, sw=3))
        o.append(text(x, hy + 412, name, 12, t.ink if state else t.mute, '700', 'middle'))
        o.append(text(x, hy + 429, where, 10.5, t.sub if state else t.mute, '400', 'middle'))
        o.append(text(x, hy + 446, when, 10, t.mute, '500', 'middle', mono=True))
    o.append(text(rx + 22, hy + 498, 'A bag that skips a stage cannot be sold: the scan fails '
                                     'and the dealer is flagged for inspection.', 12, t.mute, '400'))

    half = (rw - 22) / 2
    o.append(card(rx, hy + 562, half, 304, t))
    o += sec(rx + 22, hy + 596, 'VOUCHERS REDEEMED', t)
    o += donut(rx + half / 2, hy + 704, 62, [(0.68, t.primary), (0.32, t.line)])
    o.append(text(rx + half / 2, hy + 700, '68%', 27, t.ink, '700', 'middle'))
    o.append(text(rx + half / 2, hy + 721, 'of 1.24M', 11, t.mute, '500', 'middle'))
    o.append(text(rx + 22, hy + 800, '842,000 subsidised bags claimed', 12, t.sub, '500'))
    o.append(text(rx + 22, hy + 822, 'by 611,000 registered farmers', 12, t.mute, '400'))

    lx = rx + half + 22
    o.append(card(lx, hy + 562, half, 304, t))
    o += sec(lx + 22, hy + 596, 'FLAGGED THIS WEEK', t)
    for i, (code, why) in enumerate([('NSC-2026-03318', 'scanned twice · Mbale'),
                                     ('NSC-2025-99120', 'certificate expired'),
                                     ('NSC-2026-04102', 'unregistered dealer'),
                                     ('NSC-2026-01877', 'germination below 80%')]):
        yy = hy + 618 + i * 58
        o.append(rect(lx + 20, yy, half - 40, 48, t.bg, rx=6))
        o.append(rect(lx + 20, yy, 4, 48, t.bad, rx=2))
        o.append(text(lx + 38, yy + 21, code, 11.5, t.ink, '600', mono=True))
        o.append(text(lx + 38, yy + 37, why, 10.5, t.mute, '400'))

    def scanner(px, py, pw, ph):
        s = [rect(px, py, pw, 78, t.primary)]
        s.append(rect(px, py, pw, 25, t.primary, rx=25))
        s.append(text(px + 20, py + 42, 'Verify a bag', 15, '#FFFFFF', '700'))
        s.append(text(px + 20, py + 61, 'Kapchorwa · online', 10.5, '#FFFFFF', '400', op=0.6))
        s.append(rect(px + 14, py + 94, pw - 28, 170, t.primary_d, rx=10))
        vx, vy, vw = px + 32, py + 112, pw - 64
        for dx, dy in ((0, 0), (1, 0), (0, 1), (1, 1)):
            cx2 = vx + dx * vw
            cy2 = vy + dy * 132
            sx2 = 1 if dx == 0 else -1
            sy2 = 1 if dy == 0 else -1
            s.append(line(cx2, cy2, cx2 + sx2 * 22, cy2, t.accent, 3.4, cap='round'))
            s.append(line(cx2, cy2, cx2, cy2 + sy2 * 22, t.accent, 3.4, cap='round'))
        s += qr_block(vx + vw / 2 - 44, vy + 22, 88, t, seed=7)
        s.append(rect(vx, vy + 66, vw, 3, t.accent, rx=2, op=0.9))
        s.append(rect(px + 14, py + 280, pw - 28, 52, t.ok_bg, rx=8))
        s.append(circle(px + 44, py + 306, 13, t.ok))
        s.append(path(f'M {px + 38} {py + 306} l 4 5 l 9 -10', stroke='#FFFFFF', sw=2.6))
        s.append(text(px + 66, py + 302, 'Authentic', 13, t.ok, '700'))
        s.append(text(px + 66, py + 318, 'Longe 10H · 2 kg', 10, t.sub, '400'))
        s.append(text(px + 18, py + 356, 'Batch', 10.5, t.mute, '500'))
        s.append(text(px + pw - 18, py + 356, 'NSC-…4471', 11, t.ink, '600', 'end'))
        s.append(rect(px + 14, py + 376, pw - 28, 44, t.accent, rx=8))
        s.append(text(px + pw / 2, py + 404, 'Redeem voucher', 13.5, '#FFFFFF', '700', 'middle'))
        return s

    o += handset(t.mr - 210, hy + 268, 202, t, scanner)
    return o


def pwd(t):
    o = shell(
        t, window='ICT for Persons with Disabilities Observatory',
        brand='ICT4PWD', org='UCC · NUDIPU',
        nav=[('Observatory', ic_grid), ('Population', ic_user), ('Access to ICT', ic_chart),
             ('Policy briefs', ic_book), ('Districts', ic_pin), ('Sources', ic_list)],
        active=2,
        mark=lambda x, y: [circle(x, y - 7, 3, '#FFFFFF'),
                           path(f'M {x} {y - 4} v 6 M {x - 8} {y - 2} h 16 '
                                f'M {x} {y + 2} l -6 9 M {x} {y + 2} l 6 9',
                                stroke='#FFFFFF', sw=2.2)],
        user=('R. Alupo', 'Research lead', 'RA'),
        title='Access to ICT, by sub-region',
        subtitle='2026 round · 1.42M people on record · weighted to the national population',
        kpis=[('PEOPLE ON RECORD', '1.42M', 'self-reported, with consent'),
              ('DISTRICTS COVERED', '146', 'every district in Uganda'),
              ('OWN A SMARTPHONE', '31.4%', 'up nine points since 2023')],
        actions=[('Methodology', False), ('Download dataset', True)])

    hy = t.head
    cw = 796
    o.append(card(t.mx, hy + 246, cw, 580, t))
    o += sec(t.mx + 22, hy + 280, 'SMARTPHONE OWNERSHIP AMONG PERSONS WITH DISABILITIES',
             t, '2023 → 2026', cw - 44)
    regions = ['Central', 'Kampala', 'Eastern', 'Northern', 'Western', 'Karamoja',
               'West Nile', 'Elgon']
    then = [26, 41, 18, 12, 21, 6, 11, 16]
    now = [38, 57, 27, 19, 31, 11, 18, 24]
    bx, by, bw, bh = t.mx + 74, hy + 314, cw - 108, 366
    for i in range(5):
        yy = by + bh - i * (bh / 4)
        o.append(line(bx, yy, bx + bw, yy, t.line, 1))
        o.append(text(bx - 14, yy + 4, f'{i * 15}%', 10.5, t.mute, '500', 'end'))
    gw = bw / len(regions)
    for i, r in enumerate(regions):
        x = bx + i * gw
        h1, h2 = then[i] / 60 * bh, now[i] / 60 * bh
        o.append(rect(x + gw * 0.16, by + bh - h1, gw * 0.28, h1, t.primary, rx=3, op=0.3))
        o.append(rect(x + gw * 0.5, by + bh - h2, gw * 0.28, h2,
                      t.accent if i == 1 else t.primary, rx=3))
        o.append(text(x + gw * 0.64, by + bh - h2 - 10, f'{now[i]}%', 10.5,
                      t.accent if i == 1 else t.sub, '700', 'middle'))
        o.append(text(x + gw / 2, by + bh + 24, r, 11.5, t.sub, '600', 'middle'))
    for i, (op, label) in enumerate([(0.3, '2023 round'), (1, '2026 round')]):
        lx = t.mx + 22 + i * 132
        o.append(rect(lx, hy + 742, 12, 12, t.primary, rx=3, op=op))
        o.append(text(lx + 20, hy + 752, label, 11.5, t.sub, '500'))
    o.append(text(t.mx + 22, hy + 790, 'Collected door to door with NUDIPU district associations. '
                                       'Nobody is counted without consenting to be.',
                  11.5, t.mute, '400'))

    rx = t.mx + cw + 24
    rw = t.mr - rx
    o.append(card(rx, hy + 246, rw, 292, t))
    o += sec(rx + 20, hy + 280, 'ASSISTIVE TECHNOLOGY IN USE', t)
    for i, (label, pct) in enumerate([('Mobility aid', 58), ('Hearing aid', 34),
                                      ('Screen reader', 22), ('Braille display', 7)]):
        yy = hy + 302 + i * 54
        o.append(text(rx + 20, yy + 12, label, 12.5, t.ink, '600'))
        o.append(text(rx + rw - 20, yy + 12, f'{pct}%', 12.5, t.accent, '700', 'end'))
        o.append(rect(rx + 20, yy + 22, rw - 40, 9, t.line, rx=5))
        o.append(rect(rx + 20, yy + 22, (rw - 40) * pct / 100, 9, t.accent, rx=5))

    o.append(rect(rx, hy + 562, rw, 264, t.primary, rx=8))
    o.append(text(rx + 20, hy + 594, 'WHAT THE EVIDENCE CHANGED', 11, '#FFFFFF', '700',
                  ls=1.2, op=0.6))
    for i, (title2, note) in enumerate([('Universal service fund', 'tabled · Mar 2026'),
                                        ('Accessible procurement', 'adopted · Nov 2025'),
                                        ('Sign language on TV', 'in review')]):
        yy = hy + 614 + i * 68
        o.append(rect(rx + 20, yy, rw - 40, 56, '#FFFFFF', rx=6, op=0.1))
        o.append(rect(rx + 20, yy, 4, 56, t.accent, rx=2))
        o.append(text(rx + 40, yy + 24, title2, 13, '#FFFFFF', '700'))
        o.append(text(rx + 40, yy + 42, note, 11, '#FFFFFF', '400', op=0.62))
    return o


def rights(t):
    o = shell(
        t, window='CEHURD — Human Rights Reporting System',
        brand='Case Register', org='CEHURD · Restricted',
        nav=[('Case register', ic_folder), ('Intake', ic_list), ('Evidence vault', ic_lock),
             ('Referrals', ic_scale), ('Trends', ic_chart), ('Audit log', ic_shield)],
        active=2, mark=lambda x, y: ic_lock(x, y, '#FFFFFF', 1),
        user=('P. Nabukenya', 'Documentation lead', 'PN'),
        title='Evidence vault · case 2026-0918',
        subtitle='Sealed 19 Jan · three people hold access · every open is written to the log',
        kpis=[('CASES DOCUMENTED', '4,930', 'since 2019'),
              ('EVIDENCE ITEMS HELD', '18,240', 'encrypted at rest'),
              ('KEYS ON THIS SERVER', 'none', 'held off-site by two officers')],
        actions=[('Audit log', False), ('Add evidence', True)])

    hy = t.head
    lw = 432
    o.append(rect(t.mx, hy + 246, lw, 326, t.primary_d, rx=8, stroke=t.line, sw=1.4))
    lx, ly = t.mx + lw / 2, hy + 368
    o.append(circle(lx, ly, 70, t.accent, op=0.1))
    o.append(circle(lx, ly, 53, t.accent, op=0.16))
    o.append(path(f'M {lx - 24} {ly - 5} v -16 a 24 24 0 0 1 48 0 v 16', stroke=t.accent, sw=6))
    o.append(rect(lx - 37, ly - 6, 74, 56, t.accent, rx=8))
    o.append(circle(lx, ly + 15, 6.5, t.primary_d))
    o.append(rect(lx - 3, ly + 19, 6, 13, t.primary_d, rx=3))
    o.append(text(lx, hy + 488, 'Sealed', 19, t.ink, '700', 'middle'))
    o.append(text(lx, hy + 512, 'Opened four times since January. Each open is written',
                  11.5, t.sub, '400', 'middle'))
    o.append(text(lx, hy + 530, 'to the audit log against a named person and a reason.',
                  11.5, t.sub, '400', 'middle'))

    o.append(card(t.mx, hy + 596, lw, 270, t))
    o += sec(t.mx + 20, hy + 630, 'ATTACHED EVIDENCE', t, '4 ITEMS', lw - 40)
    for i, (kind, name, size, when) in enumerate([
            ('IMG', 'Clinic intake photograph', '2.4 MB', '12 Jan'),
            ('PDF', 'Medical report, redacted', '840 KB', '12 Jan'),
            ('AUD', 'Witness statement', '11 min', '17 Jan'),
            ('DOC', 'Referral letter to counsel', '96 KB', '02 Feb')]):
        yy = hy + 648 + i * 52
        o.append(rect(t.mx + 20, yy, lw - 40, 44, t.bg, rx=6))
        o.append(rect(t.mx + 30, yy + 10, 34, 24, t.primary, rx=4))
        o.append(text(t.mx + 47, yy + 26, kind, 9.5, t.ink, '700', 'middle', 0.6))
        o.append(text(t.mx + 76, yy + 21, name, 12, t.ink, '500'))
        o.append(text(t.mx + 76, yy + 36, f'{size} · added {when}', 10, t.mute, '400'))
        o += ic_lock(t.mx + lw - 40, yy + 22, t.accent, 0.85)

    rx = t.mx + lw + 26
    rw = t.mr - rx
    o.append(card(rx, hy + 246, rw, 326, t))
    o += sec(rx + 22, hy + 280, 'CASE TIMELINE', t, 'AWAITING A HEARING DATE', rw - 44)
    events = [('12 Jan', 'Intake recorded', 'Kampala field office · consented', 1),
              ('19 Jan', 'Evidence sealed', '4 items · keys held off-site', 2),
              ('02 Feb', 'Referred to counsel', 'legal aid partner accepted', 1),
              ('21 Feb', 'Filed in court', 'High Court, civil division', 1),
              ('—', 'Hearing', 'date not yet set', 0)]
    tx = rx + 118
    o.append(line(tx, hy + 316, tx, hy + 316 + (len(events) - 1) * 54, t.line, 2))
    for i, (when, what, note, state) in enumerate(events):
        yy = hy + 316 + i * 54
        o.append(text(rx + 98, yy + 5, when, 11, t.mute, '600', 'end', mono=True))
        if state == 2:
            o.append(circle(tx, yy, 9, t.accent))
            o.append(circle(tx, yy, 16, t.accent, op=0.24))
        elif state:
            o.append(circle(tx, yy, 7, t.sub))
        else:
            o.append(circle(tx, yy, 7, t.surface, stroke=t.line, sw=2.5))
        o.append(text(tx + 26, yy + 1, what, 13, t.ink if state else t.mute, '700'))
        o.append(text(tx + 26, yy + 18, note, 11, t.sub if state else t.mute, '400'))

    o.append(card(rx, hy + 596, rw, 270, t))
    o += sec(rx + 22, hy + 630, 'CASES DOCUMENTED, BY MONTH', t, 'LAST 12 MONTHS', rw - 44)
    o += spark(rx + 40, hy + 656, rw - 80, 154, [22, 31, 27, 44, 38, 52, 47, 61, 58, 72, 66, 84], t)
    o.append(text(rx + 22, hy + 842, 'A rising line here is reporting getting easier, '
                                     'not rights getting worse.', 11.5, t.mute, '400'))
    return o


def commerce(t):
    o = shell(
        t, window='afriinventions.com — commerce & estates back office',
        brand='AfriInventions', org='Commerce · Estates',
        nav=[('Storefront', ic_home), ('Listings', ic_bag), ('Orders', ic_list),
             ('Inventory', ic_box), ('Customers', ic_user), ('Payouts', ic_card_i)],
        active=1, mark=lambda x, y: ic_bag(x, y, '#FFFFFF', 1),
        user=('B. Nakimuli', 'Store manager', 'BN'),
        title='Listings & orders',
        subtitle='Two storefronts on one back office · 1,860 live listings · 14 orders today',
        kpis=[('REVENUE THIS MONTH', 'UGX 214M', 'across both storefronts'),
              ('LIVE LISTINGS', '1,860', '412 properties · 1,448 goods'),
              ('CHECKOUT COMPLETION', '96.8%', 'Mobile Money, Visa, Mastercard')],
        actions=[('Reports', False), ('Add listing', True)])

    hy = t.head
    gwid = 826
    o += sec(t.mx, hy + 278, 'RECENTLY LISTED', t, 'VIEW ALL 1,860', gwid)
    items = [('Kololo · 4 bedroom house', 'UGX 780M', 2, 'PROPERTY', 'Agent: Nakasero office'),
             ('Solar home kit, 200W', 'UGX 1.2M', 0, '34 IN STOCK', 'Ships in 2 days'),
             ('Naguru · 25 decimal plot', 'UGX 240M', 1, 'LAND', 'Title verified'),
             ('Water pump, 2HP', 'UGX 640K', 0, '8 IN STOCK', 'Ships in 2 days'),
             ('Ntinda · 2 bedroom flat', 'UGX 320M', 2, 'PROPERTY', 'Available now'),
             ('Drip irrigation set', 'UGX 2.4M', 0, '12 IN STOCK', 'Ships in 5 days')]
    cwid, ch = (gwid - 44) / 3, 286
    for i, (name, price, kind, meta, note) in enumerate(items):
        cx = t.mx + (i % 3) * (cwid + 22)
        cy = hy + 294 + (i // 3) * (ch + 22)
        o.append(card(cx, cy, cwid, ch, t))
        o.append(rect(cx + 1.4, cy + 1.4, cwid - 2.8, 154, t.bg, rx=8))
        mid = cx + cwid / 2
        if kind == 2:
            o.append(path(f'M {mid - 52} {cy + 104} l 52 -40 l 52 40 v 44 h -104 z',
                          fill=t.accent, op=0.92))
            o.append(rect(mid - 13, cy + 116, 26, 32, t.primary, rx=2))
            o.append(path(f'M {mid - 60} {cy + 108} l 60 -46 l 60 46', stroke=t.primary, sw=4))
        elif kind == 1:
            o.append(path(f'M {mid - 50} {cy + 124} l 22 -60 l 80 12 l -18 60 z',
                          fill=t.accent, op=0.42, stroke=t.accent, sw=3))
            o.append(line(mid - 39, cy + 96, mid + 31, cy + 108, t.primary, 1.4, dash='6 5', op=0.45))
            o.append(path(f'M {mid} {cy + 80} c -8 0 -14 6 -14 14 c 0 10 14 24 14 24 '
                          f's 14 -14 14 -24 c 0 -8 -6 -14 -14 -14 z', fill=t.primary))
            o.append(circle(mid, cy + 94, 5, t.accent))
        else:
            o.append(path(f'M {mid} {cy + 58} l 42 21 v 44 l -42 21 l -42 -21 v -44 z',
                          fill=t.primary, op=0.92))
            o.append(path(f'M {mid - 42} {cy + 79} l 42 21 l 42 -21', stroke='#FFFFFF',
                          sw=2.4, op=0.42))
            o.append(line(mid, cy + 100, mid, cy + 144, '#FFFFFF', 2.4, op=0.42))
            o.append(rect(mid - 16, cy + 44, 32, 20, t.accent, rx=3))
        o.append(text(cx + 18, cy + 192, name, 13.5, t.ink, '700'))
        o.append(text(cx + 18, cy + 211, note, 11, t.mute, '400'))
        o.append(text(cx + 18, cy + 242, price, 17, t.accent, '700'))
        o += pill(cx + 18, cy + 254, meta, t.sub, t.bg, 9.5, h=20)[0]

    rx = t.mx + gwid + 24
    rw = t.mr - rx
    o.append(card(rx, hy + 246, rw, 292, t))
    o += sec(rx + 20, hy + 280, 'REVENUE, LAST 12 MONTHS', t)
    o += spark(rx + 32, hy + 304, rw - 64, 146,
               [58, 64, 61, 79, 86, 74, 98, 112, 104, 138, 152, 214], t)
    o.append(text(rx + 20, hy + 494, 'UGX 214M', 25, t.ink, '700'))
    o.append(text(rx + 20, hy + 516, '+41% on the same month last year', 11.5, t.ok, '600'))

    o.append(card(rx, hy + 562, rw, 304, t))
    o += sec(rx + 20, hy + 596, 'ORDERS TODAY', t, '14 · UGX 6.1M', rw - 40)
    for i, (ref, who, amt, state, col, bgc) in enumerate([
            ('#48210', 'MTN MoMo · Kampala', 'UGX 1.2M', 'PAID', t.ok, t.ok_bg),
            ('#48209', 'Visa · Entebbe', 'UGX 640K', 'PAID', t.ok, t.ok_bg),
            ('#48208', 'Airtel Money · Gulu', 'UGX 2.4M', 'PENDING', t.warn, t.warn_bg),
            ('#48207', 'MTN MoMo · Jinja', 'UGX 320K', 'PAID', t.ok, t.ok_bg)]):
        yy = hy + 618 + i * 60
        o.append(rect(rx + 20, yy, rw - 40, 50, t.bg, rx=6))
        o.append(text(rx + 36, yy + 21, ref, 12.5, t.ink, '700', mono=True))
        o.append(text(rx + 36, yy + 38, who, 10.5, t.mute, '400'))
        o.append(text(rx + rw - 36, yy + 21, amt, 12.5, t.ink, '600', 'end'))
        o += pill(rx + rw - 36, yy + 26, state, col, bgc, 9, anchor='end')[0]
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
        t = THEMES[slug]
        svg = frame(fn(t))
        with open(os.path.join(out, f'{slug}.svg'), 'w', encoding='utf-8') as f:
            f.write(svg)
        print(f'{slug:26} {len(svg) / 1024:6.1f} KB   {t.shell:8} {t.primary} / {t.accent}')
