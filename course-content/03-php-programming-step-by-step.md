# Course 03 — PHP Programming, Step by Step

**Tier 1 · Foundations · Level: Beginner · Prerequisites: Course 01 (or basic HTML)**

PHP powers most of the web — including WordPress and Laravel. This course teaches you
the language itself, slowly and properly, so frameworks make sense later. Every lesson
is short and has one clear goal.

**What you will learn**

- Install a PHP environment and run code on your own computer
- Variables, data types, arrays, operators and conditions — the building blocks
- Write clean functions and make real decisions in code

---

## Module 1 — Getting started

1. **Install XAMPP and run your first PHP file** — ▶ https://www.youtube.com/watch?v=STQHQGci1bA
2. **Hello, World!** — your first real script. ▶ https://www.youtube.com/watch?v=Cciw8piGktA
3. **Variables** — storing values. ▶ https://www.youtube.com/watch?v=CsMhlTwDInY

```php
<?php
$name = "Muhindo";
$students = 25;
echo "Teacher $name has $students students.";
```

## Module 2 — Data types

4. **Data types, part 1** — strings, integers, floats, booleans. ▶ https://www.youtube.com/watch?v=EiGlvZuAWEk
5. **Arrays** — many values in one variable. ▶ https://www.youtube.com/watch?v=PHLHZ78QQj0
6. **Data types, part 2** — deeper practice. ▶ https://www.youtube.com/watch?v=46i8FbboTtE
7. **Math functions** — round, random, power. ▶ https://www.youtube.com/watch?v=NIBHh3NA-Y4
8. **Constants** — values that never change. ▶ https://www.youtube.com/watch?v=-OhlDP5jW_A

## Module 3 — Operators (small lessons, big foundations)

9. **Arithmetic operators** — ▶ https://www.youtube.com/watch?v=LInrysykyTw
10. **Assignment operators** — ▶ https://www.youtube.com/watch?v=NddidZqiif0
11. **Comparison operators** — ▶ https://www.youtube.com/watch?v=3gpBI2hvnQc
12. **Identical comparison (`===`)** — why `==` is not enough. ▶ https://www.youtube.com/watch?v=oQ3hUJSCK-o
13. **Increment & decrement** — ▶ https://www.youtube.com/watch?v=pDTFX1bxfZs
14. **Logical operators (AND)** — ▶ https://www.youtube.com/watch?v=9M24Tqs9zh4
15. **Logical operators (OR)** — ▶ https://www.youtube.com/watch?v=YYEd3hWN0QE
16. **String operators** — joining text. ▶ https://www.youtube.com/watch?v=lXcE9WsKVFw

## Module 4 — Making decisions

17. **The `if` condition** — ▶ https://www.youtube.com/watch?v=WeBbM1x7vdk
18. **`if … else`** — ▶ https://www.youtube.com/watch?v=tuKoLCO4XzA

```php
<?php
$mark = 68;
if ($mark >= 80) {
    echo "Distinction";
} elseif ($mark >= 50) {
    echo "Pass — keep going!";
} else {
    echo "Let's revise together.";
}
```

## Module 5 — Loops, functions & practice (guided practice module)

These topics complete the language basics. Learn them by *doing* — each lesson is a
written exercise with a worked solution (text lessons; no video needed):

19. **Loops: `for`, `while`, `foreach`** — print tables, walk arrays.
20. **Writing your own functions** — parameters, return values, defaults.
21. **Working with dates** — `date()`, timestamps.
22. **Mini-build: a marks-grading script** — combine arrays, loops and conditions.
    ▶ Reference build (music web app in one video): https://www.youtube.com/watch?v=OBqB6jF1oT4

## Final project

A **student report generator**: an array of students and marks → a loop grades everyone
→ output a clean HTML table with pass/fail colors. Uses every concept in this course.

**Quiz ideas:** predict-the-output (operators are perfect for this) · true/false on
`==` vs `===` · fill-in-the-blank loops · one code-writing assignment auto-checked by
expected output.

**Continue to:** Course 04 (MySQL) then Course 19 (Build an Online Shop) to use PHP for real.
