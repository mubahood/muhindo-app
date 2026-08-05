<?php

namespace Database\Seeders;

use App\Enums\ContentFormat;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PUBLIC_SITE_PLAN.md A real-looking public catalogue to build and review the
 * e-Learning pages against, distinct from `DemoCourseSeeder` (which is explicitly
 * dev-scoped: one free course titled "(Demo)"). Every course here is something
 * Muhindo actually teaches on the "Learn It With Muhindo" channel, web development,
 * mobile development, databases, tooling, spanning free/paid, every level, and
 * several categories so 's filters have real data to filter.
 *
 * Every lesson carries real, topic-accurate written content (not a one-line
 * placeholder) so the lesson player looks like a real course when reviewed.
 * no video is attached (no real video file/URL exists to point at, and inventing
 * one would be a broken embed), so the written lesson body is deliberately the
 * substantial part of each lesson here.
 *
 * Opt-in only (`--class=PublicCourseCatalogueSeeder`), not wired into
 * `DatabaseSeeder`'s default chain. Safe to re-run. Every write is an upsert keyed
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
                'tagline' => 'A complete, W3Schools-style HTML course, from your first tag to semantic layouts and forms.',
                'description' => 'A thorough, practical HTML course for absolute beginners, structured the way the best references teach it: elements, attributes, text, links, images, tables, lists, semantic layout, and forms, every lesson with real code you type yourself.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 0,
                'outcomes' => ['Write valid, semantic HTML from scratch', 'Structure pages with headings, lists, tables and semantic layout elements', 'Link pages, embed images and media correctly', 'Build accessible forms with the right input types and validation attributes'],
                'requirements' => ['A computer with internet access', 'No prior programming experience needed'],
                'quiz' => [
                    'title' => 'HTML Fundamentals Check',
                    'attach_to' => 'Attributes',
                    'required' => true,
                    'pass_percent' => 70,
                    'description' => 'A required check on the fundamentals, submit it to unlock lesson completion.',
                    'questions' => [
                        ['type' => 'mcq_single', 'prompt' => 'Which attribute provides alternative text for an image?', 'points' => 1,
                            'options' => [['alt', true], ['src', false], ['title', false], ['href', false]]],
                        ['type' => 'true_false', 'prompt' => 'HTML tag names are case-sensitive.', 'points' => 1,
                            'options' => [['True', false], ['False', true]]],
                        ['type' => 'mcq_multi', 'prompt' => 'Which of these are real HTML5 semantic layout elements?', 'points' => 2,
                            'options' => [['article', true], ['section', true], ['headerbar', false], ['spanner', false]]],
                        ['type' => 'fill_blank', 'prompt' => 'The <a> element\'s ____ attribute holds the URL the link points to.', 'points' => 1,
                            'accepted' => ['href']],
                        ['type' => 'short_text', 'prompt' => 'Which element marks the most important heading on a page?', 'points' => 1,
                            'accepted' => ['h1', '<h1>']],
                    ],
                ],
                'assignment' => [
                    'title' => 'Build a Contact Page',
                    'attach_to' => 'Forms',
                    'required' => false,
                    'points' => 30,
                    'allowed_types' => 'text,link,zip',
                    'instructions' => 'Build a complete contact page using what you learned: a heading, a short intro paragraph, and a form with name, email, a subject dropdown, a message textarea and a submit button. Use labels for every field and at least two validation attributes. Paste your HTML code directly, share a link to it, or upload it as a .zip.',
                ],
                'modules' => [
                    ['title' => 'HTML Fundamentals', 'lessons' => [
                        [
                            'title' => 'What is HTML?', 'minutes' => 10, 'min_seconds' => 120, 'preview' => true,
                            'content' => "HTML (HyperText Markup Language) is the standard language every web page is written in. It describes the *structure* of a page as a tree of elements: headings, paragraphs, links, images, lists. Browsers read HTML and render it into what you see.\n\nA minimal but complete HTML document:\n\n```html\n<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"UTF-8\">\n  <title>Page Title</title>\n</head>\n<body>\n  <h1>My First Heading</h1>\n  <p>My first paragraph.</p>\n</body>\n</html>\n```\n\n- `<!DOCTYPE html>` declares this is an HTML5 document\n- `<html>` is the root element of the page\n- `<head>` holds machine-facing information about the page\n- `<body>` holds everything a visitor actually sees\n\nType this out by hand (don't copy-paste), save it as `index.html`, and open it in your browser | you've written your first web page.",
                        ],
                        [
                            'title' => 'HTML elements and nesting', 'minutes' => 12, 'min_seconds' => 180,
                            'content' => "An HTML element is everything from a start tag to its matching end tag: `<p>My paragraph.</p>`. Elements nest inside each other, forming the tree the browser renders, and getting nesting right is what separates valid HTML from a page that only *happens* to work.\n\n```html\n<body>\n  <h1>Gardening tips</h1>\n  <p>Watering matters more than <b>most people think</b>.</p>\n</body>\n```\n\nRules to internalize now:\n\n- Close elements in the reverse order you opened them, `<p><b>text</b></p>`, never `<p><b>text</p></b>`\n- A few elements are *empty* (void) and have no end tag: `<br>`, `<hr>`, `<img>`, `<meta>`\n- Browsers forgive missing end tags on some elements, but never rely on that, write the end tag\n\nEverything you build for the rest of this course is nested inside `<body>`; everything describing the page lives in `<head>`.",
                        ],
                        [
                            'title' => 'Attributes', 'minutes' => 12, 'min_seconds' => 180,
                            'content' => "Attributes give elements extra information, always written in the start tag as `name=\"value\"` pairs.\n\n```html\n<a href=\"https://example.com\">Visit example.com</a>\n<img src=\"garden.jpg\" alt=\"A vegetable garden at sunrise\" width=\"500\" height=\"300\">\n<p title=\"Shown as a tooltip on hover\">Hover over me.</p>\n<html lang=\"en\">\n```\n\nThe ones you'll use constantly:\n\n- `href` (the URL an `<a>` link points to\n- `src`) the file an `<img>` (or script) loads\n- `alt`. The text shown/read when an image can't be seen; screen readers depend on it\n- `width`/`height` (reserving image space so the page doesn't jump while loading\n- `style`, `class`, `id`) hooks for styling, covered later in this course\n- `lang` on `<html>`, declares the page language for search engines and assistive tech\n\nAlways quote attribute values, and always write `alt` on every image, it's the difference between a page everyone can use and one only sighted visitors can.",
                        ],
                        [
                            'title' => 'Headings, paragraphs and line breaks', 'minutes' => 10, 'min_seconds' => 120,
                            'content' => "Headings run from `<h1>` (most important, one per page) to `<h6>` (least). Search engines and screen readers use them as the page's outline, so never pick a heading level because of how big it looks, pick it for what it *means*, then restyle with CSS later.\n\n```html\n<h1>All about tea</h1>\n<h2>Green tea</h2>\n<h3>Brewing temperature</h3>\n\n<p>Paragraphs are the workhorse of text content.</p>\n<p>The browser collapses    extra spaces\n   and line breaks into single spaces.</p>\n<hr>\n<p>Need a line break<br>without a new paragraph? That's <code>&lt;br&gt;</code>.</p>\n<pre>\n  Text inside pre\n    keeps its    spacing\n      exactly as written.\n</pre>\n```\n\n- `<hr>` draws a thematic break between sections\n- `<br>` forces a line break inside a paragraph (use it for addresses or poems, not for spacing\n- `<pre>` preserves whitespace exactly) perfect for code and ASCII layouts",
                        ],
                    ]],
                    ['title' => 'Text, Links & Media', 'lessons' => [
                        [
                            'title' => 'Text formatting', 'minutes' => 12, 'min_seconds' => 180,
                            'content' => "HTML has two families of text formatting: *presentational* and *semantic*, and the semantic ones are almost always the better choice, because they carry meaning to browsers, search engines and screen readers.\n\n```html\n<p><b>Bold</b> and <strong>important</strong> look the same, but mean different things.</p>\n<p><i>Italic</i> and <em>emphasized</em> likewise.</p>\n<p><mark>Highlighted</mark>, <small>fine print</small>, <del>removed</del>, <ins>added</ins>.</p>\n<p>H<sub>2</sub>O uses subscript; E = mc<sup>2</sup> uses superscript.</p>\n<p>Use <code>code</code> for inline code and <kbd>Ctrl + S</kbd> for keyboard input.</p>\n```\n\n- `<strong>` = \"this matters\" (semantic); `<b>` = \"make it bold\" (visual only)\n- `<em>` = spoken emphasis; `<i>` = italics for terms, titles, foreign words\n- `<del>`/`<ins>` show edits, great for prices and corrections\n\nWhen in doubt, ask: am I conveying *meaning* or just changing the look? Meaning → semantic element. Look → CSS, later.",
                        ],
                        [
                            'title' => 'Quotations and citations', 'minutes' => 8, 'min_seconds' => 120,
                            'content' => "HTML has purpose-built elements for quoted and referenced text, using them correctly makes your page more readable to both people and machines.\n\n```html\n<blockquote cite=\"https://www.example.com/source\">\n  <p>The best way to learn HTML is to write HTML.</p>\n</blockquote>\n\n<p>As they say, <q>practice beats theory</q>.</p>\n\n<p><abbr title=\"HyperText Markup Language\">HTML</abbr> powers every page you visit.</p>\n\n<address>\nWritten by Muhindo Mubaraka<br>\nKampala, Uganda\n</address>\n\n<p><cite>The Elements of Style</cite> remains a classic.</p>\n```\n\n- `<blockquote>` for long, indented quotations (with an optional `cite` URL)\n- `<q>` for short inline quotes, the browser adds the quotation marks for you\n- `<abbr>` with a `title` explains abbreviations on hover and to assistive tech\n- `<address>` marks contact information; `<cite>` marks the title of a work",
                        ],
                        [
                            'title' => 'Links', 'minutes' => 14, 'min_seconds' => 180,
                            'content' => "Links are what make the web a web. The `<a>` element wraps anything clickable, text, images, even whole cards.\n\n```html\n<a href=\"https://example.com\">Absolute link to another site</a>\n<a href=\"about.html\">Relative link within your own site</a>\n<a href=\"https://example.com\" target=\"_blank\" rel=\"noopener\">Opens in a new tab</a>\n<a href=\"#pricing\">Jump to the section with id=\"pricing\"</a>\n<a href=\"mailto:you@example.com\">Email me</a>\n<a href=\"tel:+256700000000\">Call me</a>\n```\n\nThings that matter:\n\n- Relative URLs (`about.html`, `../index.html`) keep working when your site moves domains, prefer them for internal links\n- `target=\"_blank\"` opens a new tab; always pair it with `rel=\"noopener\"` for security\n- Bookmark links (`#id`) jump to any element with a matching `id`, that's how tables of contents work\n- Link text should describe the destination: \"read the pricing guide\", never \"click here\"",
                        ],
                        [
                            'title' => 'Images', 'minutes' => 14, 'min_seconds' => 180,
                            'content' => "Images are embedded (not inserted) with the void `<img>` element: the page links to the image file, and the browser fetches and renders it in place.\n\n```html\n<img src=\"img/garden.jpg\" alt=\"Raised vegetable beds in a backyard garden\" width=\"600\" height=\"400\">\n\n<figure>\n  <img src=\"img/harvest.jpg\" alt=\"A basket of freshly picked tomatoes\" width=\"600\" height=\"400\">\n  <figcaption>First harvest of the season.</figcaption>\n</figure>\n\n<a href=\"https://example.com\"><img src=\"img/logo.png\" alt=\"Example Ltd home page\" width=\"120\" height=\"40\"></a>\n```\n\nNon-negotiables:\n\n- **Always** write `alt` text describing the image's content or purpose; if an image is purely decorative, use `alt=\"\"` so screen readers skip it\n- **Always** set `width` and `height`, the browser reserves the space and the page stops jumping around while images load\n- Use `<figure>`/`<figcaption>` when an image needs a caption\n- An image inside `<a>` becomes a clickable image, its `alt` text doubles as the link text",
                        ],
                        [
                            'title' => 'Colors and inline styles', 'minutes' => 12, 'min_seconds' => 180,
                            'content' => "The `style` attribute applies CSS directly to a single element, the doorway between HTML and the styling language you'll meet fully in a CSS course.\n\n```html\n<h1 style=\"color: #0b1f3a;\">Navy heading</h1>\n<p style=\"color: rgb(125, 98, 40); font-size: 18px;\">Golden brown text, 18px.</p>\n<div style=\"background-color: #f7f0df; padding: 16px;\">A padded, tinted box.</div>\n<p style=\"font-family: Georgia, serif; text-align: center;\">Centered serif text.</p>\n```\n\nColors can be written as:\n\n- **Names** (`red`, `tomato`, `steelblue` (about 140 exist)\n- **Hex**) `#ff6347`: two digits each for red, green, blue\n- **RGB**, `rgb(255, 99, 71)`; add alpha with `rgba(255, 99, 71, 0.5)`\n- **HSL**, `hsl(9, 100%, 64%)`: hue, saturation, lightness\n\nInline styles are fine for experimenting and for one-off tweaks, but the moment you repeat a style twice, it belongs in a stylesheet with a `class`, which is exactly where the next lessons take you.",
                        ],
                    ]],
                    ['title' => 'Structuring Pages', 'lessons' => [
                        [
                            'title' => 'Lists', 'minutes' => 10, 'min_seconds' => 120,
                            'content' => "HTML gives you three list types, and choosing the right one is another small act of semantics.\n\n```html\n<ul>\n  <li>Unordered: bullet points</li>\n  <li>Order doesn't matter</li>\n</ul>\n\n<ol start=\"3\" type=\"A\">\n  <li>Ordered: numbered (or lettered) steps</li>\n  <li>Order matters</li>\n</ol>\n\n<dl>\n  <dt>HTML</dt>\n  <dd>The structure language of the web.</dd>\n  <dt>CSS</dt>\n  <dd>The styling language of the web.</dd>\n</dl>\n```\n\n- `<ul>`/`<li>` for collections where order is irrelevant (navigation menus are almost always `<ul>`s)\n- `<ol>` for sequences, steps, rankings; `start` and `type` control numbering\n- `<dl>`/`<dt>`/`<dd>` for term-and-description pairs, glossaries, FAQs, metadata\n- Lists nest: put a whole `<ul>` inside an `<li>` to build sub-lists",
                        ],
                        [
                            'title' => 'Tables', 'minutes' => 16, 'min_seconds' => 240,
                            'content' => "Tables are for *tabular data* (rows and columns of related values) never for page layout (semantic layout elements do that job now).\n\n```html\n<table>\n  <caption>Monthly savings</caption>\n  <thead>\n    <tr><th>Month</th><th>Savings</th></tr>\n  </thead>\n  <tbody>\n    <tr><td>January</td><td>UGX 100,000</td></tr>\n    <tr><td>February</td><td>UGX 150,000</td></tr>\n  </tbody>\n</table>\n```\n\nThe building blocks:\n\n- `<tr>` = a row; `<th>` = a header cell; `<td>` = a data cell\n- `<thead>`, `<tbody>`, `<tfoot>` group rows meaningfully\n- `<caption>` titles the table for everyone, including screen readers\n- `colspan`/`rowspan` merge cells across columns or rows:\n\n```html\n<tr><th colspan=\"2\">Contact</th></tr>\n<tr><td>Email</td><td>info@example.com</td></tr>\n```\n\nHeader cells aren't decoration, screen readers announce them with each data cell, which is why a real `<th>` beats a bolded `<td>` every time.",
                        ],
                        [
                            'title' => 'Block, inline, div and span', 'minutes' => 12, 'min_seconds' => 180,
                            'content' => "Every HTML element renders as either **block** (starts on a new line, fills the available width, `<p>`, `<h1>`, `<ul>`, `<div>`) or **inline** (flows within a line, only as wide as its content, `<a>`, `<b>`, `<img>`, `<span>`).\n\nTwo generic containers exist purely as hooks for styling and scripting:\n\n```html\n<div class=\"card\">\n  <h2>A grouped block of content</h2>\n  <p>Styled as one unit via its class.</p>\n</div>\n\n<p>Prices start at <span class=\"price\">UGX 50,000</span> per month.</p>\n```\n\n- `<div>`, a block-level box with no meaning of its own; group things to style them together\n- `<span>`, its inline twin; style a few words inside a line\n- `class`, reusable label, any number of elements can share it (and one element can carry several, space-separated)\n- `id`, unique label, one element per page; doubles as a link target (`#pricing`)\n\nRule of thumb: reach for a semantic element first (`<article>`, `<nav>`, `<strong>`); use `div`/`span` only when nothing meaningful fits.",
                        ],
                        [
                            'title' => 'Semantic page layout', 'minutes' => 14, 'min_seconds' => 180,
                            'content' => "HTML5 replaced the old `<div id=\"header\">` soup with elements that *say what they are*, and browsers, search engines and screen readers all understand them.\n\n```html\n<body>\n  <header>\n    <h1>The Kampala Gardener</h1>\n    <nav>\n      <a href=\"index.html\">Home</a>\n      <a href=\"tips.html\">Tips</a>\n    </nav>\n  </header>\n\n  <main>\n    <article>\n      <h2>Growing tomatoes in pots</h2>\n      <section>\n        <h3>Choosing a pot</h3>\n        <p>Bigger than you think...</p>\n      </section>\n      <aside>Related: watering schedules</aside>\n    </article>\n  </main>\n\n  <footer>&copy; 2026 The Kampala Gardener</footer>\n</body>\n```\n\n- `<header>`/`<footer>` (top and bottom matter (page-level or per-article)\n- `<nav>`) major navigation blocks\n- `<main>` (the unique content of this page, exactly one per page\n- `<article>`) self-contained content that would make sense on its own\n- `<section>` (a thematic grouping, almost always with its own heading\n- `<aside>`) tangential content: sidebars, pull quotes, related links",
                        ],
                        [
                            'title' => 'Iframes', 'minutes' => 8, 'min_seconds' => 120,
                            'content' => "An `<iframe>` embeds one web page inside another, it's how maps, videos and previews end up inside your pages.\n\n```html\n<iframe src=\"https://example.com\" title=\"Example site preview\"\n        width=\"600\" height=\"400\"></iframe>\n\n<iframe src=\"demo.html\" title=\"Live demo\" name=\"demo-frame\"></iframe>\n<p><a href=\"version2.html\" target=\"demo-frame\">Load version 2 into the frame above</a></p>\n```\n\nWhat to know:\n\n- **Always** give an iframe a `title`, it's how screen-reader users know what's embedded\n- A link's `target` can name an iframe, loading the link inside it instead of the current tab\n- Many sites (correctly) block being iframed; if the frame stays blank, that's the embedded site's policy, not your bug\n- Iframes are heavyweight, each one loads a whole page, so use them deliberately, not decoratively",
                        ],
                    ]],
                    ['title' => 'Forms & the Head', 'lessons' => [
                        [
                            'title' => 'Forms', 'minutes' => 18, 'min_seconds' => 240,
                            'content' => "Forms are how pages collect input. Every form is a `<form>` element wrapping controls, with `action` (where the data goes) and `method` (how it's sent).\n\n```html\n<form action=\"/subscribe\" method=\"post\">\n  <label for=\"name\">Name</label>\n  <input type=\"text\" id=\"name\" name=\"name\">\n\n  <label for=\"plan\">Plan</label>\n  <select id=\"plan\" name=\"plan\">\n    <option value=\"free\">Free</option>\n    <option value=\"pro\" selected>Pro</option>\n  </select>\n\n  <label for=\"msg\">Message</label>\n  <textarea id=\"msg\" name=\"msg\" rows=\"4\"></textarea>\n\n  <button type=\"submit\">Subscribe</button>\n</form>\n```\n\nThe rules that make forms *work*:\n\n- Every control needs a `name`. A field without one is silently left out of the submission\n- Every control deserves a `<label>` whose `for` matches the control's `id`, clicking the label focuses the field, and screen readers announce it\n- `method=\"get\"` puts data in the URL (searches, filters); `method=\"post\"` sends it in the request body (anything that changes data, and never put passwords in a URL)",
                        ],
                        [
                            'title' => 'Input types and validation', 'minutes' => 14, 'min_seconds' => 180,
                            'content' => "The `type` attribute turns one element (`<input>`) into a whole toolbox, and picking the right type gives you mobile keyboards and built-in validation for free.\n\n```html\n<input type=\"email\" name=\"email\" required>\n<input type=\"number\" name=\"qty\" min=\"1\" max=\"10\" step=\"1\">\n<input type=\"date\" name=\"delivery\">\n<input type=\"password\" name=\"pw\" minlength=\"8\">\n<input type=\"checkbox\" id=\"terms\" name=\"terms\" required>\n<input type=\"radio\" name=\"size\" value=\"m\" checked> \n<input type=\"range\" name=\"volume\" min=\"0\" max=\"100\">\n<input type=\"file\" name=\"cv\" accept=\".pdf\">\n```\n\nValidation attributes the browser enforces before anything is submitted:\n\n- `required` (the field must be filled\n- `min`/`max`/`step`) numeric and date bounds\n- `minlength`/`maxlength` (text length bounds\n- `pattern`) a regular expression the value must match\n\nBrowser validation is a courtesy to users, not security. Anyone can bypass it, so the server must always re-validate. (You'll hear that again in every backend course, because it's that important.)",
                        ],
                        [
                            'title' => 'The head, meta and the viewport', 'minutes' => 10, 'min_seconds' => 120,
                            'content' => "Everything inside `<head>` is for machines: browsers, search engines, social networks. Nothing in it renders on the page, but it decides how the page behaves everywhere else.\n\n```html\n<head>\n  <meta charset=\"UTF-8\">\n  <title>Growing Tomatoes | The Kampala Gardener</title>\n  <meta name=\"description\" content=\"A practical guide to growing tomatoes in pots.\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <link rel=\"stylesheet\" href=\"styles.css\">\n  <link rel=\"icon\" href=\"favicon.png\">\n</head>\n```\n\n- `<title>` | the browser-tab text and the headline in search results; make it specific\n- `meta charset=\"UTF-8\"` | first thing in head, so every character renders correctly\n- `meta description` | the snippet search engines show under your title\n- **The viewport meta is why your page works on phones**: without it, mobile browsers render a zoomed-out desktop page\n- `<link>` pulls in stylesheets and the favicon",
                        ],
                        [
                            'title' => 'Entities, symbols and best practices', 'minutes' => 10, 'min_seconds' => 120,
                            'content' => "Some characters can't be typed directly into HTML (`<` starts a tag, `&` starts an entity), so HTML provides named escapes:\n\n```html\n<p>5 &lt; 10 and 10 &gt; 5 &amp; that's maths.</p>\n<p>&copy; 2026 &middot; Price: 50&nbsp;000 UGX &euro; &pound; &hearts;</p>\n```\n\n- `&lt;` `&gt;` `&amp;` `&quot;`, the essential four\n- `&nbsp;`. A non-breaking space that keeps \"50 000\" on one line\n- `&copy;` `&euro;` `&hearts;` and hundreds more symbols\n\nAnd the habits that mark clean HTML, all of which you've been practicing:\n\n1. Declare `<!DOCTYPE html>` and `lang` on `<html>`\n2. One `<h1>` per page; heading levels never skip\n3. `alt` on every image, `<label>` on every form control\n4. Close what you open; lowercase tag and attribute names; quote attribute values\n5. Validate your pages with the free W3C validator, it catches what eyes miss\n\nRun your contact-page assignment through the validator before submitting it. A clean validation report is the mark of a page built right.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Laravel From Scratch',
                'cover' => 'web-development.png',
                'tagline' => 'Build real, database-backed web applications with Laravel and PHP.',
                'description' => 'A project-based course covering routing, Eloquent, Blade, authentication and deployment. Everything you need to build and ship a real Laravel application.',
                'level' => 'intermediate', 'category' => 'Web Development', 'price' => 150000,
                'outcomes' => ['Build CRUD applications with Eloquent and Blade', 'Handle authentication and authorization', 'Write and run automated tests', 'Deploy a Laravel app to a live server'],
                'requirements' => ['Basic PHP knowledge', 'Basic HTML/CSS'],
                'modules' => [
                    ['title' => 'Laravel essentials', 'lessons' => [
                        [
                            'title' => 'Routing and controllers', 'minutes' => 20, 'preview' => true,
                            'content' => "Every request to a Laravel app starts in `routes/web.php`, a route matches a URL to a piece of code that handles it. This lesson covers defining routes, passing route parameters, and moving that logic out of the routes file into a proper controller once it grows past a line or two.\n\n```php\nRoute::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');\n```\n\nWe cover route model binding (Laravel resolving `{course}` straight into a `Course` model for you), named routes, and why controllers exist, keeping `routes/web.php` as a readable map of your application rather than a dumping ground for logic.",
                        ],
                        [
                            'title' => 'Blade templates', 'minutes' => 22,
                            'content' => "Blade is Laravel's templating engine, plain HTML with a small set of directives for loops, conditionals, and reusable layouts. This lesson builds a real layout with `@extends`/`@section`, loops data into a page with `@foreach`, and introduces components for pieces of UI you'll reuse across pages (a card, a button, an alert).\n\n```blade\n@foreach(\$courses as \$course)\n  <div class=\"card\">{{ \$course->title }}</div>\n@endforeach\n```\n\nWe also cover Blade's automatic HTML escaping (`{{ }}`) versus raw output (`{!! !!}`), and why you almost always want the former, for security.",
                        ],
                        [
                            'title' => 'Eloquent models and migrations', 'minutes' => 35,
                            'content' => "Eloquent is Laravel's ORM. It lets you work with database rows as PHP objects instead of writing raw SQL everywhere. This lesson covers migrations (version-controlled database schema changes), models, and Eloquent relationships (`hasMany`, `belongsTo`) using a real courses-and-lessons schema as the running example.\n\n```php\nclass Course extends Model {\n    public function lessons(): HasMany {\n        return \$this->hasMany(Lesson::class);\n    }\n}\n```\n\nBy the end you'll be able to design a small relational schema, write the migrations for it, and query it naturally through Eloquent relationships instead of manual joins.",
                        ],
                    ]],
                    ['title' => 'Building a real app', 'lessons' => [
                        [
                            'title' => 'CRUD from scratch', 'minutes' => 40,
                            'content' => "CRUD (Create, Read, Update, Delete) is the backbone of almost every web application. This lesson builds a complete CRUD flow for a real resource: a form to create it, a list page to read it, an edit form to update it, and a delete action, wired through a resource controller (`Route::resource`).\n\nWe pay close attention to the details that separate a working demo from production-ready code: redirecting after a successful save (never leave a form re-submittable on refresh), flashing a success message, and confirming before a destructive delete.",
                        ],
                        [
                            'title' => 'Authentication with Breeze', 'minutes' => 30,
                            'content' => "Laravel Breeze gives you a complete, secure login/register/password-reset flow out of the box. This lesson installs Breeze into the app we've been building, walks through what it actually generates (so it never feels like magic), and covers protecting routes with the `auth` middleware so only signed-in users can reach certain pages.\n\n```php\nRoute::middleware('auth')->group(function () {\n    Route::get('/dashboard', DashboardController::class);\n});\n```\n\nWe also cover the difference between authentication (who are you) and authorization (what are you allowed to do), setting up the next lesson's validation and error-handling work on solid ground.",
                        ],
                        [
                            'title' => 'Form validation and error handling', 'minutes' => 25,
                            'content' => "Never trust input from the browser, validation is what keeps bad data out of your database. This lesson covers Laravel's `\$request->validate()`, writing clear validation rules, and displaying field-level errors back to the user without losing what they already typed.\n\n```php\n\$data = \$request->validate([\n    'title' => 'required|string|max:200',\n    'email' => 'required|email',\n]);\n```\n\nWe also cover graceful failure. What a user sees when something goes wrong that isn't their fault (a failed payment, an unreachable service), so the app never shows a raw error page to a real visitor.",
                        ],
                    ]],
                    ['title' => 'Shipping it', 'lessons' => [
                        [
                            'title' => 'Testing with PHPUnit', 'minutes' => 28,
                            'content' => "A feature you haven't tested is a feature you're not sure works. This lesson introduces PHPUnit through Laravel's testing helpers, spinning up a test database, hitting a real route, and asserting on the response.\n\n```php\npublic function test_a_guest_can_view_the_course_list(): void\n{\n    \$this->get('/courses')->assertOk();\n}\n```\n\nWe write tests for both the happy path (a valid form submission succeeds) and the abuse path (an invalid submission is rejected), the same discipline real production Laravel teams use before merging any change.",
                        ],
                        [
                            'title' => 'Deploying to a live server', 'minutes' => 24,
                            'content' => "A Laravel app only matters once real people can use it. This closing lesson covers what changes between your local machine and a live server: environment variables, running migrations safely against production data, caching config/routes for performance, and the handful of checks worth running before every deploy.\n\nWe walk through a real deployment end to end, pushing code, running `php artisan migrate --force`, and confirming the live site actually works, the same checklist used to ship this platform itself.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Flutter Mobile App Development',
                'cover' => 'mobile-apps.png',
                'tagline' => 'Build native Android and iOS apps from one Dart codebase.',
                'description' => 'Learn Flutter and Dart by building a real, working mobile app, widgets, navigation, state management and connecting to a live API.',
                'level' => 'intermediate', 'category' => 'Mobile Development', 'price' => 200000,
                'outcomes' => ['Build responsive UIs with Flutter widgets', 'Manage app state', 'Navigate between screens', 'Consume a REST API from a mobile app'],
                'requirements' => ['Basic programming knowledge (any language)', 'A computer able to run Android Studio'],
                'modules' => [
                    ['title' => 'Flutter basics', 'lessons' => [
                        [
                            'title' => 'Setting up Flutter', 'minutes' => 15, 'preview' => true,
                            'content' => "Flutter lets you write one Dart codebase that compiles to real native Android and iOS apps, not a web view wrapped in a shell. This lesson installs the Flutter SDK, sets up an emulator, and runs the default counter app so your environment is confirmed working before we write a single line of our own.\n\nWe also cover the project structure Flutter generates for you, `lib/main.dart`, `pubspec.yaml` for dependencies, and `flutter run`'s hot reload, the single biggest productivity feature in mobile development: change code, save, and see the update on the emulator in under a second.",
                        ],
                        [
                            'title' => 'Widgets and layouts', 'minutes' => 28,
                            'content' => "In Flutter, everything is a widget, text, buttons, padding, even layout itself. This lesson covers the widgets you'll use constantly: `Text`, `Container`, `Row`, `Column`, and the difference between `StatelessWidget` (renders once from its inputs) and `StatefulWidget` (can change itself over time).\n\n```dart\nColumn(\n  children: [\n    Text('Welcome'),\n    ElevatedButton(onPressed: () {}, child: Text('Continue')),\n  ],\n)\n```\n\nBy the end of this lesson you'll be able to compose a real screen layout (header, content, action button) entirely from Flutter's built-in widgets.",
                        ],
                    ]],
                    ['title' => 'Building the app', 'lessons' => [
                        [
                            'title' => 'Navigation between screens', 'minutes' => 22,
                            'content' => "A real app has more than one screen. This lesson covers Flutter's `Navigator`, pushing a new screen onto the stack, passing data to it, and popping back to the previous screen with a result.\n\n```dart\nNavigator.push(\n  context,\n  MaterialPageRoute(builder: (context) => DetailScreen(id: item.id)),\n);\n```\n\nWe build a list-to-detail flow (tap an item, see its details, go back), the single most common navigation pattern in real mobile apps.",
                        ],
                        [
                            'title' => 'State management', 'minutes' => 32,
                            'content' => "As an app grows, keeping track of what changed and re-rendering the right widgets becomes the hard part. This lesson starts with Flutter's built-in `setState` (perfect for a single screen), then introduces the `Provider` package for state that needs to be shared across multiple screens, like a logged-in user or a shopping cart.\n\nWe deliberately start simple: you don't need a complex state-management library for every app, and this lesson is honest about when `setState` is genuinely enough.",
                        ],
                        [
                            'title' => 'Calling a REST API', 'minutes' => 30,
                            'content' => "Most real apps talk to a server. This lesson uses the `http` package to call a real REST API, decode the JSON response into Dart objects, and handle the three states every network call actually has: loading, success, and error.\n\n```dart\nfinal response = await http.get(Uri.parse('\$apiUrl/courses'));\nfinal courses = jsonDecode(response.body);\n```\n\nWe build a real loading spinner and a real error state with a retry button, the difference between an app that feels broken on a bad connection and one that feels reliable.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'MySQL Database Design & Administration',
                'cover' => 'cloud-computing.png',
                'tagline' => 'Design, query and administer relational databases the right way.',
                'description' => 'From your first table to indexing and backups, a practical guide to designing and running MySQL databases for real applications.',
                'level' => 'beginner', 'category' => 'Databases', 'price' => 120000,
                'outcomes' => ['Design normalized relational schemas', 'Write efficient SQL queries and joins', 'Index tables for performance', 'Back up and restore a database'],
                'requirements' => ['No prior database experience needed'],
                'modules' => [
                    ['title' => 'Database design', 'lessons' => [
                        [
                            'title' => 'Tables, rows and relationships', 'minutes' => 16, 'preview' => true,
                            'content' => "A relational database stores data in tables, rows and columns, like a spreadsheet, but with rules connecting tables to each other. This lesson covers the three core relationship types you'll use in almost every schema: one-to-many (one course has many lessons), many-to-many (a student enrolls in many courses, a course has many students), and one-to-one.\n\nWe design a small real schema together (`courses`, `lessons`, `students`, `enrollments`) and draw out the relationships before writing a single line of SQL, because a schema is much cheaper to fix on paper than after it's full of data.",
                        ],
                        [
                            'title' => 'Normalization', 'minutes' => 20,
                            'content' => "Normalization is the process of structuring tables to avoid storing the same fact in two places, the source of most painful data bugs. This lesson covers the practical version of the first three normal forms: no repeating groups, every non-key column depends on the whole key, and nothing depends on a column that isn't the key.\n\nRather than memorizing definitions, we take a badly-designed table full of duplicated data and normalize it step by step, so you can recognize the smell of a bad schema in your own projects.",
                        ],
                    ]],
                    ['title' => 'SQL in practice', 'lessons' => [
                        [
                            'title' => 'Selects, joins and subqueries', 'minutes' => 30,
                            'content' => "This is the SQL you'll write every single day. We cover `SELECT` with `WHERE`/`ORDER BY`/`LIMIT`, the four join types (`INNER`, `LEFT`, `RIGHT`, and when you'd actually reach for each), and subqueries for questions a single join can't answer cleanly.\n\n```sql\nSELECT students.name, COUNT(enrollments.id) AS course_count\nFROM students\nLEFT JOIN enrollments ON enrollments.student_id = students.id\nGROUP BY students.id;\n```\n\nEvery example runs against the schema from the design module, so the queries answer real questions (\"which students haven't enrolled in anything?\") rather than testing abstract syntax.",
                        ],
                        [
                            'title' => 'Indexes and performance', 'minutes' => 24,
                            'content' => "A query that's fast on 100 rows can be unusably slow on 10 million, indexes are how you fix that. This lesson covers what an index actually is (a sorted lookup structure, not magic), when to add one (columns you filter or join on frequently), and when not to (indexes slow down writes and cost storage).\n\nWe use `EXPLAIN` to see how MySQL actually executes a query before and after adding an index, so the improvement isn't just claimed, it's measured.",
                        ],
                        [
                            'title' => 'Backups and restores', 'minutes' => 18,
                            'content' => "A database without backups is one mistake away from disaster. This closing lesson covers `mysqldump` for logical backups, restoring from a dump file, and the practical backup schedule a small production app actually needs, not an enterprise disaster-recovery plan, just a reliable, boring routine that actually runs.\n\nWe deliberately practice a full restore during the lesson, not just a backup. A backup you've never restored from is not a backup you can trust.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'React for Beginners',
                'cover' => 'web-development.png',
                'tagline' => 'Build fast, interactive user interfaces with React.',
                'description' => 'Learn React by building real components (hooks, props, state and connecting to an API) the way modern front-ends are actually built.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 130000,
                'outcomes' => ['Build reusable components with props', 'Manage state with hooks', 'Handle forms and events', 'Fetch and display data from an API'],
                'requirements' => ['Comfortable with HTML, CSS and JavaScript basics'],
                'modules' => [
                    ['title' => 'React fundamentals', 'lessons' => [
                        [
                            'title' => 'Components and props', 'minutes' => 18, 'preview' => true,
                            'content' => "React apps are built from components, small, reusable pieces of UI that take inputs (called props) and return what should appear on screen. This lesson builds a `CourseCard` component that accepts a title and price as props, then reuses it to render a whole list of different courses from one piece of code.\n\n```jsx\nfunction CourseCard({ title, price }) {\n  return <div className=\"card\"><h3>{title}</h3><p>{price}</p></div>;\n}\n```\n\nWe cover JSX (writing HTML-like markup inside JavaScript) and why breaking a page into small components makes it dramatically easier to reason about and reuse.",
                        ],
                        [
                            'title' => 'State and hooks', 'minutes' => 26,
                            'content' => "Props flow into a component; state is what a component remembers about itself. This lesson introduces the `useState` hook (React's mechanism for a component to hold and update its own data) by building a real, working counter and a toggleable dropdown.\n\n```jsx\nconst [count, setCount] = useState(0);\n<button onClick={() => setCount(count + 1)}>{count}</button>\n```\n\nWe cover why you never mutate state directly and always go through its setter function, the single most common beginner mistake in React, and the source of bugs that seem to make no sense until you understand why.",
                        ],
                    ]],
                    ['title' => 'Building an app', 'lessons' => [
                        [
                            'title' => 'Forms and events', 'minutes' => 22,
                            'content' => "Forms in React work a little differently than plain HTML, React usually \"controls\" the input's value through state, so the UI and the underlying data never disagree. This lesson builds a real controlled form (name, email, message) with live validation feedback as the user types.\n\n```jsx\n<input value={email} onChange={(e) => setEmail(e.target.value)} />\n```\n\nWe also cover handling form submission properly, preventing the default page reload, and giving the user clear feedback while a submission is in progress.",
                        ],
                        [
                            'title' => 'Fetching data from an API', 'minutes' => 28,
                            'content' => "Real apps load real data. This closing lesson uses the `useEffect` hook to fetch data from an API when a component first appears, store it in state, and render loading/error/success states properly. The same three states every network request needs, in React this time instead of Flutter.\n\n```jsx\nuseEffect(() => {\n  fetch('/api/courses').then(res => res.json()).then(setCourses);\n}, []);\n```\n\nBy the end you'll have built a real course listing page powered by live data, not a hardcoded array.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'REST APIs with Laravel',
                'cover' => 'programming.png',
                'tagline' => 'Design and build production-ready APIs with Laravel and Sanctum.',
                'description' => 'A focused, advanced course on API design, authentication, resource responses, rate limiting and versioning, built the way real production APIs are built.',
                'level' => 'advanced', 'category' => 'Web Development', 'price' => 100000,
                'outcomes' => ['Design consistent, versioned API endpoints', 'Authenticate requests with Sanctum tokens', 'Shape responses with API resources', 'Rate-limit and secure public endpoints'],
                'requirements' => ['Solid PHP and Laravel fundamentals'],
                'modules' => [
                    ['title' => 'API design', 'lessons' => [
                        [
                            'title' => 'REST principles and versioning', 'minutes' => 18, 'preview' => true,
                            'content' => "A good API is predictable, the same shape and conventions everywhere, so a consumer can guess how an endpoint behaves before reading its docs. This lesson covers REST fundamentals (resources, HTTP verbs mapping to actions, meaningful status codes) and why versioning your API from day one (`/api/v1/...`) saves you from breaking every existing client the first time you need to change something.\n\nWe design a small, consistent endpoint set for a courses resource together, applying the same conventions you'd want from any API you consume yourself.",
                        ],
                        [
                            'title' => 'API resources and responses', 'minutes' => 24,
                            'content' => "Never return a raw Eloquent model from an API endpoint. It leaks internal columns and makes your response shape hostage to your database schema. This lesson covers Laravel API Resources, which give you full control over exactly what a response contains.\n\n```php\nclass CourseResource extends JsonResource {\n    public function toArray(\$request) {\n        return ['id' => \$this->id, 'title' => \$this->title, 'price' => \$this->price];\n    }\n}\n```\n\nWe also cover a consistent envelope shape (`{\"data\": ...}` vs. `{\"error\": ...}`) so every consumer of your API can handle success and failure the same way, every time.",
                        ],
                    ]],
                    ['title' => 'Securing the API', 'lessons' => [
                        [
                            'title' => 'Token authentication with Sanctum', 'minutes' => 26,
                            'content' => "Laravel Sanctum issues lightweight API tokens, the standard way to authenticate mobile apps and SPAs against a Laravel backend. This lesson covers issuing a token on login, sending it as a Bearer token on every subsequent request, and protecting routes with the `auth:sanctum` middleware.\n\n```php\nRoute::middleware('auth:sanctum')->get('/user', fn (Request \$r) => \$r->user());\n```\n\nWe also cover token abilities (scoping what a given token is allowed to do) and revoking a token when a user logs out or a device is lost.",
                        ],
                        [
                            'title' => 'Rate limiting and throttling', 'minutes' => 16,
                            'content' => "A public API without rate limiting is an outage waiting to happen. One buggy client (or one bad actor) can take your whole service down. This closing lesson covers Laravel's built-in throttle middleware, setting sensible per-route limits, and returning a proper `429 Too Many Requests` response instead of letting the server fall over.\n\n```php\nRoute::middleware('throttle:60,1')->group(function () { /* ... */ });\n```\n\nWe close with a short checklist for taking an API from \"it works on my machine\" to something you'd trust in front of real traffic.",
                        ],
                    ]],
                ],
            ],
            [
                'title' => 'Git & GitHub for Developers',
                'cover' => 'programming.png',
                'tagline' => 'Version control, branching and collaborating on real projects with Git.',
                'description' => 'Everything you need to use Git and GitHub confidently on a real team, branching, pull requests, merge conflicts and a clean commit history.',
                'level' => 'beginner', 'category' => 'Tools', 'price' => 0,
                'outcomes' => ['Track changes with commits and branches', 'Collaborate through pull requests', 'Resolve merge conflicts', 'Keep a clean, readable commit history'],
                'requirements' => ['No prior experience needed'],
                'modules' => [
                    ['title' => 'Git basics', 'lessons' => [
                        [
                            'title' => 'Commits, branches and history', 'minutes' => 14, 'preview' => true,
                            'content' => "Git tracks every change you make to a project as a series of commits, snapshots you can always go back to. This lesson covers the everyday commands: `git init`, `git add`, `git commit`, and `git log` to see your history, plus branches, lightweight, disposable copies of your project where you can try things safely.\n\n```bash\ngit checkout -b feature/new-header\ngit add .\ngit commit -m \"Add new header component\"\n```\n\nWe cover writing a good commit message (what changed and why, not just \"fixed stuff\"). The habit that makes a project's history actually useful six months later.",
                        ],
                        [
                            'title' => 'Working with remotes', 'minutes' => 12,
                            'content' => "A local Git repository only you can see isn't collaboration. This lesson covers remotes (connecting your local repository to GitHub) and the push/pull cycle that keeps your local copy and the shared copy in sync.\n\n```bash\ngit remote add origin https://github.com/you/project.git\ngit push -u origin main\ngit pull\n```\n\nWe also cover cloning an existing repository and the difference between `fetch` (check what's new, don't apply it yet) and `pull` (fetch and merge in one step).",
                        ],
                    ]],
                    ['title' => 'Working on a team', 'lessons' => [
                        [
                            'title' => 'Pull requests and code review', 'minutes' => 16,
                            'content' => "A pull request is a proposal to merge one branch into another, with a space for discussion before it happens. This lesson walks through opening a real pull request on GitHub, writing a description that helps a reviewer understand *why* a change was made, and responding to review comments.\n\nWe also cover why teams review code at all, it's not about catching every bug, it's a second set of eyes, shared knowledge of the codebase, and a natural checkpoint before anything reaches production.",
                        ],
                        [
                            'title' => 'Resolving merge conflicts', 'minutes' => 18,
                            'content' => "A merge conflict happens when Git can't automatically combine two changes to the same lines, it's normal, not a sign something went wrong. This closing lesson deliberately creates a real conflict, then walks through resolving it calmly: reading the conflict markers, deciding which change (or both) should survive, and completing the merge.\n\n```\n<<<<<<< HEAD\nconst title = 'Welcome!';\n=======\nconst title = 'Hello there!';\n>>>>>>> feature/new-header\n```\n\nBy the end, a merge conflict will feel like a routine five-minute task instead of something to panic about.",
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
                        'min_active_seconds' => $lessonDefinition['min_seconds'] ?? null,
                        'sort_order' => $lessonIndex,
                        'is_published' => true,
                        'is_free_preview' => $lessonDefinition['preview'] ?? false,
                    ]
                );
            }
        }

        $this->pruneStaleContent($course, $definition);
        $this->seedQuiz($course, $definition['quiz'] ?? null);
        $this->seedAssignment($course, $definition['assignment'] ?? null);
    }

    /**
     * Soft-delete lessons/modules that the (re-run) definition no longer contains,
     * without this, renaming a lesson in the definition would leave the old copy
     * alongside the new one forever. Soft deletes keep any student progress rows.
     *
     * @param  array<string,mixed>  $definition
     */
    private function pruneStaleContent(Course $course, array $definition): void
    {
        $moduleTitles = collect($definition['modules'])->pluck('title');
        $lessonTitlesByModule = collect($definition['modules'])->mapWithKeys(
            fn ($m) => [$m['title'] => collect($m['lessons'])->pluck('title')]
        );

        foreach ($course->modules()->get() as $module) {
            if (! $moduleTitles->contains($module->title)) {
                $module->lessons()->delete();
                $module->delete();

                continue;
            }
            $module->lessons()->whereNotIn('title', $lessonTitlesByModule[$module->title])->delete();
        }
    }

    /** @param array<string,mixed>|null $quizDefinition */
    private function seedQuiz(Course $course, ?array $quizDefinition): void
    {
        if ($quizDefinition === null) {
            return;
        }

        $lesson = $this->lessonByTitle($course, $quizDefinition['attach_to']);

        $quiz = $course->quizzes()->updateOrCreate(
            ['title' => $quizDefinition['title']],
            [
                'lesson_id' => $lesson?->id,
                'description' => $quizDefinition['description'] ?? null,
                'pass_percent' => $quizDefinition['pass_percent'] ?? 70,
                'max_attempts' => 3,
                'grading_method' => 'highest',
                'feedback_mode' => 'after_submit',
                'is_required' => $quizDefinition['required'] ?? false,
                'is_published' => true,
            ]
        );

        foreach ($quizDefinition['questions'] as $index => $questionDefinition) {
            $question = $quiz->questions()->updateOrCreate(
                ['prompt' => $questionDefinition['prompt']],
                [
                    'type' => QuestionType::from($questionDefinition['type']),
                    'points' => $questionDefinition['points'],
                    'sort_order' => $index,
                    'meta' => isset($questionDefinition['accepted']) ? ['accepted_answers' => $questionDefinition['accepted']] : null,
                ]
            );

            foreach ($questionDefinition['options'] ?? [] as $optionIndex => [$label, $isCorrect]) {
                $question->options()->updateOrCreate(
                    ['label' => $label],
                    ['is_correct' => $isCorrect, 'sort_order' => $optionIndex]
                );
            }
        }
    }

    /** @param array<string,mixed>|null $assignmentDefinition */
    private function seedAssignment(Course $course, ?array $assignmentDefinition): void
    {
        if ($assignmentDefinition === null) {
            return;
        }

        $lesson = $this->lessonByTitle($course, $assignmentDefinition['attach_to']);

        $course->assignments()->updateOrCreate(
            ['title' => $assignmentDefinition['title']],
            [
                'lesson_id' => $lesson?->id,
                'instructions' => $assignmentDefinition['instructions'],
                'points' => $assignmentDefinition['points'],
                'allowed_types' => $assignmentDefinition['allowed_types'],
                'is_required' => $assignmentDefinition['required'] ?? false,
                'is_published' => true,
            ]
        );
    }

    private function lessonByTitle(Course $course, string $title): ?\App\Models\Lesson
    {
        return \App\Models\Lesson::whereIn('course_module_id', $course->modules()->pluck('id'))
            ->where('title', $title)->first();
    }
}
