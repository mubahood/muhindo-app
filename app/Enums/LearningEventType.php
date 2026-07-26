<?php

namespace App\Enums;

/**
 * The xAPI-lite event vocabulary fed into `learning_events` (§6.2). Not every
 * case has a recorder yet — the video, quiz, note and question cases wait on
 * the player heartbeat (P2), quiz engine (P3), and community features (P4)
 * respectively; they're declared now so the schema doesn't need another
 * migration when those phases land.
 */
enum LearningEventType: string
{
    case LessonViewed = 'lesson.viewed';
    case VideoPlay = 'video.play';
    case VideoPause = 'video.pause';
    case VideoHeartbeat = 'video.heartbeat';
    case VideoEnded = 'video.ended';
    case LessonCompleted = 'lesson.completed';
    case QuizStarted = 'quiz.started';
    case QuizSubmitted = 'quiz.submitted';
    case MaterialDownloaded = 'material.downloaded';
    case NoteCreated = 'note.created';
    case QuestionAsked = 'question.asked';

    /** Human label for the instructor's per-student activity timeline (§6.3.2). */
    public function label(): string
    {
        return match ($this) {
            self::LessonViewed => 'Viewed a lesson',
            self::VideoPlay => 'Played the video',
            self::VideoPause => 'Paused the video',
            self::VideoHeartbeat => 'Watched the video',
            self::VideoEnded => 'Finished the video',
            self::LessonCompleted => 'Completed a lesson',
            self::QuizStarted => 'Started a quiz',
            self::QuizSubmitted => 'Submitted a quiz',
            self::MaterialDownloaded => 'Downloaded a material',
            self::NoteCreated => 'Left a note',
            self::QuestionAsked => 'Asked a question',
        };
    }
}
