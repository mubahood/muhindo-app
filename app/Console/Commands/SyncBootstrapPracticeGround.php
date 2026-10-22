<?php

namespace App\Console\Commands;

use App\Enums\ContentFormat;
use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

        // css/, js/ and images/ sit beside the module folders; only a directory
        // that actually holds lesson pages is a module.
        $modules = collect(File::directories($root))
            ->filter(fn ($dir) => collect(File::files($dir))
                ->contains(fn ($f) => in_array(strtolower($f->getExtension()), ['html', 'htm'], true)))
            ->sort(fn ($a, $b) => strnatcasecmp(basename($a), basename($b)))
            ->values();
        if ($modules->isEmpty()) {
            $this->error('No module folders were found.');

            return self::FAILURE;
        }

        $videos = $this->videoManifest($root);

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
        $liveCount = 0;
        $prunedLessons = 0;
        DB::transaction(function () use ($modules, $course, $videos, &$lessonCount, &$liveCount, &$prunedLessons): void {
            $keptModules = [];

            foreach ($modules as $moduleIndex => $modulePath) {
                $moduleTitle = basename($modulePath);
                $module = $course->modules()->updateOrCreate(
                    ['title' => $moduleTitle],
                    ['sort_order' => $moduleIndex],
                );
                $keptModules[] = $moduleTitle;

                $files = collect(File::files($modulePath))
                    ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['html', 'htm'], true))
                    ->sort(fn ($a, $b) => strnatcasecmp($a->getFilename(), $b->getFilename()))
                    ->values();

                $keptLessons = [];

                foreach ($files as $lessonIndex => $file) {
                    $html = File::get($file->getPathname());
                    $title = $this->pageTitle($html)
                        ?: pathinfo($file->getFilename(), PATHINFO_FILENAME);

                    // The leading number in the file name is the key the video
                    // manifest is written against: "4.2.1 - BOOTSTRAP - ...".
                    $number = $this->lessonNumber($file->getFilename());
                    $video = $number !== null ? ($videos[$number] ?? null) : null;

                    $module->lessons()->updateOrCreate(
                        ['title' => $title],
                        [
                            'content' => $html,
                            'content_format' => ContentFormat::Html,
                            'sort_order' => $lessonIndex,
                            // A lesson goes live when its video exists and stays a
                            // draft until then. The manifest is the only authority,
                            // so a re-sync never silently publishes an empty lesson.
                            'is_published' => $video !== null,
                            'video_url' => $video
                                ? 'https://www.youtube-nocookie.com/embed/'.$video['youtube']
                                : null,
                            'duration_minutes' => $video
                                ? max(1, (int) round($video['seconds'] / 60))
                                : null,
                            'is_free_preview' => str_starts_with($file->getFilename(), '0.0'),
                            'completion_rule' => 'manual',
                            'completion_threshold' => 80,
                        ],
                    );

                    $keptLessons[] = $title;
                    $lessonCount++;
                    if ($video !== null) {
                        $liveCount++;
                    }
                }

                // Renumbered or dropped lessons would otherwise linger for ever.
                // get()->each->delete() so the model events and cascades fire.
                $stale = $module->lessons()->whereNotIn('title', $keptLessons)->get();
                $stale->each->delete();
                $prunedLessons += $stale->count();
            }

            $course->modules()->whereNotIn('title', $keptModules)->get()->each->delete();
        });

        $drafts = $lessonCount - $liveCount;
        $this->info("Synced {$modules->count()} modules and {$lessonCount} HTML lessons to {$course->title}.");
        $this->line("{$liveCount} lessons are live with a video; {$drafts} stay drafts until one is recorded.");
        if ($prunedLessons > 0) {
            $this->line("{$prunedLessons} lesson(s) no longer in the source were removed.");
        }
        $this->line('Existing student lessons and progress outside this Bootstrap course were not changed.');

        return self::SUCCESS;
    }

    /**
     * Lesson number -> {youtube, seconds}, authored beside the pages.
     * Absent file means every lesson syncs as a draft, which is the safe default.
     */
    private function videoManifest(string $root): array
    {
        $path = $root.'/_videos.json';
        if (! File::exists($path)) {
            $this->warn('No _videos.json found; every lesson will sync as a draft.');

            return [];
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            $this->warn('_videos.json could not be read; every lesson will sync as a draft.');

            return [];
        }

        return $decoded;
    }

    /** "4.2.1 - BOOTSTRAP - Advanced typography.html" -> "4.2.1" */
    private function lessonNumber(string $filename): ?string
    {
        return preg_match('/^(\d+(?:\.\d+)*)\s*-/', $filename, $m) ? $m[1] : null;
    }

    private function pageTitle(string $html): ?string
    {
        if (! preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $match)) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
    }
}
