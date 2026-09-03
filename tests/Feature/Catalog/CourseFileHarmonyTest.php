<?php

namespace Tests\Feature\Catalog;

use App\Services\Catalog\CourseFileParser;
use App\Services\Catalog\CourseFileScanner;
use Tests\TestCase;

/**
 * The authored course files, read the way the importer reads them.
 *
 * No database: these are assertions about the files themselves, which is where
 * the mistakes are. The parser fails silently by design, so a malformed line
 * does not error, it quietly drops a description or swallows a heading, and
 * nobody finds out until a student opens the topic.
 */
class CourseFileHarmonyTest extends TestCase
{
    private const DIR = __DIR__.'/../../../course-content';

    /**
     * The same scanner the import command uses, rather than a glob of its own.
     *
     * A private glob here would drift from what actually gets imported, and it
     * did: it picked up _link-report.md, which is a report about the courses
     * and not a course.
     *
     * @return list<string>
     */
    private function files(): array
    {
        return (new CourseFileScanner)->files(self::DIR)->all();
    }

    private function parse(string $path): array
    {
        return (new CourseFileParser)->parse($path);
    }

    /**
     * The em dash is load-bearing in three places, and only one of them
     * announces itself when it is missing. A lesson line written with a hyphen
     * still imports, still gets a title, and silently loses its description.
     */
    public function test_every_lesson_in_every_course_keeps_its_description(): void
    {
        foreach ($this->files() as $path) {
            $course = $this->parse($path);

            foreach ($course['modules'] as $module) {
                foreach ($module['lessons'] as $lesson) {
                    $hasText = trim(implode('', $lesson['body'])) !== '';
                    $hasVideo = $lesson['video_id'] !== null;

                    $this->assertTrue($hasText || $hasVideo, sprintf(
                        '%s: "%s" has neither a description nor a video. Check the em dash.',
                        basename($path), $lesson['title'],
                    ));
                }
            }
        }
    }

    public function test_every_course_file_yields_a_complete_course(): void
    {
        foreach ($this->files() as $path) {
            $course = $this->parse($path);
            $name = basename($path);

            $this->assertNotEmpty($course['title'], "{$name}: no title, so the heading did not match");
            $this->assertNotNull($course['course_number'], "{$name}: no course number");
            $this->assertNotNull($course['tier'], "{$name}: no tier, so the meta line did not match");
            $this->assertNotEmpty($course['modules'], "{$name}: no modules");
            $this->assertNotEmpty($course['description'], "{$name}: no description");

            foreach ($course['modules'] as $module) {
                $this->assertNotEmpty($module['lessons'], "{$name}: module \"{$module['title']}\" is empty");
                // "Module 4 — Name" must lose its prefix, or the sidebar reads
                // "Module 4 — Module 4 — Name" once the number is added back.
                $this->assertDoesNotMatchRegularExpression('/^(Module|Phase|Project)\s+\d/', $module['title'],
                    "{$name}: module title still carries its own number");
            }
        }
    }

    public function test_no_two_courses_claim_the_same_number(): void
    {
        $numbers = array_map(fn ($p) => $this->parse($p)['course_number'], $this->files());

        $this->assertSame(array_unique($numbers), $numbers, 'two files share a course number');
    }

    /**
     * "Continue to" sits after the final project in every file, so it was being
     * read as the last paragraph of the brief a student is marked against.
     */
    public function test_the_assignment_brief_is_not_polluted_by_what_follows_it(): void
    {
        foreach ($this->files() as $path) {
            $course = $this->parse($path);
            if (empty($course['assignment'])) {
                continue;
            }

            $body = $course['assignment']['body'];
            $name = basename($path);

            $this->assertStringNotContainsString('Continue to:', $body, "{$name}: navigation leaked into the brief");
            $this->assertStringNotContainsString('Quiz ideas', $body, "{$name}: the quiz brief leaked into the brief");
            $this->assertStringNotContainsString("\n---", $body, "{$name}: a horizontal rule leaked into the brief");
        }
    }

    /** A quiz brief that wraps onto a second line must arrive whole. */
    public function test_a_wrapped_quiz_brief_is_captured_completely(): void
    {
        $course = $this->parse(self::DIR.'/10-laravel-essentials.md');

        $this->assertStringContainsString('route → controller → view ordering', $course['quiz_brief']);
        $this->assertStringContainsString('find the N+1 query', $course['quiz_brief'], 'the second line was dropped');
    }

    /* Course 22 ------------------------------------------------------------ */

    public function test_course_22_is_shaped_the_way_the_catalogue_says(): void
    {
        $course = $this->parse(self::DIR.'/22-microsoft-office-mastery.md');

        $this->assertSame(22, $course['course_number']);
        $this->assertSame('Microsoft Office Mastery (Word, PowerPoint & Excel)', $course['title']);
        $this->assertTrue($course['is_featured'], 'it took 23% of the poll');
        $this->assertSame(1, $course['tier']);
        $this->assertSame('beginner', $course['level']);
        $this->assertSame('Foundations', $course['category']);
        $this->assertSame([], $course['requirements'], 'prerequisites: none');
        $this->assertCount(11, $course['modules']);

        $lessons = array_sum(array_map(fn ($m) => count($m['lessons']), $course['modules']));
        $this->assertSame(55, $lessons);
    }

    /**
     * Course 22 is the first Office course carrying fenced code, so the path
     * that attaches a fence to the lesson above it is exercised by real
     * content here for the first time.
     */
    public function test_the_excel_formulas_land_on_their_own_lessons(): void
    {
        $course = $this->parse(self::DIR.'/22-microsoft-office-mastery.md');

        $formulas = collect($course['modules'])->firstWhere('title', 'Calculate with formulas and functions');
        $this->assertNotNull($formulas);

        $byTitle = collect($formulas['lessons'])->keyBy('title');

        $this->assertStringContainsString('=IF(C2>=50,"Pass","Fail")',
            implode("\n", $byTitle['Make Excel decide with IF']['body']));
        $this->assertStringContainsString('=SUMIF(',
            implode("\n", $byTitle['Count and total by condition']['body']));
        // The one lesson in the module that is about judgement, not syntax.
        $this->assertStringNotContainsString('```',
            implode("\n", $byTitle['Get a formula from AI, then test it']['body']));
    }

    /** Every course this one points at, and every course that points at it. */
    public function test_course_22_cross_references_resolve(): void
    {
        $body = file_get_contents(self::DIR.'/22-microsoft-office-mastery.md');

        preg_match_all('/Course (\d{2})/', $body, $matches);

        $numbers = array_map('intval', array_unique($matches[1]));
        $existing = array_map(fn ($p) => $this->parse($p)['course_number'], $this->files());

        foreach ($numbers as $number) {
            $this->assertContains($number, $existing, "course 22 points at course {$number}, which does not exist");
        }
    }

    /** The AI thread the rebuild brief requires, in a course for non-programmers. */
    public function test_course_22_threads_ai_through_the_whole_course(): void
    {
        $course = $this->parse(self::DIR.'/22-microsoft-office-mastery.md');

        $aiLessons = collect($course['modules'])
            ->flatMap(fn ($m) => $m['lessons'])
            ->filter(function (array $lesson) {
                $text = strtolower($lesson['title'].' '.implode(' ', $lesson['body']));

                return str_contains($text, 'assistant') || preg_match('/\bai\b/', $text);
            });

        $this->assertGreaterThanOrEqual(8, $aiLessons->count());

        // Not only "use AI": the judgement half is what the brief asks for.
        $this->assertNotNull(
            collect($course['modules'])->firstWhere('title', 'Know where the AI assistant gets it wrong'),
        );
    }
}
