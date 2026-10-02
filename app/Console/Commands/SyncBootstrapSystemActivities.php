<?php

namespace App\Console\Commands;

use App\Enums\QuizFeedbackMode;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuestionOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Move the prepared Bootstrap module checks into the existing graded activity system. */
class SyncBootstrapSystemActivities extends Command
{
    protected $signature = 'courses:sync-bootstrap-activities {--path= : Practice-ground content folder}';

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
        if (is_file($manifestPath)) {
            $sourceQuizzes = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        } else {
            foreach (glob($root.'/*/*.html') ?: [] as $path) {
                if (! preg_match('/^(\d+)\.(\d+)\s+-\s+BOOTSTRAP\s+-\s+Module\s+\d+\s+quiz\.html$/i', basename($path), $match)) continue;
                $moduleNumber = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                $sourceQuizzes[] = [
                    'lesson_title' => 'Module '.$moduleNumber.' quiz',
                    'questions' => $this->parseQuiz((string) file_get_contents($path)),
                ];
            }
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
        if ($questionItems) foreach ($questionItems as $index => $item) {
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
        return $parsed;
    }

    private function syncFinalProject(Course $course): void
    {
        $lesson = Lesson::whereHas('module', fn ($query) => $query->where('course_id', $course->id))
            ->where('title', 'like', '%Final project brief%')->first();
        if (! $lesson) return;

        Assignment::updateOrCreate(
            ['course_id' => $course->id, 'title' => 'Final project: Marigold Stores website'],
            [
                'lesson_id' => $lesson->id,
                'instructions' => "Build and publish the Marigold Stores website from the course lessons. Submit a live link or a ZIP of your project, then write a short note naming the pages you built and one change you made after testing.\n\n**Your project should include:**\n- Home, product, cart and information pages.\n- Account forms and an admin layout.\n- A responsive layout with working Bootstrap components.\n- Clear labels, keyboard focus and readable colour contrast.\n- A published address, if you submit a live link.\n\n**Before you submit:** open every page, test at phone and laptop widths, try the navigation and forms, and fix any browser console errors. Do not include passwords, real customer information or private API keys.",
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
