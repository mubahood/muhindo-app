# Bootstrap Practice Ground

This is the hands-on work area for **AI Powered - Frontend Development with Bootstrap**. It follows the course module order and uses the same `module.position - BOOTSTRAP - topic` naming as the recordings. Each topic file is independent: it explains one idea, shows its example and code, and gives one short exercise. There is no lesson-to-lesson navigation inside those files.

## Start here

1. Make a copy of this `practice-ground` folder for your own work.
2. Open the copied folder in VS Code.
3. Start Live Server on `index.html` (right-click the file and choose **Open with Live Server**).
4. Choose a module, then open the activity with the same number and title as the lesson.
5. Edit the HTML or shared CSS, save, and observe the page refresh. Test at 360, 768 and 1280 pixels wide when the topic involves responsive layout.

Opening files through Live Server keeps paths and browser behavior consistent. The site does not need a server-side language, build tool, or internet connection to run.

## Folder map

```text
practice-ground/
├── index.html                 module and activity directory
├── README.md                  these instructions
├── css/
│   ├── practice.css           small reading and code styles
│   └── vendor/
│       ├── bootstrap.min.css  Bootstrap 5.3.8
│       ├── bootstrap-icons.min.css  Bootstrap Icons 1.11.3
│       └── fonts/             icon font files
├── js/
│   ├── practice.js            small helpers for Bootstrap interactive examples
│   └── vendor/
│       └── bootstrap.bundle.min.js  Bootstrap JavaScript plus Popper
├── images/                    reused Marigold course images
├── 00. Start here/
├── 01. ... through 10. .../   one HTML page per video topic, plus module intro and quiz
└── 11. Final project/         project brief and course wrap-up activities
```

Bootstrap and Bootstrap Icons are included locally. The versioned vendor files are unmodified distribution files. Bootstrap JavaScript is the bundle, so dropdowns, tooltips, popovers and other interactive components have the dependency they need. No jQuery is used.

## Keep the work simple

- Each topic page explains one idea, shows its live result and source, and gives one short task.
- Lesson pages use plain document flow. They have no decorative cards, frames, breadcrumbs or next and previous buttons.
- Shared CSS, JavaScript and images live at the top level so students can reuse them across pages.
- Examples use Marigold Stores where it helps explain the concept. A topic can also be practised on its own.
- Module quiz pages include the answer notes in a collapsed section for self-checking.
- `demo-files/marigold-shop/` beside this folder is the larger completed storefront reference. It is not required to run this practice ground.

## Rebuild the lesson pages

The lesson content comes from `../generators/outline_content.js`. From the course folder, run:

```sh
node generators/build_practice_ground.js
```

This rebuilds the generated lesson pages and `index.html`. If students want to preserve their edits, they should work in a copied practice-ground folder before regenerating.

## Attribution and versions

- Bootstrap 5.3.8, MIT license: <https://github.com/twbs/bootstrap/blob/v5.3.8/LICENSE>
- Bootstrap Icons 1.11.3, MIT license: <https://github.com/twbs/icons/blob/v1.11.3/LICENSE>
- Versioned files are distributed locally so class activities work offline.
