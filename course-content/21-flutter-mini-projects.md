# Course 21 — Flutter Mini-Projects: Local Diary & News App

**Tier 3 · Capstone Systems · Level: Intermediate · Prerequisites: Course 11 (Course 13 helps)**

Two complete apps, start to finish — the fastest way to turn Flutter knowledge into
Flutter *confidence*. The Diary app masters offline storage (SQLite); the News app
masters live internet data (HTTP + JSON). Together they cover the two halves of
almost every real mobile app.

**What you will build**

- A private diary that stores entries on the phone (works with no internet)
- A news reader pulling live articles from an API, with images and detail pages

---

## Project 1 — Complete Local Diary App (offline-first)

1. **Part 1: setup & first screen** — ▶ https://www.youtube.com/watch?v=tjA81whQw_Q
2. **Part 2: entry form** — ▶ https://www.youtube.com/watch?v=VqZeIKdu79s
3. **Part 3: saving to SQLite** — ▶ https://www.youtube.com/watch?v=wHgDHQGT5XE
4. **Part 4: listing & reading entries** — ▶ https://www.youtube.com/watch?v=KUQNYxH6wiQ
5. **Part 5: edit & delete** — ▶ https://www.youtube.com/watch?v=CsD4BJVeqe8
6. **Part 6: polish & finish** — ▶ https://www.youtube.com/watch?v=sr0u8ecgta4

```dart
// The heart of the diary — one table, full CRUD:
await db.insert('entries', {'title': t, 'body': b, 'created_at': now});
final rows = await db.query('entries', orderBy: 'created_at DESC');
```

**Checkpoint quiz:** what happens to SQLite data when the app restarts? When it's
uninstalled? (Know the difference — it matters in real products.)

## Project 2 — News App (online-first)

7. **Part 1: project & layout** — ▶ https://www.youtube.com/watch?v=K0UC-Imv8uE
8. **Part 2: fetching articles (HTTP)** — ▶ https://www.youtube.com/watch?v=yCjmSz4np6k
9. **Part 3: JSON → models → list** — ▶ https://www.youtube.com/watch?v=ZeuCYZZn2gk
10. **Part 4: images & detail screen** — ▶ https://www.youtube.com/watch?v=ds2vD9WkQKE
11. **Part 5: refinements** — ▶ https://www.youtube.com/watch?v=64nq1PqSgNo
12. **Part 6: complete app wrap-up** — ▶ https://www.youtube.com/watch?v=OepU7ISOsZc

**Checkpoint quiz:** the API is down — what should the user see? (Design the error
and loading states; the videos' happy path is only half the job.)

## Bonus module — 2025 techniques refresher

13. **Dynamic lists (2025)** — ▶ https://www.youtube.com/watch?v=hAf_ph993Ek
14. **Local storage with SQFLITE (2025)** — ▶ https://www.youtube.com/watch?v=w4L3d2LGL1o

## Graduation assignment

**Merge the two skills:** build a "Read Later" news app — fetch live articles (News
App skills) and let the user save favourites into SQLite for offline reading (Diary
skills). One app, both halves of mobile development. Add pull-to-refresh and an
empty state for zero saved articles.

**Quiz ideas:** offline vs online decision scenarios · JSON-to-model matching ·
debugging exercise: "the list shows old data after saving — why?"

---

*Catalog note: this course replaces the earlier "WhatsApp Clone" capstone — those
2021 videos are no longer public on the channel. The chat-app concept lives on as an
extension brief in Course 20 (add FCM messaging).*
