# Course 14 — Android Development Fundamentals (Java)

**Tier 2 · Frameworks & Specialisation · Level: Beginner–Intermediate · Prerequisites: any programming basics**

Native Android still matters: it is what most Ugandan companies maintain, and
understanding it makes you a stronger Flutter developer too. This course rebuilds the
classic Android path — layouts, activities, lists, menus and networking with Retrofit —
from the channel's live classes and one-on-one series.

**What you will learn**

- Android Studio, layouts and common attributes
- The Activity lifecycle (the #1 interview topic)
- RecyclerView lists, menus, and app polish
- Retrofit: load live data from an API

---

## Module 1 — Setup & first screens

1. **Install Android Studio & tools** — ▶ https://www.youtube.com/watch?v=A13lms_DfzQ
2. **Android layouts** — ▶ https://www.youtube.com/watch?v=tu4HtByL9H4
3. **Common Android attributes** — ▶ https://www.youtube.com/watch?v=b3Au9yyCXps
4. **Live session: mobile development, part 1** — ▶ https://www.youtube.com/watch?v=0IBxQpH1jDs
5. **Live session: mobile development, part 2** — ▶ https://www.youtube.com/watch?v=miNkDGXqA0Y

## Module 2 — How Android really works

6. **The Activity lifecycle, part 1** — ▶ https://www.youtube.com/watch?v=ymdV21dAp9w
7. **The Activity lifecycle, part 2** — ▶ https://www.youtube.com/watch?v=JQViSzxo8I4

> Understand `onCreate → onStart → onResume` and why your app loses data on rotation —
> then fix it. This is the lesson employers test.

## Module 3 — Lists & menus

8. **RecyclerView, part 1** — the widget behind every feed.
   ▶ https://www.youtube.com/watch?v=ZMZRRxSCRO8
9. **RecyclerView, part 2** — ▶ https://www.youtube.com/watch?v=N9C0yL8MX1s
10. **Options menu with sub-items** — ▶ https://www.youtube.com/watch?v=IhwLT_a---k
11. **Pop-up menu** — ▶ https://www.youtube.com/watch?v=kuURDZJ-yeo
12. **Remove the action bar (full-screen apps)** — ▶ https://www.youtube.com/watch?v=shaO3ASXAK4

## Module 4 — Networking with Retrofit

13. **Retrofit introduction** — ▶ https://www.youtube.com/watch?v=5CwhQ-ZpYZQ
14. **Dynamic data with Retrofit** — ▶ https://www.youtube.com/watch?v=tTpq3ZyY9_g
15. **Filtering API data** — ▶ https://www.youtube.com/watch?v=EnIzgVe2fmU

```java
public interface NewsApi {
    @GET("articles")
    Call<List<Article>> getArticles();
}
// Retrofit turns this interface into a working HTTP client.
```

## Module 5 — Modern companion (external, hand-picked)

16. **Android development for beginners — full course** — a complete modern pass
    (Kotlin) to see where Android is heading.
    ▶ https://www.youtube.com/watch?v=fis26HvvDII *(freeCodeCamp)*

## Final project

A **news reader app**: fetch articles from a free API with Retrofit, show them in a
RecyclerView with images, open a detail screen on tap, survive screen rotation
correctly.

**Quiz ideas:** lifecycle-order sorting question · XML attribute matching ·
"what happens on rotation?" scenarios · practical graded on the working reader.

**Continue to:** Course 15 (Material UI Challenge ⭐) and Capstone 20 (Firebase E-Commerce ⭐).
