# Course 19 ⭐ — Build a Complete Online Shop with PHP & MySQL

**Tier 3 · Capstone Systems · Level: Intermediate · Prerequisites: Courses 01, 03, 04 · TOP FEATURED**

Classic e-commerce, built by hand: accounts, product catalogue, image galleries with
zoom, a real shopping cart, shipping details and database-backed orders. Everything
is visible PHP — the best possible way to *understand* e-commerce before using
frameworks that automate it.

**System features:** registration/login/logout with sessions, product management,
product pages with galleries, cart (add, list, save), shipping info, order storage.

▶ Full playlist: https://www.youtube.com/playlist?list=PLOR5hj0X3WPdOWwU7eCCfFcgIkS1WrDYl

---

## Phase A — Accounts & foundations

1. Project introduction — https://www.youtube.com/watch?v=LhOHiKIICsc
2. Project setup walk-through — https://www.youtube.com/watch?v=x21TDyTOYNU
3. Registration form — https://www.youtube.com/watch?v=K3xqLF4vyrY
4. Complete login form — https://www.youtube.com/watch?v=mhS4UwPYFMg
5. Logout with sessions & cookies — https://www.youtube.com/watch?v=unWJbwLW8Fg

```php
// login success:
session_start();
$_SESSION['user_id'] = $user['id'];

// every protected page:
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
```

## Phase B — Products

6. Insert into MySQL dynamically — https://www.youtube.com/watch?v=DA7tSMkhJoQ
7. File upload to the database — https://www.youtube.com/watch?v=raq7zQ5CdsQ
8. Image compression (keep quality) — https://www.youtube.com/watch?v=WCDtHZCTTbU
9. Insert data dynamically, part 2 — https://www.youtube.com/watch?v=fO4PhyPDr0M
10. SELECT: reading products — https://www.youtube.com/watch?v=Y5_clDXz42s
11. Add new product flow — https://www.youtube.com/watch?v=I3YnSR_uhes
12. Products session — https://www.youtube.com/watch?v=wJMzCCekQA4
13. Display products to customers — https://www.youtube.com/watch?v=ZNDHWD1q1uY
14. Single product page — https://www.youtube.com/watch?v=SpN-FWPOjas
15. Image gallery with zoom — https://www.youtube.com/watch?v=bAAen7N3jXg

## Phase C — Cart & orders

16. Shopping cart (add to cart) — https://www.youtube.com/watch?v=lbbLwaeWSJ8
17. Cart session deep-dive — https://www.youtube.com/watch?v=EO8U5_mpW6I
18. List cart items — https://www.youtube.com/watch?v=rjhxk-zY2oY
19. Shipping information — https://www.youtube.com/watch?v=JFrzzTZ8gOw
20. Save the cart to the database — https://www.youtube.com/watch?v=McB4oxo8ZCg
21. Complete build recap (one sitting) — https://www.youtube.com/watch?v=8_PoFnroR0Y

## Graduation assignment

Open your own **one-category shop** (shoes, phones, books — your choice): 15 real
products with compressed images, working cart → shipping → saved order, an "my
orders" page for customers, and an admin order list. Then run the Course 08 attack
checklist against it and fix what you find.

**Milestone quizzes:** session vs cookie true/false · cart-logic prediction
questions · SQL for "top 5 best-selling products" exercise.

**Where to next:** rebuild the same shop in Laravel (Course 10 + 12) and feel the
difference — or go multi-vendor with Course 17.
