<?php

namespace App\Support\Catalog;

use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * Turns the authored prerequisite text into real, clickable courses.
 *
 * The files say "Prerequisites: Courses 10, 11, 12" useful to a reader, dead
 * to a student who then has to go and find them. These are advisory: shown as
 * "best after", never blocking, always one click away.
 */
class Prerequisites
{
    /** @return Collection<int,Course> */
    public static function resolve(Course $course): Collection
    {
        $note = (string) $course->prerequisites_note;

        if ($note === '' || preg_match('/^\s*none/i', $note)) {
            return collect();
        }

        preg_match_all('/\d+/', $note, $matches);
        $numbers = array_map('intval', $matches[0]);

        if ($numbers === []) {
            return collect();
        }

        return Course::whereIn('course_number', $numbers)
            ->where('is_published', true)
            ->orderBy('course_number')
            ->get();
    }

    /**
     * The course that follows this one. Finishing 03 should offer 04 without
     * the student going back to the catalogue to work out what is next.
     */
    public static function next(Course $course): ?Course
    {
        if ($course->course_number === null) {
            return null;
        }

        return Course::where('is_published', true)
            ->where('course_number', '>', $course->course_number)
            ->orderBy('course_number')
            ->first();
    }
}
