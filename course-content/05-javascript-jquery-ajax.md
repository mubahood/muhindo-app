# Course 05 — JavaScript, jQuery & AJAX Essentials

**Tier 1 · Foundations · Level: Beginner · Prerequisites: Course 01**

HTML builds the page; JavaScript makes it *alive*. You will learn the JavaScript
language first, then jQuery (still everywhere in real projects, including Bootstrap-era
sites), then AJAX — loading data without refreshing the page.

**What you will learn**

- JavaScript basics: variables, functions, events, the DOM
- jQuery selectors, effects and event handling
- AJAX: talk to a PHP backend without reloading the page

---

## Module 1 — JavaScript, the language (external foundation, hand-picked)

1. **JavaScript for beginners — first steps** — a calm, clear introduction.
   ▶ https://www.youtube.com/watch?v=W6NZfCO5SIk *(Programming with Mosh)*
2. **JavaScript full course** — keep this as your reference companion while you
   progress through the modules below.
   ▶ https://www.youtube.com/watch?v=PkZNo7MFNFg *(freeCodeCamp)*

```html
<button id="greet">Click me</button>
<script>
  document.getElementById('greet').addEventListener('click', function () {
    alert('Hello from JavaScript!');
  });
</script>
```

## Module 2 — jQuery from zero (Muhindo live classes)

3. **jQuery introduction, part 1** — what jQuery is, adding it to a page.
   ▶ https://www.youtube.com/watch?v=6LwsYZnO7DI
4. **jQuery, part 2** — selectors: find anything on the page.
   ▶ https://www.youtube.com/watch?v=VacvJA-8Yl4
5. **jQuery, part 3** — events and effects (click, hide, show, fade).
   ▶ https://www.youtube.com/watch?v=4_0yGUfSNlk
6. **jQuery, part 4** — forms and values.
   ▶ https://www.youtube.com/watch?v=fKhluq8owtM
7. **jQuery, part 5** — putting it together.
   ▶ https://www.youtube.com/watch?v=4LDuKdBKHJ0

```javascript
$('#search').on('keyup', function () {
  const term = $(this).val();
  $('.student-row').hide()
    .filter(':contains(' + term + ')').show();
});
```

## Module 3 — AJAX: the magic behind modern apps (practice module)

Text lessons + guided code (build on your Course 03/04 PHP + MySQL skills):

8. **What AJAX is** — request data in the background; JSON explained simply.
9. **`$.get` and `$.post`** — fetch a PHP page, show the result without reload.
10. **Build: live search** — type a name, results appear instantly from MySQL.
11. **Build: form submit without refresh** — save a comment, show a success toast.

```javascript
$.post('save_comment.php', { body: $('#comment').val() }, function (res) {
  $('#status').text('Saved!').fadeIn().delay(1500).fadeOut();
});
```

## Final project

Add life to your Course-01 News website: live search of articles, "load more" button
that fetches the next 5 articles with AJAX, and a comment form that saves without a
page reload.

**Quiz ideas:** selector matching (`$('.x')` vs `$('#x')`) · predict-the-effect ·
short answer: "why does AJAX make apps feel faster?" · practical graded by working demo.

**Continue to:** Course 10 (Laravel) or Course 19 (Online Shop) — both use these skills.
