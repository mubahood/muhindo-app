# Course 01 — Introduction to Web Development (HTML, CSS, Bootstrap, PHP & MySQL)

**Tier 1 · Foundations · Level: Absolute beginner · Prerequisites: none — just a computer**

The first course every new student should take. You start with an empty folder and finish
with a working, database-driven website. Everything is explained in plain English, one
small step at a time.

**What you will learn**

- Build web pages with HTML and style them with CSS and Bootstrap
- Write your first PHP code and understand how a server works
- Save and read real data with MySQL
- Build forms that upload files and store submissions in a database

---

## Module 1 — Your first web page

1. **Setting up your tools & your first HTML page** — install a code editor, create `index.html`, open it in a browser.
   ▶ https://www.youtube.com/watch?v=y7mC6h1wPL4
2. **How a website really works** — browser, server, request, response. Simple diagram lesson (no video needed — read + quiz).
3. **Practice:** build a one-page "About Me" site with a heading, a photo and a list.

```html
<!DOCTYPE html>
<html>
  <head><title>About Me</title></head>
  <body>
    <h1>Hello, I am Sarah</h1>
    <p>I am learning web development, step by step.</p>
  </body>
</html>
```

## Module 2 — Bootstrap: beautiful pages, fast

4. **Introduction to Bootstrap** — add Bootstrap with one line and use ready-made styles.
   ▶ https://www.youtube.com/watch?v=d9MfcPVi58U
5. **Images and typography** — responsive images, headings, text utilities.
   ▶ https://www.youtube.com/watch?v=YoXvPlgwppU
6. **Cards and components** — build product-style cards.
   ▶ https://www.youtube.com/watch?v=eQE1Iieog7s
7. **The grid system** — rows and columns; make any layout.
   ▶ https://www.youtube.com/watch?v=Ge3qHN53K0Q

## Module 3 — Hello, PHP

8. **Introduction to PHP** — what PHP is, install XAMPP, run your first script.
   ▶ https://www.youtube.com/watch?v=wxXdZZRGDEU
9. **PHP basics continued** — variables, echo, simple logic.
   ▶ https://www.youtube.com/watch?v=DpmoQHV_ygI
10. **PHP functions, part 1** — write and call your own functions.
    ▶ https://www.youtube.com/watch?v=yuzlg1-5Sbk
11. **PHP functions, part 2** — parameters and return values.
    ▶ https://www.youtube.com/watch?v=ePEiWSF5oH0

## Module 4 — Forms: talking to the server

12. **GET requests** — send data through the URL and read it in PHP.
    ▶ https://www.youtube.com/watch?v=56UdC-YcwYY
13. **POST requests** — send form data safely.
    ▶ https://www.youtube.com/watch?v=HGnFZxwf0ks
14. **File uploading** — let users upload images.
    ▶ https://www.youtube.com/watch?v=YWjR1kYLb4Y

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    echo "Welcome, " . $name . "!";
}
?>
<form method="POST">
  <input name="name" placeholder="Your name">
  <button type="submit">Say hello</button>
</form>
```

## Module 5 — Databases with MySQL

15. **Database introduction & CREATE** — what a database is; create your first table.
    ▶ https://www.youtube.com/watch?v=QJT2WQD_OrY
16. **INSERT and SELECT** — save data and read it back.
    ▶ https://www.youtube.com/watch?v=2xWj1rUgFV8
17. **The WHERE clause, part 1** — find exactly the rows you want.
    ▶ https://www.youtube.com/watch?v=-ny4FQ9QRe8
18. **The WHERE clause, part 2** — combine conditions.
    ▶ https://www.youtube.com/watch?v=-bZcveWnfVY
19. **Designing a database (ERD): News App** — plan tables before you build.
    ▶ https://www.youtube.com/watch?v=WDEtrLvug1c
20. **Designing a database (ERD): Music App** — a second design practice.
    ▶ https://www.youtube.com/watch?v=Gc_vb1iyq3o

## Module 6 — PHP + MySQL together

21. **Insert data from PHP dynamically** — connect your form to the database.
    ▶ https://www.youtube.com/watch?v=fO4PhyPDr0M
22. **Upload files into the database** — store the file, save the path.
    ▶ https://www.youtube.com/watch?v=raq7zQ5CdsQ
23. **Compress images and keep quality** — a pro trick for real projects.
    ▶ https://www.youtube.com/watch?v=WCDtHZCTTbU
24. **Extra: version control with Git & GitHub** — save your work like a professional.
    ▶ https://www.youtube.com/watch?v=RGOj5yH7evk *(freeCodeCamp)*

## Final project

Build a small **News website**: articles stored in MySQL, a Bootstrap homepage listing
them, an "add article" form with image upload. This uses every module above.

**Quiz ideas per module:** HTML tags matching quiz · Bootstrap grid multiple choice ·
PHP output prediction ("what does this code print?") · SQL WHERE clause fill-in-the-blank.
