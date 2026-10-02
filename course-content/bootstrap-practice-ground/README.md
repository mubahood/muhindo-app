# Bootstrap practice lesson source

The HTML files in this folder are the written and practical lessons for the
Bootstrap course. The site stores each page as lesson content and shows it in
a sandboxed live preview, so its example code does not change the learning
site around it.

## Sync the lessons

From the website folder, run:

```sh
php artisan courses:sync-bootstrap-practice-ground
```

The command updates the Bootstrap course modules and lesson pages from these
folders. Keep each HTML page's first `<h1>` as its lesson title. Module folder
names set the module names. Re-running the command updates these pages without
changing student progress or video links.

## Upload one page in the admin

Open a lesson, set **Content format** to **HTML lesson (live example)**, choose
the HTML file, and save. The complete page is stored as the lesson content.
The shared Bootstrap files used by the examples are in
`public/bootstrap-practice-ground/`.
