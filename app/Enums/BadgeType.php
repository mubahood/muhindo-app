<?php

namespace App\Enums;

/** §6.5 — the fixed, code-defined set of earnable badges. No admin CRUD; new badges ship as new cases. */
enum BadgeType: string
{
    case FirstCourseCompleted = 'first_course_completed';
    case FiveCoursesCompleted = 'five_courses_completed';
    case PerfectQuiz = 'perfect_quiz';
    case FourWeekStreak = 'four_week_streak';

    public function label(): string
    {
        return match ($this) {
            self::FirstCourseCompleted => 'First Course Completed',
            self::FiveCoursesCompleted => 'Five Courses Completed',
            self::PerfectQuiz => 'Perfect Quiz',
            self::FourWeekStreak => '4-Week Streak',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FirstCourseCompleted => 'Completed your first course.',
            self::FiveCoursesCompleted => 'Completed five courses.',
            self::PerfectQuiz => 'Scored 100% on a quiz.',
            self::FourWeekStreak => 'Learned in four consecutive weeks.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FirstCourseCompleted => 'fa-award',
            self::FiveCoursesCompleted => 'fa-medal',
            self::PerfectQuiz => 'fa-star',
            self::FourWeekStreak => 'fa-fire',
        };
    }
}
