<?php

namespace App\Console\Commands;

use App\Enums\QuizFeedbackMode;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Move the prepared Bootstrap module checks into the existing graded activity system. */
class SyncBootstrapSystemActivities extends Command
{
    protected $signature = 'courses:sync-bootstrap-activities
                            {--path= : Practice-ground content folder}
                            {--rebuild-manifest : Re-read every quiz from the practice-ground HTML and rewrite the manifest first}';

    protected $description = 'Import Bootstrap module quizzes and the final project as system activities';

    public function handle(): int
    {
        $root = $this->option('path') ?: base_path('course-content/bootstrap-practice-ground');
        $course = Course::where('slug', 'ai-powered-frontend-development-with-bootstrap')->first();
        if (! $course) {
            $this->error('The Bootstrap course is not present yet.');

            return self::FAILURE;
        }

        $sourceQuizzes = [];
        $manifestPath = database_path('seeders/data/bootstrap-quizzes.json');

        /*
         * The manifest is a cache of the quiz pages, and a cache of generated
         * files goes stale the first time those files are regenerated. It did:
         * a module was rewritten, the manifest still held the old module's
         * questions, and the import produced two quizzes with the same name and
         * none at all for the new module. --rebuild-manifest re-reads the HTML
         * and rewrites it, which is what to run after any module changes.
         */
        if ($this->option('rebuild-manifest')) {
            $rebuilt = $this->readQuizzesFromHtml($root);
            $existing = is_file($manifestPath)
                ? collect(json_decode((string) file_get_contents($manifestPath), true) ?: [])->keyBy('lesson_title')
                : collect();

            /*
             * Merged, never replaced wholesale, and only with quizzes that
             * actually parsed. Not every page in the folder was written by the
             * current generator: the older modules use markup this parser
             * cannot read, and a straight overwrite turned nine good quizzes
             * into nine empty ones in the file while the database still held
             * the real questions. A partial parse must not be able to delete
             * work that parsed fine last time.
             */
            $dropped = [];

            foreach ($rebuilt as $quiz) {
                if (count($quiz['questions']) < 5) {
                    $dropped[] = $quiz['lesson_title'];

                    continue;
                }

                $existing[$quiz['lesson_title']] = $quiz;
            }

            $merged = $existing->sortKeys()->values()->all();
            file_put_contents($manifestPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

            $this->info('Manifest now holds '.count($merged).' quizzes.');

            if ($dropped !== []) {
                $this->warn('Kept the stored version of these, because the page did not parse: '.implode(', ', $dropped));
            }
        }

        if (is_file($manifestPath)) {
            $sourceQuizzes = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        } else {
            $sourceQuizzes = $this->readQuizzesFromHtml($root);
        }

        $imported = 0;
        foreach ($sourceQuizzes as $sourceQuiz) {
            $title = (string) ($sourceQuiz['lesson_title'] ?? '');
            $lesson = Lesson::whereHas('module', fn ($query) => $query->where('course_id', $course->id))
                ->where('title', $title)->first();
            if (! $lesson) {
                $this->warn('Skipped; matching lesson not found: '.$title);

                continue;
            }

            $parsed = $sourceQuiz['questions'] ?? [];
            if (count($parsed) < 5) {
                $this->warn('Skipped; quiz source has fewer than five complete questions: '.$title);

                continue;
            }

            DB::transaction(function () use ($course, $lesson, $parsed): void {
                $quiz = Quiz::withTrashed()->firstOrNew(['course_id' => $course->id, 'lesson_id' => $lesson->id]);
                if ($quiz->trashed()) {
                    $quiz->restore();
                }
                if ($quiz->exists && ($quiz->attempts()->exists() || $quiz->questions()->exists())) {
                    return;
                }
                $quiz->fill([
                    'title' => $lesson->title,
                    'description' => 'Choose the best answer for each question. Your answers are saved and scored in the course.',
                    'time_limit_minutes' => 15,
                    'max_attempts' => 3,
                    'pass_percent' => 70,
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'questions_per_attempt' => null,
                    'one_question_per_page' => true,
                    'feedback_mode' => QuizFeedbackMode::AfterSubmit,
                    'is_required' => true,
                    'is_published' => true,
                ])->save();

                foreach ($parsed as $index => $row) {
                    $question = $quiz->questions()->create([
                        'type' => 'mcq_single', 'prompt' => $row['prompt'], 'explanation' => $row['explanation'],
                        'points' => 1, 'sort_order' => $index + 1,
                    ]);
                    foreach ($row['options'] as $optionIndex => $option) {
                        $question->options()->create([
                            'label' => $option['label'], 'is_correct' => $option['key'] === $row['answer'],
                            'sort_order' => $optionIndex + 1,
                        ]);
                    }
                }
            });
            $imported++;
            $this->line('Imported '.$lesson->title.' ('.count($parsed).' questions).');
        }

        $this->syncFinalProject($course);
        $this->info("Processed {$imported} module quizzes and the final project assignment.");

        return self::SUCCESS;
    }

    /** @return list<array{prompt:string, answer:string, explanation:string, options:list<array{key:string,label:string}>}> */
    /**
     * Every "Module NN quiz" page under the practice ground, in module order.
     *
     * @return list<array{lesson_title:string, questions:array}>
     */
    private function readQuizzesFromHtml(string $root): array
    {
        $found = [];

        foreach (glob($root.'/*/*.html') ?: [] as $path) {
            if (! preg_match('/^(\d+)\.(\d+)\s+-\s+BOOTSTRAP\s+-\s+Module\s+\d+\s+quiz\.html$/i', basename($path), $match)) {
                continue;
            }

            $found[(int) $match[1]] = [
                'lesson_title' => 'Module '.str_pad($match[1], 2, '0', STR_PAD_LEFT).' quiz',
                'questions' => $this->parseQuiz((string) file_get_contents($path)),
            ];
        }

        ksort($found);

        return array_values($found);
    }

    private function parseQuiz(string $html): array
    {
        $dom = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($dom);
        $answerRows = [];
        $detailItems = $xpath->query('//main//details//ol/li');
        if ($detailItems) {
            foreach ($detailItems as $index => $item) {
                $strong = $xpath->query('.//strong[1]', $item)?->item(0);
                $lead = $strong?->textContent ?? '';
                preg_match('/^\s*([a-d])\s*\./i', $lead, $key);
                $answerRows[$index] = [
                    'answer' => strtolower($key[1] ?? ''),
                    'explanation' => trim(preg_replace('/^\s*[a-d]\s*\.\s*/i', '', $item->textContent) ?? ''),
                ];
            }
        }

        $parsed = [];
        $questionItems = $xpath->query('//main//ol[not(ancestor::details)]/li[.//ul]');
        if ($questionItems) {
            foreach ($questionItems as $index => $item) {
                $promptNode = $xpath->query('.//strong[1]', $item)?->item(0);
                $optionsNode = $xpath->query('.//ul[1]', $item)?->item(0);
                if (! $promptNode || ! $optionsNode) {
                    continue;
                }
                $options = [];
                foreach ($xpath->query('./li', $optionsNode) ?: [] as $optionNode) {
                    $text = trim($optionNode->textContent);
                    if (preg_match('/^([a-d])\)\s*(.*)$/is', $text, $optionMatch)) {
                        $options[] = ['key' => strtolower($optionMatch[1]), 'label' => trim($optionMatch[2])];
                    }
                }
                $answer = $answerRows[$index] ?? null;
                if (count($options) === 4 && $answer && collect($options)->contains('key', $answer['answer'])) {
                    $parsed[] = [
                        'prompt' => trim($promptNode->textContent),
                        'answer' => $answer['answer'],
                        'explanation' => $answer['explanation'],
                        'options' => $options,
                    ];
                }
            }
        }

        return $parsed;
    }

    private function syncFinalProject(Course $course): void
    {
        $lesson = Lesson::whereHas('module', fn ($query) => $query->where('course_id', $course->id))
            ->where('title', 'like', '%What you can do now%')->first();
        if (! $lesson) {
            return;
        }

        // Renamed with the module: the final project is the Kestrel Ridge site
        // the learner directs an assistant to build across module 11, not the
        // Marigold storefront, which is the worked example from modules 1 to 9.
        Assignment::withTrashed()
            ->where('course_id', $course->id)
            ->where('title', 'Final project: Marigold Stores website')
            ->forceDelete();

        Assignment::updateOrCreate(
            ['course_id' => $course->id, 'title' => 'Final project: Kestrel Ridge Secondary School website'],
            [
                'lesson_id' => $lesson->id,
                'instructions' => "Build the complete Kestrel Ridge Secondary School website by directing an AI assistant, following lessons 11.1 to 11.5. Submit a live GitHub Pages address, or a ZIP if you cannot publish.\n\n**The site must include:**\n- index.html, about.html, news.html, article.html and admissions.html, all sharing one header and footer with the correct active link on each.\n- admin/login.html, which has no navbar and no sidebar, plus admin/index.html, admin/news-list.html and admin/news-form.html sharing one admin shell.\n- The navy and gold theme applied through component variables, with no Bootstrap blue left anywhere, and a working dark mode.\n- An admissions form with a label on every field, helpful validation messages, and a sensible tab order you can use with the keyboard alone.\n\n**Also submit:**\n- Your written page plan from lesson 11.1, showing which pages share which layout.\n- Your .github/copilot-instructions.md.\n- notes/ai-log.md with at least eight entries. Each one says what you asked for, what came back, what was wrong with it and how you caught it. Entries that only say it worked score nothing.\n- Lighthouse Accessibility screenshots of 90 or more for index.html, admissions.html and admin/index.html.\n\n**Before you submit:** open every page at 360, 768 and 1280 pixels in both colour modes, click every link, and clear the browser console. You must be able to explain any file in the project on request. Do not include passwords, real personal details or private API keys.",
                'points' => 100,
                'allowed_types' => 'text,link,zip',
                'max_file_mb' => 20,
                'is_required' => true,
                'resubmit_until_graded' => true,
                'is_published' => true,
            ],
        );
    }
}
