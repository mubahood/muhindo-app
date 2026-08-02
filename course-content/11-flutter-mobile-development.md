# Course 11 ⭐ — Flutter Mobile App Development, from Zero

**Tier 2 · Frameworks & Specialisation · Level: Beginner–Intermediate · Prerequisites: any programming basics (Course 03 is enough) · TOP FEATURED**

Build real Android & iOS apps from one codebase. This course merges the best of the
university lecture series (taught at Makerere), the Flutter Bootcamp, and the fresh
2025 update — reorganised into one clean path from "what is Dart?" to apps that talk
to the internet and store data offline.

**What you will learn**

- Dart: the language behind Flutter (with OOP)
- Widgets, layouts, navigation and forms
- JSON + HTTP: connect your app to any API
- SQLite offline storage — apps that work without internet

---

## Module 1 — Why Flutter, and hello Dart

1. **The best solution for mobile development** — why cross-platform, why Flutter.
   ▶ https://www.youtube.com/watch?v=DzuNwnkOO8g
2. **Dart: a new programming language** — ▶ https://www.youtube.com/watch?v=7K1oyKkiZwQ
3. **Git & GitHub, well explained** — save your work properly.
   ▶ https://www.youtube.com/watch?v=QVw0ZfY8Drw
4. **Dart, part 2** — ▶ https://www.youtube.com/watch?v=-d8ilFOjSBY
5. **Dart OOP, part 1** — classes and objects. ▶ https://www.youtube.com/watch?v=oBo_9eDw_Yc
6. **Dart OOP, part 2** — ▶ https://www.youtube.com/watch?v=O4lDreRrukw
7. *Optional deep-dive:* **Dart full course** — ▶ https://www.youtube.com/watch?v=Ej_Pcr4uC2Q *(freeCodeCamp)*

```dart
class Student {
  final String name;
  int mark;
  Student(this.name, this.mark);
  bool get passed => mark >= 50;
}

void main() {
  final s = Student('Amina', 82);
  print('${s.name}: ${s.passed ? "PASS" : "RETRY"}');
}
```

## Module 2 — First app & environment

8. **Setting up the Flutter environment** — ▶ https://www.youtube.com/watch?v=EDEQ-1Pl4YQ
9. **Create your first Flutter mobile app** — ▶ https://www.youtube.com/watch?v=k5fcXJU5CUI
10. **Introduction (2025 refresher)** — the newest setup walk-through.
    ▶ https://www.youtube.com/watch?v=doJDig5J57U
11. **Stateless vs Stateful widgets** — the one idea everything builds on.
    ▶ https://www.youtube.com/watch?v=WQBqMHpeDRg

## Module 3 — Layouts & navigation

12. **Rows, containers & layouts** — ▶ https://www.youtube.com/watch?v=n4ZqdYn3u4g
13. **Columns & rows (2025)** — ▶ https://www.youtube.com/watch?v=SuxkUbq1PF4
14. **Text styling & container decoration** — ▶ https://www.youtube.com/watch?v=5txGtFiQ2HM
15. **Columns, rows, flex & stack** — ▶ https://www.youtube.com/watch?v=0E25EvlSkFs
16. **Navigation, ListView & ListTile** — ▶ https://www.youtube.com/watch?v=8LmjuqM-fWo
17. **Navigation & click listeners (lecture)** — ▶ https://www.youtube.com/watch?v=X252E7AVe0I
18. **Dynamic lists (2025)** — ▶ https://www.youtube.com/watch?v=hAf_ph993Ek

▶ Full lecture playlist (backup for any lesson): https://www.youtube.com/playlist?list=PLOR5hj0X3WPcxrB6m8zrIkcfg_z74Ebbl

## Module 4 — Forms & user input

19. **Introduction to packages** — pub.dev, adding dependencies.
    ▶ https://www.youtube.com/watch?v=mM1JDy7n2O0
20. **Form Builder: fields & text inputs** — ▶ https://www.youtube.com/watch?v=2CuEqfeTaTA
21. **Form field decoration, checkboxes & more** — ▶ https://www.youtube.com/watch?v=yRPh-gdxW8o
22. **Form validations** — never trust empty input. ▶ https://www.youtube.com/watch?v=OsF2gTN0vlk
23. **Form builder (lecture pass 1)** — ▶ https://www.youtube.com/watch?v=NcxxE3AxHFA
24. **Form builder (lecture pass 2)** — ▶ https://www.youtube.com/watch?v=6i-npdKcgio

## Module 5 — Data: JSON, HTTP & models

25. **JSON explained** — ▶ https://www.youtube.com/watch?v=x7cPa4RQ-G8 *(2025)*
26. **JSON data format (lecture)** — ▶ https://www.youtube.com/watch?v=pA8ID0Gtqpo
27. **HTTP requests** — call a live API. ▶ https://www.youtube.com/watch?v=pUhkQmC2PyE
28. **Flutter HTTP connection** — ▶ https://www.youtube.com/watch?v=diWp11uh5Xw
29. **Models & widgets: structure your data** — ▶ https://www.youtube.com/watch?v=JZd9LKXSoaM
30. **Dynamic lists from data** — ▶ https://www.youtube.com/watch?v=ntSIjW6w1Ew
31. **File upload from Flutter** — ▶ https://www.youtube.com/watch?v=Z9HdhOEtv4Q

## Module 6 — Offline storage (SQLite)

32. **Introduction to sqflite** — ▶ https://www.youtube.com/watch?v=wNpiaMBSkFM
33. **Create table, insert & retrieve** — ▶ https://www.youtube.com/watch?v=FO8aDfAtyhA
34. **Offline database CRUD** — ▶ https://www.youtube.com/watch?v=LS2EZ31tfX8
35. **Local storage with SQFLITE (2025)** — ▶ https://www.youtube.com/watch?v=w4L3d2LGL1o
36. **SQLite lectures (three-part deep dive)** —
    ▶ https://www.youtube.com/watch?v=0ourMROIhUU · ▶ https://www.youtube.com/watch?v=JzW6uiZjn1U ·
    ▶ https://www.youtube.com/watch?v=m8-RjszHadQ

## Final project

Your own **Expense Tracker**: form to add an expense (validated), list with dynamic
totals, data stored in SQLite so it survives restarts, and a settings screen. Optional
bonus: sync to a free API with HTTP.

*Optional companion:* **Flutter 37-hour full course** — ▶ https://www.youtube.com/watch?v=VPvVD8t02U8 *(freeCodeCamp)*

**Quiz ideas:** widget tree ordering · stateless vs stateful scenarios · JSON→model
matching · practical graded on the working tracker.

**Continue to:** Course 13 (Mastering Flutter UI ⭐) and Course 21 (Mini-Projects), then capstone 16.
