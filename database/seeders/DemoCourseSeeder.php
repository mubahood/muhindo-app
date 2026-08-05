<?php

namespace Database\Seeders;

use App\Enums\ContentFormat;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * "a factory + seeder for every new model (demo course with quiz + assignment seeded for
 * local dev)". A demo course with real modules/lessons, a quiz covering several question types,
 * and an assignment is the concretely useful, testable half of that requirement, something a
 * developer can actually run locally to see the LMS with real-ish content in it. Deliberately
 * NOT wired into DatabaseSeeder's default chain (RbacSeeder/AdminUserSeeder/etc. provision a
 * real environment; this is sample content, opt-in via `--class=DemoCourseSeeder`).
 *
 * Safe to re-run. Every write is an upsert keyed on the course slug.
 */
class DemoCourseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('role', 'super_admin')->first() ?? User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $course = Course::updateOrCreate(
            ['slug' => 'demo-laravel-fundamentals'],
            [
                'uuid' => (string) Str::uuid(),
                'title' => 'Laravel Fundamentals (Demo)',
                'description' => 'A sample course seeded for local development, covers the basics of routing, Eloquent, and Blade with a short quiz and a written assignment.',
                'price' => 0,
                'currency' => 'UGX',
                'level' => 'beginner',
                'category' => 'Web Development',
                'is_published' => true,
                'created_by' => $owner->id,
                'progression' => 'sequential',
            ]
        );

        $module = $course->modules()->updateOrCreate(['title' => 'Getting Started'], ['sort_order' => 0]);

        $lessonOne = $module->lessons()->updateOrCreate(
            ['title' => 'Welcome & Routing Basics'],
            [
                'content' => "# Welcome\n\nIn this lesson we cover how Laravel routes an incoming request to a controller action.\n\n- `routes/web.php` for browser-facing routes\n- `routes/api.php` for JSON APIs",
                'content_format' => ContentFormat::Markdown,
                'sort_order' => 0,
                'is_published' => true,
                'is_free_preview' => true,
                'completion_rule' => 'manual',
                'completion_threshold' => 80,
            ]
        );

        $lessonTwo = $module->lessons()->updateOrCreate(
            ['title' => 'Eloquent Models'],
            [
                'content' => "# Eloquent\n\nEloquent is Laravel's ActiveRecord ORM. Each database table gets a corresponding Model for interacting with it.",
                'content_format' => ContentFormat::Markdown,
                'sort_order' => 1,
                'is_published' => true,
                'completion_rule' => 'manual',
                'completion_threshold' => 80,
            ]
        );

        $quiz = $course->quizzes()->updateOrCreate(
            ['title' => 'Fundamentals Check'],
            [
                'lesson_id' => $lessonTwo->id,
                'description' => 'A short check on what you just read.',
                'pass_percent' => 70,
                'max_attempts' => 3,
                'grading_method' => 'highest',
                'feedback_mode' => 'after_submit',
                'counts_toward_certificate' => true,
                'is_published' => true,
            ]
        );

        $mcq = $quiz->questions()->updateOrCreate(
            ['prompt' => 'Which file registers browser-facing routes by default?'],
            ['type' => QuestionType::McqSingle, 'points' => 1, 'sort_order' => 0]
        );
        $mcq->options()->updateOrCreate(['label' => 'routes/web.php'], ['is_correct' => true, 'sort_order' => 0]);
        $mcq->options()->updateOrCreate(['label' => 'routes/api.php'], ['is_correct' => false, 'sort_order' => 1]);
        $mcq->options()->updateOrCreate(['label' => 'routes/console.php'], ['is_correct' => false, 'sort_order' => 2]);

        $trueFalse = $quiz->questions()->updateOrCreate(
            ['prompt' => 'Eloquent is an implementation of the ActiveRecord pattern.'],
            ['type' => QuestionType::TrueFalse, 'points' => 1, 'sort_order' => 1]
        );
        $trueFalse->options()->updateOrCreate(['label' => 'True'], ['is_correct' => true, 'sort_order' => 0]);
        $trueFalse->options()->updateOrCreate(['label' => 'False'], ['is_correct' => false, 'sort_order' => 1]);

        $quiz->questions()->updateOrCreate(
            ['prompt' => 'In your own words, explain what a Laravel route does.'],
            ['type' => QuestionType::Essay, 'points' => 3, 'sort_order' => 2]
        );

        $course->assignments()->updateOrCreate(
            ['title' => 'Build a Simple Route'],
            [
                'lesson_id' => $lessonOne->id,
                'instructions' => 'Write a short paragraph describing a route you would add to a small blog app, including its URI, HTTP verb, and controller action.',
                'points' => 20,
                'allow_late' => true,
                'late_penalty_percent' => 10,
                'max_file_mb' => 10,
                'allowed_types' => 'text',
                'is_published' => true,
            ]
        );

        $this->command->info('DemoCourseSeeder: "Laravel Fundamentals (Demo)" course with 1 module, 2 lessons, a 3-question quiz, and 1 assignment.');
    }
}
