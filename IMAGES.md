# Images — what to supply and where to put it

The home page is finished and shippable **without any of these files**. Every
image slot renders a labelled placeholder naming the exact path and pixel size,
so nothing looks broken while you're still gathering artwork.

Drop a file at the path below and it becomes the real image on the next page
load. No template edit, no setting to flip, nothing to tell me.

Everything is relative to `public/`.

---

## 1. Your portrait — the one that matters most

| | |
|---|---|
| **Path** | `public/images/portrait.jpg` |
| **Size** | 900 × 1100px (portrait, 4:5) |
| **Shows** | Hero, beside the headline |

This is the single highest-impact image on the site — it sits next to your name
above the fold. Worth doing properly:

- Shot against a plain wall or a real workspace, not a busy background.
- Even, soft light on your face. Window light beats a phone flash.
- Framed from roughly the chest up, eyes about a third down the frame.
- Looking at the camera. It reads as directness, which is the whole point here.
- Neutral or dark clothing sits best against the site's cream and navy.

Export as JPG, quality ~80, under about 250KB.

---

## 2. Client logos

| | |
|---|---|
| **Path** | `public/images/clients/{slug}.png` |
| **Size** | 240 × 80px, transparent PNG |
| **Shows** | "Organisations I've delivered for" strip |

Until a logo file exists the organisation's **name is displayed as a wordmark**,
which looks deliberate rather than missing — so add these only where you have a
clean logo and the right to display it.

The slug is the organisation name lowercased with hyphens. Current list:

| Organisation | File |
|---|---|
| Ministry of Agriculture | `clients/ministry-of-agriculture.png` |
| Uganda Wildlife Authority | `clients/uganda-wildlife-authority.png` |
| Uganda Communications Commission | `clients/uganda-communications-commission.png` |
| NUDIPU | `clients/nudipu.png` |
| CEHURD | `clients/cehurd.png` |
| Makerere University | `clients/makerere-university.png` |
| Eight Tech Consults | `clients/eight-tech-consults.png` |

Logos render greyscale and lift to full colour on hover, so a busy multi-colour
logo still sits calmly in the row.

To change who appears here, edit the `portfolio.clients` setting.

> **One caution:** displaying a client's logo implies they endorse you. Use the
> ones you have permission to show, and keep the rest as wordmarks.

---

## 3. System screenshots

| | |
|---|---|
| **Path** | `public/images/systems/{project-slug}.png` |
| **Size** | 1600 × 1000px (16:10) |
| **Shows** | "Systems running in production", inside a browser frame |

Each screenshot is drawn inside a browser chrome, so a plain screengrab of the
app looks like a product shot without any editing.

Current projects on the home page:

- `systems/ulits.png`
- `systems/school-dynamics.png`
- `systems/hospital-management.png`

Capture the screen that makes the system obvious in one glance — usually a
populated dashboard or list, never an empty state or a login form. **Blur or
replace real names, patient data and phone numbers before exporting.**

A project can also carry its own upload via the admin
(*Portfolio → Projects → Cover image*), which takes precedence over this path.

---

## 4. Testimonials

| | |
|---|---|
| **Setting** | `portfolio.testimonials` |
| **Photo path** | `public/images/people/{name}.jpg`, 200 × 200px square |
| **Shows** | "What the people I've worked with say" |

**The whole section stays hidden until you add at least one.** I deliberately
did not write placeholder quotes: a testimonial is attributed to a named person,
and invented words — even as filler meant to be replaced — put statements in a
real person's mouth that they never said. One accidental deploy and that's
published.

Each entry:

```json
{
  "quote": "What they actually said.",
  "name":  "Their Name",
  "role":  "Their job title",
  "org":   "Their organisation",
  "photo": "images/people/their-name.jpg"
}
```

`photo` is optional — without one, a labelled avatar slot shows instead.

Ask for these by email and paste the reply verbatim. Two or three specific,
concrete quotes beat six vague ones.

---

## Checking your work

After adding files:

```bash
php artisan view:clear
```

Then load the home page. Any slot still showing a dashed placeholder tells you
its exact expected path — that's the file it's waiting for.
