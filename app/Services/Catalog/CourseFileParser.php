<?php

namespace App\Services\Catalog;

/**
 * Turns one authored course file into a plain array the importer can persist.
 *
 * Pure: no database, no network. That keeps the fiddly part — a hand-written
 * markdown dialect with a dozen shapes — testable against a fixture without a
 * schema, and lets the importer stay a boring loop over the result.
 *
 * The shapes it must survive, all present across the 21 real files:
 *
 *   # Course 16 ⭐ — Title                     heading, number, featured
 *   **Tier 3 · … · Level: … · Prerequisites: … · TOP FEATURED**
 *   **What you will learn** + bullets          outcomes
 *   **System features:** …                     description addendum
 *   ▶ Full playlist: …                         course/module fallback, never a lesson
 *   ## Module 1 — Name  /  ## Phase A — Name   a module, either word
 *   ## Project 1 — Name /  ## Bonus module …   also a module (undocumented, real)
 *   1. **Title** — text.                       lesson, link on the next line
 *      ▶ https://youtu.be/ID
 *   1. Title — https://…                       capstone shorthand
 *   1. **Title** — text. ▶ https://…           both on one line
 *   *(freeCodeCamp)*                           external attribution
 *   ```fenced```                               belongs to the lesson above it
 *   ## Final project / ## Graduation assignment / ## Final challenge → assignment
 *   **Quiz ideas…** / **Milestone quizzes…**   → quiz brief
 */
class CourseFileParser
{
    private const VIDEO = '/(?:youtu\.be\/|watch\?v=)([A-Za-z0-9_-]{11})/';

    /** Headings that open a module, whatever the author called it. */
    private const MODULE_HEADINGS = '/^##\s+(Module|Phase|Project|Bonus module|Extension modules?|Extension briefs?)\b/i';

    /** Headings whose body becomes the course's assignment. */
    private const ASSIGNMENT_HEADINGS = '/^##\s+(Final project|Graduation assignment|Final challenge)\b/i';

    public function parse(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        $course = [
            'source_file' => basename($path),
            'title' => '',
            'course_number' => null,
            'is_featured' => false,
            'tier' => null,
            'level' => 'beginner',
            'category' => null,
            'description' => '',
            'outcomes' => [],
            'requirements' => [],
            'prerequisites_note' => null,
            'playlist_url' => null,
            'modules' => [],
            'assignment' => null,
            'quiz_brief' => null,
        ];

        $module = null;          // the module being filled
        $lesson = null;          // the lesson being filled
        $section = 'intro';      // intro | modules | assignment
        $bulletTarget = null;    // outcomes while inside "What you will learn"
        $fence = null;           // open code fence, if any
        $assignment = [];
        $descriptionLines = [];

        foreach ($lines as $raw) {
            $line = rtrim($raw);
            $trimmed = trim($line);

            // A fenced block belongs to whatever it follows, verbatim.
            if (str_starts_with($trimmed, '```')) {
                if ($fence === null) {
                    $fence = [$line];
                } else {
                    $fence[] = $line;
                    $this->attachCode($module, $lesson, $assignment, $section, implode("\n", $fence));
                    $fence = null;
                }

                continue;
            }
            if ($fence !== null) {
                $fence[] = $line;

                continue;
            }

            // ── Headings ────────────────────────────────────────────────────
            if (str_starts_with($trimmed, '# ')) {
                $course = array_merge($course, $this->heading($trimmed));

                continue;
            }

            if (preg_match(self::ASSIGNMENT_HEADINGS, $trimmed, $m)) {
                $lesson = $this->closeLesson($module, $lesson);
                $section = 'assignment';
                $assignment = ['title' => trim($m[1]), 'body' => []];

                continue;
            }

            if (preg_match(self::MODULE_HEADINGS, $trimmed)) {
                $lesson = $this->closeLesson($module, $lesson);
                $module = $this->closeModule($course, $module);
                $section = 'modules';
                $module = ['title' => $this->moduleTitle($trimmed), 'lessons' => [], 'intro' => []];

                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                // Some other section — end the current lesson so its text does
                // not bleed into it, and stop collecting.
                $lesson = $this->closeLesson($module, $lesson);
                $section = 'other';

                continue;
            }

            // ── Course metadata, before the first module ────────────────────
            if ($section === 'intro') {
                if (preg_match('/^\*\*Tier\s/i', $trimmed)) {
                    $course = array_merge($course, $this->metaLine($trimmed));

                    continue;
                }
                if (preg_match('/^\*\*What you will learn\*\*/i', $trimmed)) {
                    $bulletTarget = 'outcomes';

                    continue;
                }
                if (preg_match('/^\*\*System features:?\*\*\s*(.*)$/i', $trimmed, $m)) {
                    $descriptionLines[] = '**System features:** '.$m[1];

                    continue;
                }
                if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m) && $bulletTarget === 'outcomes') {
                    $course['outcomes'][] = trim($m[1]);

                    continue;
                }
                if ($playlist = $this->playlistIn($trimmed)) {
                    $course['playlist_url'] = $playlist;

                    continue;
                }
                if ($trimmed !== '' && $trimmed !== '---' && ! str_starts_with($trimmed, '**')) {
                    $descriptionLines[] = $trimmed;
                }

                continue;
            }

            // ── The quiz brief can appear anywhere below the modules ────────
            if (preg_match('/^\*\*(?:Quiz ideas|Milestone quizzes)[^*]*\*\*\s*(.*)$/i', $trimmed, $m)) {
                $course['quiz_brief'] = trim($m[1]) !== '' ? trim($m[1]) : ($course['quiz_brief'] ?? '');

                continue;
            }

            if ($section === 'assignment') {
                if ($trimmed !== '') {
                    $assignment['body'][] = $trimmed;
                }

                continue;
            }

            if ($section !== 'modules' || $module === null) {
                continue;
            }

            // ── Lessons ─────────────────────────────────────────────────────
            if (preg_match('/^\s*(\d+)\.\s+(.*)$/', $line, $m)) {
                $lesson = $this->closeLesson($module, $lesson);
                $lesson = $this->startLesson((int) $m[1], trim($m[2]));

                continue;
            }

            // A playlist line inside a module is a module resource, not a lesson.
            if ($playlist = $this->playlistIn($trimmed)) {
                $module['resource_url'] = $playlist;

                continue;
            }

            // An indented continuation belongs to the open lesson: its video,
            // or more of its description.
            if ($lesson !== null && $trimmed !== '') {
                if (preg_match(self::VIDEO, $trimmed, $v)) {
                    $lesson['video_id'] ??= $v[1];
                    $rest = trim(preg_replace('/[▶►]?\s*https?:\S+/u', '', $trimmed) ?? '');
                    if ($rest !== '') {
                        $lesson['body'][] = $rest;
                    }
                } else {
                    $lesson['body'][] = $trimmed;
                }

                continue;
            }

            if ($lesson === null && $trimmed !== '') {
                $module['intro'][] = $trimmed;
            }
        }

        $lesson = $this->closeLesson($module, $lesson);
        $module = $this->closeModule($course, $module);

        $course['description'] = trim(implode("\n\n", $descriptionLines));
        if ($assignment !== []) {
            $course['assignment'] = [
                'title' => $assignment['title'],
                'body' => trim(implode("\n\n", $assignment['body'])),
            ];
        }

        return $course;
    }

    /** `# Course 16 ⭐ — Title` */
    private function heading(string $line): array
    {
        if (! preg_match('/^#\s*Course\s*(\d+)\s*(⭐)?\s*—\s*(.+)$/u', $line, $m)) {
            return [];
        }

        return [
            'course_number' => (int) $m[1],
            'is_featured' => $m[2] !== '',
            'title' => trim($m[3]),
        ];
    }

    /** `**Tier 3 · Capstone Systems · Level: Advanced · Prerequisites: … · TOP FEATURED**` */
    private function metaLine(string $line): array
    {
        $body = trim(str_replace('**', '', $line));
        $out = [];

        if (preg_match('/Tier\s*(\d+)/i', $body, $m)) {
            $out['tier'] = (int) $m[1];
        }
        if (preg_match('/·\s*Tier\s*\d+\s*·\s*([^·]+)/i', '·'.$body, $m)) {
            $out['category'] = trim($m[1]);
        }
        if (preg_match('/Level:\s*([^·]+)/i', $body, $m)) {
            $out['level'] = $this->level(trim($m[1]));
        }
        if (preg_match('/Prerequisites:\s*([^·]+)/i', $body, $m)) {
            $note = trim($m[1]);
            $out['prerequisites_note'] = $note;
            $out['requirements'] = preg_match('/^none/i', $note) ? [] : [$note];
        }
        if (stripos($body, 'TOP FEATURED') !== false) {
            $out['is_featured'] = true;
        }

        return $out;
    }

    /** The authored levels are prose; the column is an enum. */
    private function level(string $text): string
    {
        $text = strtolower($text);

        return match (true) {
            str_contains($text, 'advanced') => 'advanced',
            str_contains($text, 'intermediate') => 'intermediate',
            default => 'beginner',
        };
    }

    private function moduleTitle(string $line): string
    {
        $title = trim(preg_replace('/^##\s+/', '', $line) ?? $line);

        // "Module 1 — Your first web page" reads better as the part after the
        // dash, but a "Bonus module — …" keeps its own words.
        if (preg_match('/^(?:Module|Phase|Project)\s+[A-Z0-9]+\s*—\s*(.+)$/iu', $title, $m)) {
            return trim($m[1]);
        }

        return $title;
    }

    private function playlistIn(string $line): ?string
    {
        if (preg_match('/\bhttps?:\/\/\S*[?&]list=([A-Za-z0-9_-]+)/', $line, $m)
            && ! preg_match(self::VIDEO, $line)) {
            return 'https://www.youtube.com/playlist?list='.$m[1];
        }

        return null;
    }

    /** Either lesson shape, plus the one that carries its link inline. */
    private function startLesson(int $number, string $rest): array
    {
        $lesson = [
            'number' => $number,
            'title' => '',
            'body' => [],
            'video_id' => null,
            'is_external' => false,
            'attribution' => null,
        ];

        if (preg_match('/\*\((.+?)\)\*/', $rest, $credit)) {
            $lesson['is_external'] = true;
            $lesson['attribution'] = trim($credit[1]);
            $rest = trim(str_replace($credit[0], '', $rest));
        }

        if (preg_match(self::VIDEO, $rest, $v)) {
            $lesson['video_id'] = $v[1];
            $rest = trim(preg_replace('/[▶►]?\s*https?:\S+/u', '', $rest) ?? $rest);
        }

        if (preg_match('/^\*\*(.+?)\*\*\s*(?:—\s*(.*))?$/u', $rest, $m)) {
            $lesson['title'] = trim($m[1]);
            if (trim($m[2] ?? '') !== '') {
                $lesson['body'][] = trim($m[2]);
            }
        } else {
            $parts = preg_split('/\s+—\s+/u', $rest, 2);
            $lesson['title'] = trim($parts[0] ?? $rest);
            if (trim($parts[1] ?? '') !== '') {
                $lesson['body'][] = trim($parts[1]);
            }
        }

        $lesson['title'] = trim($lesson['title'], ' .—-');

        return $lesson;
    }

    /** Files the open lesson onto its module. Always returns null — the caller
     *  assigns it back, which is what "there is no open lesson now" means. */
    private function closeLesson(?array &$module, ?array $lesson): null
    {
        if ($module !== null && $lesson !== null && $lesson['title'] !== '') {
            $module['lessons'][] = $lesson;
        }

        return null;
    }

    /** Files the open module onto the course, dropping one with no lessons.
     *  Always returns null, for the same reason as closeLesson(). */
    private function closeModule(array &$course, ?array $module): null
    {
        if ($module !== null && $module['lessons'] !== []) {
            $course['modules'][] = $module;
        }

        return null;
    }

    /** A fenced block lands on the lesson, module intro or assignment it follows. */
    private function attachCode(?array &$module, ?array &$lesson, array &$assignment, string $section, string $code): void
    {
        if ($section === 'assignment' && $assignment !== []) {
            $assignment['body'][] = $code;

            return;
        }
        if ($lesson !== null) {
            $lesson['body'][] = $code;

            return;
        }
        if ($module !== null) {
            $module['intro'][] = $code;
        }
    }
}
