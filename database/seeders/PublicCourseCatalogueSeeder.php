<?php

namespace Database\Seeders;

use App\Enums\ContentFormat;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PUBLIC_SITE_PLAN.md §2.5 — a real-looking public catalogue to build and review the
 * e-Learning pages against, distinct from `DemoCourseSeeder` (which is explicitly
 * dev-scoped: one free course titled "(Demo)"). Every course here is something
 * Muhindo actually teaches on the "Learn It With Muhindo" channel — web development,
 * mobile development, databases, tooling — spanning free/paid, every level, and
 * several categories so §2.2's filters have real data to filter.
 *
 * Every lesson carries real, topic-accurate written content (not a one-line
 * placeholder) so the lesson player looks like a real course when reviewed —
 * no video is attached (no real video file/URL exists to point at, and inventing
 * one would be a broken embed), so the written lesson body is deliberately the
 * substantial part of each lesson here.
 *
 * Opt-in only (`--class=PublicCourseCatalogueSeeder`), not wired into
 * `DatabaseSeeder`'s default chain. Safe to re-run — every write is an upsert keyed
 * on the course slug.
 */
class PublicCourseCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'super_admin')->first() ?? User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        foreach ($this->courses() as $definition) {
            $this->seedCourse($owner, $definition);
        }

        $this->command->info('PublicCourseCatalogueSeeder: '.count($this->courses()).' courses seeded.');
    }

    /** @return array<int,array<string,mixed>> */
    private function courses(): array
    {
        return [
            [
                'title' => 'Web Development Foundations',
                'cover' => 'web-development.png',
                'tagline' => 'HTML, CSS and JavaScript from zero — build and publish your first real web page.',
                'description' => 'A practical start for anyone who has never written a line of code. You will build a real, working website from scratch — no prior experience needed.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 0,
                'outcomes' => ['Structure a web page with semantic HTML', 'Style pages with modern CSS (flexbox, grid)', 'Add interactivity with JavaScript', 'Publish a site for free on GitHub Pages'],
                'requirements' => ['A computer with internet access', 'No prior programming experience needed'],
                'modules' => [
                    ['title' => 'Getting Started', 'lessons' => [
                        [
                            'title' => 'How the web works', 'minutes' => 12, 'preview' => true,
                            'content' => "A web page starts as a request: your browser asks a server for a file, the server sends back HTML, and the browser turns that HTML into what you see on screen. Understanding this loop — request, response, render — makes everything else in this course click into place.\n\nIn this lesson we cover:\n\n- What happens when you type a URL and hit enter\n- The difference between a browser, a server, and a domain name\n- HTML, CSS and JavaScript — what each one is actually responsible for\n- Why \"view source\" is one of the best learning tools you have\n\nBy the end, you will be able to explain, in plain language, what happens between clicking a link and seeing a page appear — the mental model everything else in this course builds on.",
                        ],
                        [
                            'title' => 'Setting up your code editor', 'minutes' => 8,
                            'content' => "Before writing any code, you need a proper workspace. We install VS Code (free, and what most working developers use), set up a project folder, and install two extensions that will save you hours: Live Server (auto-refreshes your browser as you save) and Prettier (formats your code automatically so it always looks clean).\n\nWe also cover:\n\n- Opening a folder as a project, not just individual files\n- Using the built-in terminal instead of switching windows\n- A few keyboard shortcuts worth learning on day one\n\nFive minutes of setup here saves you from fighting your tools for the rest of the course.",
                        ],
                    ]],
                    ['title' => 'HTML & CSS', 'lessons' => [
                        [
                            'title' => 'Your first HTML page', 'minutes' => 18,
                            'content' => "Every web page is built from HTML elements — tags that describe what a piece of content *is*, not how it looks. In this lesson you write your first real page from an empty file: a heading, a paragraph, a list, a link and an image, wired together with proper structure.\n\n```html\n<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"UTF-8\">\n  <title>My First Page</title>\n</head>\n<body>\n  <h1>Hello, world</h1>\n  <p>This is my first real web page.</p>\n</body>\n</html>\n```\n\nWe walk through why the `<!DOCTYPE>`, `<head>` and `<body>` all matter, and why semantic tags like `<nav>`, `<main>` and `<footer>` are better choices than a page full of generic `<div>`s — both for accessibility and for how search engines read your page.",
                        ],
                        [
                            'title' => 'Styling with CSS', 'minutes' => 25,
                            'content' => "HTML gives a page structure; CSS gives it a look. This lesson covers the CSS you'll actually use every day: selectors, the box model (margin, border, padding, content), colors, typography, and how the cascade decides which rule wins when two styles conflict.\n\n```css\n.card {\n  border: 1px solid #e2e2e2;\n  padding: 16px;\n  border-radius: 8px;\n  font-family: sans-serif;\n}\n```\n\nKey ideas covered:\n\n- Class vs. id selectors, and why classes are almost always the right choice\n- The box model — the single most useful diagram in all of CSS\n- Specificity: why a style sometimes \"doesn't work\" even though it looks correct\n\nBy the end you'll be able to take a bare HTML page and make it look intentional.",
                        ],
                        [
                            'title' => 'Layouts with flexbox and grid', 'minutes' => 30,
                            'content' => "Modern page layouts are built with two CSS tools: **flexbox** for one-dimensional layouts (a row or a column of items) and **grid** for two-dimensional layouts (rows and columns together). This lesson builds a real page header with flexbox and a real card grid with CSS grid, so you see exactly when to reach for each one.\n\n```css\n.nav { display: flex; justify-content: space-between; align-items: center; }\n.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }\n```\n\nWe also cover responsive layout basics — how the same CSS can reflow from three columns on desktop to one column on a phone, without writing a single media query for the simple cases.",
                        ],
                    ]],
                    ['title' => 'JavaScript basics', 'lessons' => [
                        [
                            'title' => 'Variables, functions and events', 'minutes' => 28,
                            'content' => "JavaScript is what makes a page interactive instead of just a static document. This lesson covers the core building blocks: variables (`let` and `const`), functions, and events — the mechanism that lets your code react to a click, a keypress, or a form submission.\n\n```js\nconst button = document.querySelector('#save');\nbutton.addEventListener('click', () => {\n  console.log('Saved!');\n});\n```\n\nWe build a small interactive counter and a show/hide toggle together, step by step, so the concepts are attached to something you actually built, not just definitions on a slide.",
                        ],
                        [
                            'title' => 'Publishing your site', 'minutes' => 15,
                            'content' => "A website nobody can visit isn't finished. In this closing lesson we take the page you built across this course and publish it for free using GitHub Pages — a real, working URL you can share with anyone.\n\nSteps covered:\n\n1. Creating a GitHub account and a new repository\n2. Pushing your project files with Git (a quick preview of the full **Git & GitHub for Developers** course, if you want to go deeper)\n3. Enabling GitHub Pages and finding your live URL\n4. What to check before sharing a link — broken paths, missing images, mobile view\n\nYou'll finish this course with a real, live, working website you built yourself.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Laravel From Scratch',
                'cover' => 'web-development.png',
                'tagline' => 'Build real, database-backed web applications with Laravel and PHP.',
                'description' => 'A project-based course covering routing, Eloquent, Blade, authentication and deployment — everything you need to build and ship a real Laravel application.',
                'level' => 'intermediate', 'category' => 'Web Development', 'price' => 150000,
                'outcomes' => ['Build CRUD applications with Eloquent and Blade', 'Handle authentication and authorization', 'Write and run automated tests', 'Deploy a Laravel app to a live server'],
                'requirements' => ['Basic PHP knowledge', 'Basic HTML/CSS'],
                'modules' => [
                    ['title' => 'Laravel essentials', 'lessons' => [
                        [
                            'title' => 'Routing and controllers', 'minutes' => 20, 'preview' => true,
                            'content' => "Every request to a Laravel app starts in `routes/web.php` — a route matches a URL to a piece of code that handles it. This lesson covers defining routes, passing route parameters, and moving that logic out of the routes file into a proper controller once it grows past a line or two.\n\n```php\nRoute::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');\n```\n\nWe cover route model binding (Laravel resolving `{course}` straight into a `Course` model for you), named routes, and why controllers exist — keeping `routes/web.php` as a readable map of your application rather than a dumping ground for logic.",
                        ],
                        [
                            'title' => 'Blade templates', 'minutes' => 22,
                            'content' => "Blade is Laravel's templating engine — plain HTML with a small set of directives for loops, conditionals, and reusable layouts. This lesson builds a real layout with `@extends`/`@section`, loops data into a page with `@foreach`, and introduces components for pieces of UI you'll reuse across pages (a card, a button, an alert).\n\n```blade\n@foreach(\$courses as \$course)\n  <div class=\"card\">{{ \$course->title }}</div>\n@endforeach\n```\n\nWe also cover Blade's automatic HTML escaping (`{{ }}`) versus raw output (`{!! !!}`) — and why you almost always want the former, for security.",
                        ],
                        [
                            'title' => 'Eloquent models and migrations', 'minutes' => 35,
                            'content' => "Eloquent is Laravel's ORM — it lets you work with database rows as PHP objects instead of writing raw SQL everywhere. This lesson covers migrations (version-controlled database schema changes), models, and Eloquent relationships (`hasMany`, `belongsTo`) using a real courses-and-lessons schema as the running example.\n\n```php\nclass Course extends Model {\n    public function lessons(): HasMany {\n        return \$this->hasMany(Lesson::class);\n    }\n}\n```\n\nBy the end you'll be able to design a small relational schema, write the migrations for it, and query it naturally through Eloquent relationships instead of manual joins.",
                        ],
                    ]],
                    ['title' => 'Building a real app', 'lessons' => [
                        [
                            'title' => 'CRUD from scratch', 'minutes' => 40,
                            'content' => "CRUD — Create, Read, Update, Delete — is the backbone of almost every web application. This lesson builds a complete CRUD flow for a real resource: a form to create it, a list page to read it, an edit form to update it, and a delete action, wired through a resource controller (`Route::resource`).\n\nWe pay close attention to the details that separate a working demo from production-ready code: redirecting after a successful save (never leave a form re-submittable on refresh), flashing a success message, and confirming before a destructive delete.",
                        ],
                        [
                            'title' => 'Authentication with Breeze', 'minutes' => 30,
                            'content' => "Laravel Breeze gives you a complete, secure login/register/password-reset flow out of the box. This lesson installs Breeze into the app we've been building, walks through what it actually generates (so it never feels like magic), and covers protecting routes with the `auth` middleware so only signed-in users can reach certain pages.\n\n```php\nRoute::middleware('auth')->group(function () {\n    Route::get('/dashboard', DashboardController::class);\n});\n```\n\nWe also cover the difference between authentication (who are you) and authorization (what are you allowed to do) — setting up the next lesson's validation and error-handling work on solid ground.",
                        ],
                        [
                            'title' => 'Form validation and error handling', 'minutes' => 25,
                            'content' => "Never trust input from the browser — validation is what keeps bad data out of your database. This lesson covers Laravel's `\$request->validate()`, writing clear validation rules, and displaying field-level errors back to the user without losing what they already typed.\n\n```php\n\$data = \$request->validate([\n    'title' => 'required|string|max:200',\n    'email' => 'required|email',\n]);\n```\n\nWe also cover graceful failure — what a user sees when something goes wrong that isn't their fault (a failed payment, an unreachable service) — so the app never shows a raw error page to a real visitor.",
                        ],
                    ]],
                    ['title' => 'Shipping it', 'lessons' => [
                        [
                            'title' => 'Testing with PHPUnit', 'minutes' => 28,
                            'content' => "A feature you haven't tested is a feature you're not sure works. This lesson introduces PHPUnit through Laravel's testing helpers — spinning up a test database, hitting a real route, and asserting on the response.\n\n```php\npublic function test_a_guest_can_view_the_course_list(): void\n{\n    \$this->get('/courses')->assertOk();\n}\n```\n\nWe write tests for both the happy path (a valid form submission succeeds) and the abuse path (an invalid submission is rejected) — the same discipline real production Laravel teams use before merging any change.",
                        ],
                        [
                            'title' => 'Deploying to a live server', 'minutes' => 24,
                            'content' => "A Laravel app only matters once real people can use it. This closing lesson covers what changes between your local machine and a live server: environment variables, running migrations safely against production data, caching config/routes for performance, and the handful of checks worth running before every deploy.\n\nWe walk through a real deployment end to end — pushing code, running `php artisan migrate --force`, and confirming the live site actually works — the same checklist used to ship this platform itself.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Flutter Mobile App Development',
                'cover' => 'mobile-apps.png',
                'tagline' => 'Build native Android and iOS apps from one Dart codebase.',
                'description' => 'Learn Flutter and Dart by building a real, working mobile app — widgets, navigation, state management and connecting to a live API.',
                'level' => 'intermediate', 'category' => 'Mobile Development', 'price' => 200000,
                'outcomes' => ['Build responsive UIs with Flutter widgets', 'Manage app state', 'Navigate between screens', 'Consume a REST API from a mobile app'],
                'requirements' => ['Basic programming knowledge (any language)', 'A computer able to run Android Studio'],
                'modules' => [
                    ['title' => 'Flutter basics', 'lessons' => [
                        [
                            'title' => 'Setting up Flutter', 'minutes' => 15, 'preview' => true,
                            'content' => "Flutter lets you write one Dart codebase that compiles to real native Android and iOS apps — not a web view wrapped in a shell. This lesson installs the Flutter SDK, sets up an emulator, and runs the default counter app so your environment is confirmed working before we write a single line of our own.\n\nWe also cover the project structure Flutter generates for you — `lib/main.dart`, `pubspec.yaml` for dependencies — and `flutter run`'s hot reload, the single biggest productivity feature in mobile development: change code, save, and see the update on the emulator in under a second.",
                        ],
                        [
                            'title' => 'Widgets and layouts', 'minutes' => 28,
                            'content' => "In Flutter, everything is a widget — text, buttons, padding, even layout itself. This lesson covers the widgets you'll use constantly: `Text`, `Container`, `Row`, `Column`, and the difference between `StatelessWidget` (renders once from its inputs) and `StatefulWidget` (can change itself over time).\n\n```dart\nColumn(\n  children: [\n    Text('Welcome'),\n    ElevatedButton(onPressed: () {}, child: Text('Continue')),\n  ],\n)\n```\n\nBy the end of this lesson you'll be able to compose a real screen layout — header, content, action button — entirely from Flutter's built-in widgets.",
                        ],
                    ]],
                    ['title' => 'Building the app', 'lessons' => [
                        [
                            'title' => 'Navigation between screens', 'minutes' => 22,
                            'content' => "A real app has more than one screen. This lesson covers Flutter's `Navigator` — pushing a new screen onto the stack, passing data to it, and popping back to the previous screen with a result.\n\n```dart\nNavigator.push(\n  context,\n  MaterialPageRoute(builder: (context) => DetailScreen(id: item.id)),\n);\n```\n\nWe build a list-to-detail flow (tap an item, see its details, go back) — the single most common navigation pattern in real mobile apps.",
                        ],
                        [
                            'title' => 'State management', 'minutes' => 32,
                            'content' => "As an app grows, keeping track of what changed and re-rendering the right widgets becomes the hard part. This lesson starts with Flutter's built-in `setState` (perfect for a single screen), then introduces the `Provider` package for state that needs to be shared across multiple screens — like a logged-in user or a shopping cart.\n\nWe deliberately start simple: you don't need a complex state-management library for every app, and this lesson is honest about when `setState` is genuinely enough.",
                        ],
                        [
                            'title' => 'Calling a REST API', 'minutes' => 30,
                            'content' => "Most real apps talk to a server. This lesson uses the `http` package to call a real REST API, decode the JSON response into Dart objects, and handle the three states every network call actually has: loading, success, and error.\n\n```dart\nfinal response = await http.get(Uri.parse('\$apiUrl/courses'));\nfinal courses = jsonDecode(response.body);\n```\n\nWe build a real loading spinner and a real error state with a retry button — the difference between an app that feels broken on a bad connection and one that feels reliable.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'MySQL Database Design & Administration',
                'cover' => 'cloud-computing.png',
                'tagline' => 'Design, query and administer relational databases the right way.',
                'description' => 'From your first table to indexing and backups — a practical guide to designing and running MySQL databases for real applications.',
                'level' => 'beginner', 'category' => 'Databases', 'price' => 120000,
                'outcomes' => ['Design normalized relational schemas', 'Write efficient SQL queries and joins', 'Index tables for performance', 'Back up and restore a database'],
                'requirements' => ['No prior database experience needed'],
                'modules' => [
                    ['title' => 'Database design', 'lessons' => [
                        [
                            'title' => 'Tables, rows and relationships', 'minutes' => 16, 'preview' => true,
                            'content' => "A relational database stores data in tables — rows and columns, like a spreadsheet, but with rules connecting tables to each other. This lesson covers the three core relationship types you'll use in almost every schema: one-to-many (one course has many lessons), many-to-many (a student enrolls in many courses, a course has many students), and one-to-one.\n\nWe design a small real schema together — `courses`, `lessons`, `students`, `enrollments` — and draw out the relationships before writing a single line of SQL, because a schema is much cheaper to fix on paper than after it's full of data.",
                        ],
                        [
                            'title' => 'Normalization', 'minutes' => 20,
                            'content' => "Normalization is the process of structuring tables to avoid storing the same fact in two places — the source of most painful data bugs. This lesson covers the practical version of the first three normal forms: no repeating groups, every non-key column depends on the whole key, and nothing depends on a column that isn't the key.\n\nRather than memorizing definitions, we take a badly-designed table full of duplicated data and normalize it step by step, so you can recognize the smell of a bad schema in your own projects.",
                        ],
                    ]],
                    ['title' => 'SQL in practice', 'lessons' => [
                        [
                            'title' => 'Selects, joins and subqueries', 'minutes' => 30,
                            'content' => "This is the SQL you'll write every single day. We cover `SELECT` with `WHERE`/`ORDER BY`/`LIMIT`, the four join types (`INNER`, `LEFT`, `RIGHT`, and when you'd actually reach for each), and subqueries for questions a single join can't answer cleanly.\n\n```sql\nSELECT students.name, COUNT(enrollments.id) AS course_count\nFROM students\nLEFT JOIN enrollments ON enrollments.student_id = students.id\nGROUP BY students.id;\n```\n\nEvery example runs against the schema from the design module, so the queries answer real questions (\"which students haven't enrolled in anything?\") rather than testing abstract syntax.",
                        ],
                        [
                            'title' => 'Indexes and performance', 'minutes' => 24,
                            'content' => "A query that's fast on 100 rows can be unusably slow on 10 million — indexes are how you fix that. This lesson covers what an index actually is (a sorted lookup structure, not magic), when to add one (columns you filter or join on frequently), and when not to (indexes slow down writes and cost storage).\n\nWe use `EXPLAIN` to see how MySQL actually executes a query before and after adding an index, so the improvement isn't just claimed — it's measured.",
                        ],
                        [
                            'title' => 'Backups and restores', 'minutes' => 18,
                            'content' => "A database without backups is one mistake away from disaster. This closing lesson covers `mysqldump` for logical backups, restoring from a dump file, and the practical backup schedule a small production app actually needs — not an enterprise disaster-recovery plan, just a reliable, boring routine that actually runs.\n\nWe deliberately practice a full restore during the lesson, not just a backup — a backup you've never restored from is not a backup you can trust.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'React for Beginners',
                'cover' => 'web-development.png',
                'tagline' => 'Build fast, interactive user interfaces with React.',
                'description' => 'Learn React by building real components — hooks, props, state and connecting to an API — the way modern front-ends are actually built.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 130000,
                'outcomes' => ['Build reusable components with props', 'Manage state with hooks', 'Handle forms and events', 'Fetch and display data from an API'],
                'requirements' => ['Comfortable with HTML, CSS and JavaScript basics'],
                'modules' => [
                    ['title' => 'React fundamentals', 'lessons' => [
                        [
                            'title' => 'Components and props', 'minutes' => 18, 'preview' => true,
                            'content' => "React apps are built from components — small, reusable pieces of UI that take inputs (called props) and return what should appear on screen. This lesson builds a `CourseCard` component that accepts a title and price as props, then reuses it to render a whole list of different courses from one piece of code.\n\n```jsx\nfunction CourseCard({ title, price }) {\n  return <div className=\"card\"><h3>{title}</h3><p>{price}</p></div>;\n}\n```\n\nWe cover JSX (writing HTML-like markup inside JavaScript) and why breaking a page into small components makes it dramatically easier to reason about and reuse.",
                        ],
                        [
                            'title' => 'State and hooks', 'minutes' => 26,
                            'content' => "Props flow into a component; state is what a component remembers about itself. This lesson introduces the `useState` hook — React's mechanism for a component to hold and update its own data — by building a real, working counter and a toggleable dropdown.\n\n```jsx\nconst [count, setCount] = useState(0);\n<button onClick={() => setCount(count + 1)}>{count}</button>\n```\n\nWe cover why you never mutate state directly and always go through its setter function — the single most common beginner mistake in React, and the source of bugs that seem to make no sense until you understand why.",
                        ],
                    ]],
                    ['title' => 'Building an app', 'lessons' => [
                        [
                            'title' => 'Forms and events', 'minutes' => 22,
                            'content' => "Forms in React work a little differently than plain HTML — React usually \"controls\" the input's value through state, so the UI and the underlying data never disagree. This lesson builds a real controlled form (name, email, message) with live validation feedback as the user types.\n\n```jsx\n<input value={email} onChange={(e) => setEmail(e.target.value)} />\n```\n\nWe also cover handling form submission properly — preventing the default page reload, and giving the user clear feedback while a submission is in progress.",
                        ],
                        [
                            'title' => 'Fetching data from an API', 'minutes' => 28,
                            'content' => "Real apps load real data. This closing lesson uses the `useEffect` hook to fetch data from an API when a component first appears, store it in state, and render loading/error/success states properly — the same three states every network request needs, in React this time instead of Flutter.\n\n```jsx\nuseEffect(() => {\n  fetch('/api/courses').then(res => res.json()).then(setCourses);\n}, []);\n```\n\nBy the end you'll have built a real course listing page powered by live data, not a hardcoded array.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'REST APIs with Laravel',
                'cover' => 'programming.png',
                'tagline' => 'Design and build production-ready APIs with Laravel and Sanctum.',
                'description' => 'A focused, advanced course on API design — authentication, resource responses, rate limiting and versioning, built the way real production APIs are built.',
                'level' => 'advanced', 'category' => 'Web Development', 'price' => 100000,
                'outcomes' => ['Design consistent, versioned API endpoints', 'Authenticate requests with Sanctum tokens', 'Shape responses with API resources', 'Rate-limit and secure public endpoints'],
                'requirements' => ['Solid PHP and Laravel fundamentals'],
                'modules' => [
                    ['title' => 'API design', 'lessons' => [
                        [
                            'title' => 'REST principles and versioning', 'minutes' => 18, 'preview' => true,
                            'content' => "A good API is predictable — the same shape and conventions everywhere, so a consumer can guess how an endpoint behaves before reading its docs. This lesson covers REST fundamentals (resources, HTTP verbs mapping to actions, meaningful status codes) and why versioning your API from day one (`/api/v1/...`) saves you from breaking every existing client the first time you need to change something.\n\nWe design a small, consistent endpoint set for a courses resource together, applying the same conventions you'd want from any API you consume yourself.",
                        ],
                        [
                            'title' => 'API resources and responses', 'minutes' => 24,
                            'content' => "Never return a raw Eloquent model from an API endpoint — it leaks internal columns and makes your response shape hostage to your database schema. This lesson covers Laravel API Resources, which give you full control over exactly what a response contains.\n\n```php\nclass CourseResource extends JsonResource {\n    public function toArray(\$request) {\n        return ['id' => \$this->id, 'title' => \$this->title, 'price' => \$this->price];\n    }\n}\n```\n\nWe also cover a consistent envelope shape (`{\"data\": ...}` vs. `{\"error\": ...}`) so every consumer of your API can handle success and failure the same way, every time.",
                        ],
                    ]],
                    ['title' => 'Securing the API', 'lessons' => [
                        [
                            'title' => 'Token authentication with Sanctum', 'minutes' => 26,
                            'content' => "Laravel Sanctum issues lightweight API tokens — the standard way to authenticate mobile apps and SPAs against a Laravel backend. This lesson covers issuing a token on login, sending it as a Bearer token on every subsequent request, and protecting routes with the `auth:sanctum` middleware.\n\n```php\nRoute::middleware('auth:sanctum')->get('/user', fn (Request \$r) => \$r->user());\n```\n\nWe also cover token abilities (scoping what a given token is allowed to do) and revoking a token when a user logs out or a device is lost.",
                        ],
                        [
                            'title' => 'Rate limiting and throttling', 'minutes' => 16,
                            'content' => "A public API without rate limiting is an outage waiting to happen — one buggy client (or one bad actor) can take your whole service down. This closing lesson covers Laravel's built-in throttle middleware, setting sensible per-route limits, and returning a proper `429 Too Many Requests` response instead of letting the server fall over.\n\n```php\nRoute::middleware('throttle:60,1')->group(function () { /* ... */ });\n```\n\nWe close with a short checklist for taking an API from \"it works on my machine\" to something you'd trust in front of real traffic.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Git & GitHub for Developers',
                'cover' => 'programming.png',
                'tagline' => 'Version control, branching and collaborating on real projects with Git.',
                'description' => 'Everything you need to use Git and GitHub confidently on a real team — branching, pull requests, merge conflicts and a clean commit history.',
                'level' => 'beginner', 'category' => 'Tools', 'price' => 0,
                'outcomes' => ['Track changes with commits and branches', 'Collaborate through pull requests', 'Resolve merge conflicts', 'Keep a clean, readable commit history'],
                'requirements' => ['No prior experience needed'],
                'modules' => [
                    ['title' => 'Git basics', 'lessons' => [
                        [
                            'title' => 'Commits, branches and history', 'minutes' => 14, 'preview' => true,
                            'content' => "Git tracks every change you make to a project as a series of commits — snapshots you can always go back to. This lesson covers the everyday commands: `git init`, `git add`, `git commit`, and `git log` to see your history, plus branches — lightweight, disposable copies of your project where you can try things safely.\n\n```bash\ngit checkout -b feature/new-header\ngit add .\ngit commit -m \"Add new header component\"\n```\n\nWe cover writing a good commit message (what changed and why, not just \"fixed stuff\") — the habit that makes a project's history actually useful six months later.",
                        ],
                        [
                            'title' => 'Working with remotes', 'minutes' => 12,
                            'content' => "A local Git repository only you can see isn't collaboration. This lesson covers remotes — connecting your local repository to GitHub — and the push/pull cycle that keeps your local copy and the shared copy in sync.\n\n```bash\ngit remote add origin https://github.com/you/project.git\ngit push -u origin main\ngit pull\n```\n\nWe also cover cloning an existing repository and the difference between `fetch` (check what's new, don't apply it yet) and `pull` (fetch and merge in one step).",
                        ],
                    ]],
                    ['title' => 'Working on a team', 'lessons' => [
                        [
                            'title' => 'Pull requests and code review', 'minutes' => 16,
                            'content' => "A pull request is a proposal to merge one branch into another, with a space for discussion before it happens. This lesson walks through opening a real pull request on GitHub, writing a description that helps a reviewer understand *why* a change was made, and responding to review comments.\n\nWe also cover why teams review code at all — it's not about catching every bug, it's a second set of eyes, shared knowledge of the codebase, and a natural checkpoint before anything reaches production.",
                        ],
                        [
                            'title' => 'Resolving merge conflicts', 'minutes' => 18,
                            'content' => "A merge conflict happens when Git can't automatically combine two changes to the same lines — it's normal, not a sign something went wrong. This closing lesson deliberately creates a real conflict, then walks through resolving it calmly: reading the conflict markers, deciding which change (or both) should survive, and completing the merge.\n\n```\n<<<<<<< HEAD\nconst title = 'Welcome!';\n=======\nconst title = 'Hello there!';\n>>>>>>> feature/new-header\n```\n\nBy the end, a merge conflict will feel like a routine five-minute task instead of something to panic about.",
                        ],
                    ]],
                ],
            ],
        ];
    }

    /** @param array<string,mixed> $definition */
    private function seedCourse(User $owner, array $definition): void
    {
        $course = Course::updateOrCreate(
            ['slug' => Str::slug($definition['title'])],
            [
                'uuid' => (string) Str::uuid(),
                'title' => $definition['title'],
                'cover_image' => asset('images/courses/'.$definition['cover']),
                'cover_alt' => $definition['title'].' course cover',
                'tagline' => $definition['tagline'],
                'description' => $definition['description'],
                'outcomes' => $definition['outcomes'],
                'requirements' => $definition['requirements'],
                'price' => $definition['price'],
                'currency' => 'UGX',
                'level' => $definition['level'],
                'category' => $definition['category'],
                'is_published' => true,
                'created_by' => $owner->id,
                'progression' => 'sequential',
            ]
        );

        foreach ($definition['modules'] as $moduleIndex => $moduleDefinition) {
            $module = $course->modules()->updateOrCreate(
                ['title' => $moduleDefinition['title']],
                ['sort_order' => $moduleIndex]
            );

            foreach ($moduleDefinition['lessons'] as $lessonIndex => $lessonDefinition) {
                $module->lessons()->updateOrCreate(
                    ['title' => $lessonDefinition['title']],
                    [
                        'content' => $lessonDefinition['content'],
                        'content_format' => ContentFormat::Markdown,
                        'duration_minutes' => $lessonDefinition['minutes'],
                        'sort_order' => $lessonIndex,
                        'is_published' => true,
                        'is_free_preview' => $lessonDefinition['preview'] ?? false,
                    ]
                );
            }
        }
    }
}
