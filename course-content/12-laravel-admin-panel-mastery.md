# Course 12 ⭐ — Laravel Admin Panel Mastery

**Tier 2 · Frameworks & Specialisation · Level: Intermediate · Prerequisites: Course 10 · TOP FEATURED**

Every serious system needs a back office: users, permissions, data grids, forms,
dashboards. This course masters **laravel-admin**, the dashboard toolkit used to build
InvetoTrack and MarketLink — so one developer can ship what normally takes a team.

**What you will learn**

- Generate complete admin CRUD screens in minutes
- Powerful grids: filters, sorting, inline editing, exports
- Forms with relationships and validation
- Roles, permissions, and a real hotel-management mini-project

---

## Module 1 — Setup & first screens

1. **Create a new laravel-admin project** — ▶ https://www.youtube.com/watch?v=rmjNxqUozZw
2. **The ultimate guide to laravel-admin** — concepts overview.
   ▶ https://www.youtube.com/watch?v=KE7FU4JXKow

## Module 2 — Forms that build themselves

3. **Mastering form fields** — every input type, with tips.
   ▶ https://www.youtube.com/watch?v=6vZssRTq4YY
4. **Forms with model relationships** — select a client, attach a district.
   ▶ https://www.youtube.com/watch?v=HxwzGA6Uf-U
5. **Validation & field linkage** — smart forms that react to input.
   ▶ https://www.youtube.com/watch?v=QakPg1Z-KjQ

```php
$form->text('name', 'Student name')->rules('required|min:3');
$form->select('district_id', 'District')
     ->options(District::pluck('name', 'id'));
$form->image('photo')->uniqueName();
```

## Module 3 — Grids: your data, powerful

6. **Grid basics: powerful tables** — ▶ https://www.youtube.com/watch?v=wSD7aKiVQVE
7. **Grid basic usage, continued** — ▶ https://www.youtube.com/watch?v=frT35DlPylA
8. **Grid filters** — search any column. ▶ https://www.youtube.com/watch?v=-8YLEk-ytU8
9. **Inline editing** — fix data without opening forms.
   ▶ https://www.youtube.com/watch?v=1tpjTbqbaYs
10. **Grid actions & more** — ▶ https://www.youtube.com/watch?v=OnUXTGxcsvM
11. **Quick create** — add rows right from the grid.
    ▶ https://www.youtube.com/watch?v=_K56f0DZnbY

## Module 4 — Access control

12. **Permissions & user roles** — admins, managers, viewers: each sees only their
    world. ▶ https://www.youtube.com/watch?v=ypHVg_--YRQ

## Module 5 — Mini-project: hotel management

13. **Hotel management project setup** — ▶ https://www.youtube.com/watch?v=SX_T2y5Ar5s
14. **Room reservation, part 1** — ▶ https://www.youtube.com/watch?v=f_btCbT4uow
15. **Room reservation, part 2** — ▶ https://www.youtube.com/watch?v=qqlyQuOhuJk

▶ Full playlist (all sessions + extras): https://www.youtube.com/playlist?list=PLOR5hj0X3WPeJ8cl58w59_XMZ9m0ceDzG

## Final project

An **admin panel for a school**: students, classes, teachers (relationships in
forms), grid filters by class, inline-edit marks, role-restricted access (head
teacher vs teacher), and one dashboard chart. Ship it in a week — that's the point
of this toolkit.

**Quiz ideas:** match grid feature → code line · form-field API fill-ins ·
scenario: "the accountant should see invoices but not edit users — configure it".

**Continue to:** Capstone 16 (InvetoTrack ⭐) — it builds directly on this course.
