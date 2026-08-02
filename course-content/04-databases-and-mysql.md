# Course 04 — Database Design & MySQL for Beginners

**Tier 1 · Foundations · Level: Beginner · Prerequisites: none (Course 03 helps)**

Every real system — school, shop, hospital — lives on a database. This course teaches
you to *think* in tables first, then to speak SQL confidently. Short lessons, lots of
practice queries.

**What you will learn**

- What databases and tables really are, and how to design them well
- Create, insert, select, update and delete data with SQL
- Filter with WHERE, connect tables with keys, and plan a schema with an ERD

---

## Module 1 — Thinking in tables

1. **Database concepts, part 1** — tables, rows, columns, keys. ▶ https://www.youtube.com/watch?v=dg3Z035iwu4
2. **Database concepts, part 2** — relationships between tables. ▶ https://www.youtube.com/watch?v=5pAiK0bKHpQ
3. **Database concepts, part 3** — putting the ideas together. ▶ https://www.youtube.com/watch?v=IEhVTRQKEfE

## Module 2 — First steps in SQL

4. **SQL tutorial, part 1** — your first queries. ▶ https://www.youtube.com/watch?v=x7IXNnwLJfI
5. **SQL tutorial, part 2** — ▶ https://www.youtube.com/watch?v=9NOFFSEAszI
6. **SQL tutorial, part 3** — ▶ https://www.youtube.com/watch?v=hIbwfxk7sHQ
7. **SQL practice session** — ▶ https://www.youtube.com/watch?v=xr7vHD7UIHo

```sql
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  district VARCHAR(50),
  mark INT
);

INSERT INTO students (name, district, mark)
VALUES ('Amina', 'Kasese', 82);

SELECT name, mark FROM students WHERE mark >= 50 ORDER BY mark DESC;
```

## Module 3 — The queries you will use every day (practice module)

Text lessons with runnable examples — practise each one in phpMyAdmin:

8. **UPDATE and DELETE — carefully!** — always with a WHERE clause.
9. **Sorting and limiting** — `ORDER BY`, `LIMIT` for "top 10" lists.
10. **Counting and grouping** — `COUNT`, `SUM`, `AVG`, `GROUP BY` for reports.
11. **JOIN two tables** — students + districts; the most important skill in SQL.

```sql
SELECT s.name, d.name AS district
FROM students s
JOIN districts d ON d.id = s.district_id;
```

## Module 4 — Designing real databases (ERDs)

12. **Design a News App database** — entities → tables. ▶ https://www.youtube.com/watch?v=WDEtrLvug1c
13. **Design a Music App database** — a second full design. ▶ https://www.youtube.com/watch?v=Gc_vb1iyq3o

## Module 5 — Going deeper (external, hand-picked)

14. **MySQL full course** — a complete second pass to cement everything.
    ▶ https://www.youtube.com/watch?v=ER8oKX5myE0 *(freeCodeCamp)*
15. **SQL full database course** — optional, for those who want mastery.
    ▶ https://www.youtube.com/watch?v=HXV3zeQKqGY *(freeCodeCamp)*

## Final project

Design and build the database for a **school library**: books, members, and loans
(who borrowed what, and when). Deliver the ERD drawing, the CREATE statements, and
five useful queries (e.g. "books currently out", "most active member").

**Quiz ideas:** match query→result · write-the-WHERE-clause exercises · spot the
dangerous query (UPDATE without WHERE) · ERD multiple choice ("what type of
relationship is students→district?").

**Continue to:** Course 10 (Laravel) — migrations will feel easy after this.
