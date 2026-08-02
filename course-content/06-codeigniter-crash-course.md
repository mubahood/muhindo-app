# Course 06 — CodeIgniter Crash Course (Your First PHP Framework)

**Tier 1 · Foundations · Level: Beginner–Intermediate · Prerequisites: Courses 03 & 04**

Before jumping to Laravel, it helps to meet a *small* framework first. CodeIgniter is
light and easy to read, so you can finally see what MVC (Model–View–Controller) means
in practice — the idea behind every modern framework.

**What you will learn**

- What a framework is and why developers use them
- MVC: where your code goes and why
- Routes, controllers, views, database access — and a full CRUD app

---

## Module 1 — Meet the framework

1. **CodeIgniter explained step by step** — install and first pages.
   ▶ https://www.youtube.com/watch?v=C29xRTbkdnw
2. **Revision & core concepts** — MVC in plain words.
   ▶ https://www.youtube.com/watch?v=JJZJRDIQFxs

> **MVC in one breath:** the *Model* talks to the database, the *View* is the HTML the
> user sees, the *Controller* is the traffic officer between them.

## Module 2 — Working with data

3. **Databases and URLs** — connect MySQL, understand clean URLs and routing.
   ▶ https://www.youtube.com/watch?v=QUNMzZYBsj8
4. **Build a CRUD app** — create, read, update, delete records end-to-end.
   ▶ https://www.youtube.com/watch?v=CfMzTvGZ14k
5. **PHP + framework practice session** — ▶ https://www.youtube.com/watch?v=s6iii_pvRUE

```php
// app/Controllers/Students.php  (CodeIgniter 4)
class Students extends BaseController
{
    public function index()
    {
        $model = new \App\Models\StudentModel();
        return view('students/index', ['students' => $model->findAll()]);
    }
}
```

## Module 3 — Modern CodeIgniter 4 (external, hand-picked)

6. **CodeIgniter 4 crash course** — the same ideas in the current version.
   ▶ https://www.youtube.com/watch?v=a92xiP99mBM *(DevLap, 2025)*

## Final project

Rebuild your Course-03 **student report generator** inside CodeIgniter: a `students`
table, a controller with `index/create/edit/delete`, and Bootstrap views. Same app —
but now you'll feel why frameworks keep code organised.

**Quiz ideas:** "which folder does this file belong in?" (M, V or C) · order the
request flow (URL → route → controller → model → view) · short practical: add a
"deactivate student" action to the CRUD app.

**Continue to:** Course 10 (Laravel Essentials) — everything here transfers directly.
