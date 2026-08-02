# Course 10 — Laravel Essentials (Routes to Real Apps)

**Tier 2 · Frameworks & Specialisation · Level: Intermediate · Prerequisites: Courses 03, 04, 06**

Laravel is the most popular PHP framework in the world — and the engine behind the
capstone systems later in this catalog (InvetoTrack, MarketLink). This course teaches
the core Laravel skills properly: routing, Blade, migrations, models, auth and file
uploads — finished with a real news application.

**What you will learn**

- Set up Laravel projects and understand the folder structure
- Routes, controllers and Blade layouts
- Migrations and Eloquent models (with relationships)
- Authentication, file uploads, and deploying with Git/GitHub

---

## Module 1 — Setup & first pages

1. **Introduction to PHP Laravel** — ▶ https://www.youtube.com/watch?v=4bfefZ7LBwo
2. **Laravel + Git & GitHub** — version control from day one.
   ▶ https://www.youtube.com/watch?v=jZFl9QUJ9lU
3. **Routes, controllers and views** — ▶ https://www.youtube.com/watch?v=zDNF73Fdb5U

```php
// routes/web.php
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
```

## Module 2 — Blade templating

4. **Blade basics** — echoing data, loops, conditions in templates.
   ▶ https://www.youtube.com/watch?v=VZbPLK6zzcM
5. **Blade layouts: master pages** — one layout, many pages.
   ▶ https://www.youtube.com/watch?v=viS3kJwi24s
6. **Project layout best practices** — ▶ https://www.youtube.com/watch?v=2p2ogn0z18s

## Module 3 — Database: migrations & models

7. **Mastering migrations** — build your schema in code.
   ▶ https://www.youtube.com/watch?v=Flos2vw3GCE
8. **Laravel models: comprehensive guide** — ▶ https://www.youtube.com/watch?v=GmqZwozkWG0
9. **Models step by step (part 2)** — ▶ https://www.youtube.com/watch?v=djviWnC7TUM
10. **Model relationships** — hasMany, belongsTo: the heart of Eloquent.
    ▶ https://www.youtube.com/watch?v=DTTCSj7UMYw
11. **Retrieving records & display** — ▶ https://www.youtube.com/watch?v=QqhiVlC0WfU

```php
class Article extends Model
{
    public function author()   { return $this->belongsTo(User::class); }
    public function comments() { return $this->hasMany(Comment::class); }
}

$latest = Article::with('author')->latest()->take(5)->get();
```

## Module 4 — Authentication & uploads

12. **Build a secure login system** — ▶ https://www.youtube.com/watch?v=mEJI-BRNBz0
13. **Authentication tutorial (part 2)** — ▶ https://www.youtube.com/watch?v=UlJ16TEI6c4
14. **Laravel Breeze** — the official starter kit, explained.
    ▶ https://www.youtube.com/watch?v=APs8QwFxZBo *(Code With Dary)*
15. **Form uploads: photos & files** — ▶ https://www.youtube.com/watch?v=MEIpYhzq3aQ

## Module 5 — Ship it

16. **BUILD: a news application, step by step** — the course's guided project.
    ▶ https://www.youtube.com/watch?v=yk1gR8R9IhM
17. **Deploy with GitHub: local → live** — ▶ https://www.youtube.com/watch?v=fCobgy9w_R0
18. **Advanced sessions (live-class series)** — routes deep-dive and beyond.
    ▶ https://www.youtube.com/watch?v=__fbo5akh5Y · ▶ https://www.youtube.com/watch?v=06nVw_7MhoY ·
    ▶ https://www.youtube.com/watch?v=uJCXGJU3ctY · ▶ https://www.youtube.com/watch?v=szcZo69UHbQ

## Final project

A **campus noticeboard**: authentication (Breeze), notices belong to users, image
upload per notice, public listing with pagination, deployed to GitHub. Every module
above is used at least once.

**Quiz ideas:** route → controller → view ordering · migration syntax fill-ins ·
relationship matching (hasMany vs belongsTo) · code review question: "find the N+1 query".

**Continue to:** Course 12 (Laravel Admin Panel Mastery ⭐), then capstones 16–17.
