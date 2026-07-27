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
                'tagline' => 'HTML, CSS and JavaScript from zero — build and publish your first real web page.',
                'description' => 'A practical start for anyone who has never written a line of code. You will build a real, working website from scratch — no prior experience needed.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 0,
                'outcomes' => ['Structure a web page with semantic HTML', 'Style pages with modern CSS (flexbox, grid)', 'Add interactivity with JavaScript', 'Publish a site for free on GitHub Pages'],
                'requirements' => ['A computer with internet access', 'No prior programming experience needed'],
                'modules' => [
                    ['title' => 'Getting Started', 'lessons' => [
                        ['title' => 'How the web works', 'minutes' => 12, 'preview' => true],
                        ['title' => 'Setting up your code editor', 'minutes' => 8],
                    ]],
                    ['title' => 'HTML & CSS', 'lessons' => [
                        ['title' => 'Your first HTML page', 'minutes' => 18],
                        ['title' => 'Styling with CSS', 'minutes' => 25],
                        ['title' => 'Layouts with flexbox and grid', 'minutes' => 30],
                    ]],
                    ['title' => 'JavaScript basics', 'lessons' => [
                        ['title' => 'Variables, functions and events', 'minutes' => 28],
                        ['title' => 'Publishing your site', 'minutes' => 15],
                    ]],
                ],
            ],
            [
                'title' => 'Laravel From Scratch',
                'tagline' => 'Build real, database-backed web applications with Laravel and PHP.',
                'description' => 'A project-based course covering routing, Eloquent, Blade, authentication and deployment — everything you need to build and ship a real Laravel application.',
                'level' => 'intermediate', 'category' => 'Web Development', 'price' => 150000,
                'outcomes' => ['Build CRUD applications with Eloquent and Blade', 'Handle authentication and authorization', 'Write and run automated tests', 'Deploy a Laravel app to a live server'],
                'requirements' => ['Basic PHP knowledge', 'Basic HTML/CSS'],
                'modules' => [
                    ['title' => 'Laravel essentials', 'lessons' => [
                        ['title' => 'Routing and controllers', 'minutes' => 20, 'preview' => true],
                        ['title' => 'Blade templates', 'minutes' => 22],
                        ['title' => 'Eloquent models and migrations', 'minutes' => 35],
                    ]],
                    ['title' => 'Building a real app', 'lessons' => [
                        ['title' => 'CRUD from scratch', 'minutes' => 40],
                        ['title' => 'Authentication with Breeze', 'minutes' => 30],
                        ['title' => 'Form validation and error handling', 'minutes' => 25],
                    ]],
                    ['title' => 'Shipping it', 'lessons' => [
                        ['title' => 'Testing with PHPUnit', 'minutes' => 28],
                        ['title' => 'Deploying to a live server', 'minutes' => 24],
                    ]],
                ],
            ],
            [
                'title' => 'Flutter Mobile App Development',
                'tagline' => 'Build native Android and iOS apps from one Dart codebase.',
                'description' => 'Learn Flutter and Dart by building a real, working mobile app — widgets, navigation, state management and connecting to a live API.',
                'level' => 'intermediate', 'category' => 'Mobile Development', 'price' => 200000,
                'outcomes' => ['Build responsive UIs with Flutter widgets', 'Manage app state', 'Navigate between screens', 'Consume a REST API from a mobile app'],
                'requirements' => ['Basic programming knowledge (any language)', 'A computer able to run Android Studio'],
                'modules' => [
                    ['title' => 'Flutter basics', 'lessons' => [
                        ['title' => 'Setting up Flutter', 'minutes' => 15, 'preview' => true],
                        ['title' => 'Widgets and layouts', 'minutes' => 28],
                    ]],
                    ['title' => 'Building the app', 'lessons' => [
                        ['title' => 'Navigation between screens', 'minutes' => 22],
                        ['title' => 'State management', 'minutes' => 32],
                        ['title' => 'Calling a REST API', 'minutes' => 30],
                    ]],
                ],
            ],
            [
                'title' => 'MySQL Database Design & Administration',
                'tagline' => 'Design, query and administer relational databases the right way.',
                'description' => 'From your first table to indexing and backups — a practical guide to designing and running MySQL databases for real applications.',
                'level' => 'beginner', 'category' => 'Databases', 'price' => 120000,
                'outcomes' => ['Design normalized relational schemas', 'Write efficient SQL queries and joins', 'Index tables for performance', 'Back up and restore a database'],
                'requirements' => ['No prior database experience needed'],
                'modules' => [
                    ['title' => 'Database design', 'lessons' => [
                        ['title' => 'Tables, rows and relationships', 'minutes' => 16, 'preview' => true],
                        ['title' => 'Normalization', 'minutes' => 20],
                    ]],
                    ['title' => 'SQL in practice', 'lessons' => [
                        ['title' => 'Selects, joins and subqueries', 'minutes' => 30],
                        ['title' => 'Indexes and performance', 'minutes' => 24],
                        ['title' => 'Backups and restores', 'minutes' => 18],
                    ]],
                ],
            ],
            [
                'title' => 'React for Beginners',
                'tagline' => 'Build fast, interactive user interfaces with React.',
                'description' => 'Learn React by building real components — hooks, props, state and connecting to an API — the way modern front-ends are actually built.',
                'level' => 'beginner', 'category' => 'Web Development', 'price' => 130000,
                'outcomes' => ['Build reusable components with props', 'Manage state with hooks', 'Handle forms and events', 'Fetch and display data from an API'],
                'requirements' => ['Comfortable with HTML, CSS and JavaScript basics'],
                'modules' => [
                    ['title' => 'React fundamentals', 'lessons' => [
                        ['title' => 'Components and props', 'minutes' => 18, 'preview' => true],
                        ['title' => 'State and hooks', 'minutes' => 26],
                    ]],
                    ['title' => 'Building an app', 'lessons' => [
                        ['title' => 'Forms and events', 'minutes' => 22],
                        ['title' => 'Fetching data from an API', 'minutes' => 28],
                    ]],
                ],
            ],
            [
                'title' => 'REST APIs with Laravel',
                'tagline' => 'Design and build production-ready APIs with Laravel and Sanctum.',
                'description' => 'A focused, advanced course on API design — authentication, resource responses, rate limiting and versioning, built the way real production APIs are built.',
                'level' => 'advanced', 'category' => 'Web Development', 'price' => 100000,
                'outcomes' => ['Design consistent, versioned API endpoints', 'Authenticate requests with Sanctum tokens', 'Shape responses with API resources', 'Rate-limit and secure public endpoints'],
                'requirements' => ['Solid PHP and Laravel fundamentals'],
                'modules' => [
                    ['title' => 'API design', 'lessons' => [
                        ['title' => 'REST principles and versioning', 'minutes' => 18, 'preview' => true],
                        ['title' => 'API resources and responses', 'minutes' => 24],
                    ]],
                    ['title' => 'Securing the API', 'lessons' => [
                        ['title' => 'Token authentication with Sanctum', 'minutes' => 26],
                        ['title' => 'Rate limiting and throttling', 'minutes' => 16],
                    ]],
                ],
            ],
            [
                'title' => 'Git & GitHub for Developers',
                'tagline' => 'Version control, branching and collaborating on real projects with Git.',
                'description' => 'Everything you need to use Git and GitHub confidently on a real team — branching, pull requests, merge conflicts and a clean commit history.',
                'level' => 'beginner', 'category' => 'Tools', 'price' => 0,
                'outcomes' => ['Track changes with commits and branches', 'Collaborate through pull requests', 'Resolve merge conflicts', 'Keep a clean, readable commit history'],
                'requirements' => ['No prior experience needed'],
                'modules' => [
                    ['title' => 'Git basics', 'lessons' => [
                        ['title' => 'Commits, branches and history', 'minutes' => 14, 'preview' => true],
                        ['title' => 'Working with remotes', 'minutes' => 12],
                    ]],
                    ['title' => 'Working on a team', 'lessons' => [
                        ['title' => 'Pull requests and code review', 'minutes' => 16],
                        ['title' => 'Resolving merge conflicts', 'minutes' => 18],
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
                        'content' => "# {$lessonDefinition['title']}\n\nLesson content for **{$course->title}**.",
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
