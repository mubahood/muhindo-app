<?php

namespace App\Services\Learning;

use App\Models\Course;

/** Prepares an uploaded HTML lesson for display inside a sandboxed iframe. */
class HtmlLessonRenderer
{
    public function toDocument(?string $content, ?Course $course = null, bool $teachingMode = false): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $assetRoot = asset('bootstrap-practice-ground');
        // Rewrite only real HTML attributes. A global replacement would also
        // alter code samples that teach students to write relative paths.
        $document = preg_replace_callback(
            '/\b(href|src)=("|\')\.\.\/(css|js|images)\//i',
            fn (array $match) => $match[1].'='.$match[2].$assetRoot.'/'.$match[3].'/',
            $content,
        ) ?? $content;
        $courseUrl = $course ? route('courses.show', $course) : route('courses.index');
        $document = preg_replace_callback(
            '/\bhref=("|\')\.\.\/index\.html\1/i',
            fn (array $match) => 'href='.$match[1].$courseUrl.$match[1],
            $document,
        ) ?? $document;

        if (! preg_match('/<html\b/i', $document)) {
            $document = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>'.$document.'</body></html>';
        }

        if (str_contains(strtolower($document), '<pre')) {
            $codeAssets = '<link rel="stylesheet" href="'.asset('lesson-content/github-dark.min.css').'">'
                .'<link rel="stylesheet" href="'.asset('lesson-content/lesson-code.css').'">'
                .'<script src="'.asset('lesson-content/highlight.min.js').'"></script>';
            $document = preg_replace('/<\/head>/i', $codeAssets.'</head>', $document, 1) ?? $document;
            $codeScript = '<script src="'.asset('lesson-content/lesson-code.js').'?v=3"></script>';
            $document = preg_replace('/<\/body>/i', $codeScript.'</body>', $document, 1) ?? $document;

            if ($teachingMode) {
                $document = preg_replace('/<html\b/i', '<html data-lesson-teacher="true"', $document, 1) ?? $document;
            }
        }

        return $document;
    }
}
