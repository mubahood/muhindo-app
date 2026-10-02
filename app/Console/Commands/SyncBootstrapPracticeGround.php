<?php

namespace App\Console\Commands;

use App\Enums\ContentFormat;
use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/** Import the prepared Bootstrap practice pages as the written lessons in the course. */
class SyncBootstrapPracticeGround extends Command
{
    protected $signature = 'courses:sync-bootstrap-practice-ground {--path= : Optional path to a practice-ground folder}';

    protected $description = 'Sync the Bootstrap practice-ground HTML pages into the Bootstrap course';

    public function handle(): int
    {
        $root = $this->option('path') ?: base_path('course-content/bootstrap-practice-ground');
        if (! File::isDirectory($root)) {
            $this->error("Practice-ground folder not found: {$root}");

            return self::FAILURE;
        }

        $modules = collect(File::directories($root))
            ->sort(fn ($a, $b) => strnatcasecmp(basename($a), basename($b)))
            ->values();
        if ($modules->isEmpty()) {
            $this->error('No module folders were found.');

            return self::FAILURE;
        }

        $course = Course::firstOrNew(['slug' => 'ai-powered-frontend-development-with-bootstrap']);
        if (! $course->exists) {
            $course->uuid = (string) Str::uuid();
        }
        $course->fill([
            'title' => 'AI Powered - Frontend Development with Bootstrap',
            'tagline' => 'Build a responsive storefront with Bootstrap 5.3.',
            'description' => 'Start with the CSS Bootstrap uses, add Bootstrap 5.3 and build the Marigold Stores website one clear step at a time. Practise with live examples, readable code, browser checks and short tasks. Use GitHub Copilot as a helper, then check every suggestion before you keep it.',
            'outcomes' => [
                'Add Bootstrap 5.3 and Bootstrap Icons to a website.',
                'Build responsive layouts with containers, the grid, spacing and flex utilities.',
                'Style text, colours, tables and images with Bootstrap classes.',
                'Use Bootstrap components, navigation, overlays and forms.',
                'Check AI suggestions, test accessibility and publish a website with GitHub Pages.',
            ],
            'requirements' => [
                'Basic HTML and CSS, including pages, images, links and forms.',
                'A computer with VS Code and a current web browser.',
                'Internet access. A free GitHub account is needed for the final publishing lessons.',
            ],
            'price' => 60000,
            'currency' => 'UGX',
            'level' => 'beginner',
            'category' => 'Frontend development',
            'course_number' => 2,
            'is_published' => true,
            'progression' => 'sequential',
        ])->save();

        $lessonCount = 0;
        DB::transaction(function () use ($modules, $course, &$lessonCount): void {
            foreach ($modules as $moduleIndex => $modulePath) {
                $moduleTitle = basename($modulePath);
                $module = $course->modules()->updateOrCreate(
                    ['title' => $moduleTitle],
                    ['sort_order' => $moduleIndex],
                );

                $files = collect(File::files($modulePath))
                    ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['html', 'htm'], true))
                    ->sort(fn ($a, $b) => strnatcasecmp($a->getFilename(), $b->getFilename()))
                    ->values();

                foreach ($files as $lessonIndex => $file) {
                    $html = File::get($file->getPathname());
                    $title = $this->pageTitle($html)
                        ?: pathinfo($file->getFilename(), PATHINFO_FILENAME);

                    $module->lessons()->updateOrCreate(
                        ['title' => $title],
                        [
                            'content' => $html,
                            'content_format' => ContentFormat::Html,
                            'sort_order' => $lessonIndex,
                            'is_published' => true,
                            'is_free_preview' => str_starts_with($file->getFilename(), '0.0'),
                            'completion_rule' => 'manual',
                            'completion_threshold' => 80,
                        ],
                    );
                    $lessonCount++;
                }
            }
        });

        $this->info("Synced {$modules->count()} modules and {$lessonCount} HTML lessons to {$course->title}.");
        $this->line('Existing student lessons and progress outside this Bootstrap course were not changed.');

        return self::SUCCESS;
    }

    private function pageTitle(string $html): ?string
    {
        if (! preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $match)) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
    }
}
