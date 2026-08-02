# Course 08 — Web Application Security Essentials

**Tier 1 · Foundations · Level: Intermediate · Prerequisites: Courses 03–05**

You can build it — now learn to protect it. This course shows you how attackers break
web apps (safely, on your own code) and exactly how to stop them. Short course, huge
career value: every serious employer asks about security.

**What you will learn**

- The most common attacks: SQL injection, XSS, CSRF, broken authentication
- How to write PHP that resists each one
- OWASP: the industry's standard security checklist

---

## Module 1 — Thinking like an attacker (Muhindo's security series)

Follow the full series in order — each session demonstrates one attack and its fix:

▶ Full playlist: https://www.youtube.com/playlist?list=PLOR5hj0X3WPdr_IONZ9Hw2haZUuPeYp3E

1. **Introduction to software security** — why "it works" is not enough.
   ▶ https://www.youtube.com/watch?v=FZzbVTyDiSA
2. **Cross-site scripting (XSS)** — injecting JavaScript into pages, and escaping output.
   ▶ https://www.youtube.com/watch?v=2fkhfeQiYso
3. **Session security** — ▶ https://www.youtube.com/watch?v=Fsr_bCo-p-o
4. **SQL injection** — ▶ https://www.youtube.com/watch?v=CjG1lEwpVeo
5. **Security session 5** — ▶ https://www.youtube.com/watch?v=1JkxzUgqnC8
6. **Security session 6** — ▶ https://www.youtube.com/watch?v=mAHkX514WyA
7. **Security session 7** — ▶ https://www.youtube.com/watch?v=eIEXqnmJIs4

## Module 2 — The fixes, in code (practice module)

Text lessons — every attack from Module 1 gets its defence:

8. **Stop SQL injection: prepared statements.**

```php
// NEVER: "SELECT * FROM users WHERE email = '$email'"
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);          // safe — data can never become SQL
```

9. **Stop XSS: escape everything you output.**

```php
echo htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
```

10. **Stop CSRF: tokens on every form.** One hidden token per session, checked on POST.
11. **Passwords done right** — `password_hash()` and `password_verify()`, never MD5.
12. **File-upload safety** — check type and size, rename files, store outside the web root.

## Module 3 — The professional standard

13. **OWASP API Security Top 10** — the checklist used across the industry.
    ▶ https://www.youtube.com/watch?v=YYe0FdfdgDU *(freeCodeCamp)*

## Final project

**Attack and defend your own shop.** Take your Course-19 (or Course-01) project:
first *break in* — try SQL injection on the login, XSS in a comment. Document what
worked. Then fix every hole using Module 2 and prove the attacks now fail. Submit the
before/after report.

**Quiz ideas:** match attack → defence · spot-the-vulnerable-line code quizzes ·
true/false ("hashing and encryption are the same thing") · scenario questions.
