<?php

namespace Tests\Unit;

use App\Models\Lesson;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** §7.3 — the YouTube IFrame API needs a bare video id extracted from whatever URL shape an admin pastes. */
class LessonYoutubeVideoIdTest extends TestCase
{
    #[DataProvider('urls')]
    public function test_it_extracts_the_video_id(?string $url, ?string $expected): void
    {
        $lesson = new Lesson(['video_url' => $url]);

        $this->assertSame($expected, $lesson->youtubeVideoId());
    }

    /** @return array<string,array{0:?string,1:?string}> */
    public static function urls(): array
    {
        return [
            'embed url' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            /* The whole imported catalogue is written in nocookie URLs — 384
               lessons of it — and every one of them used to fall back to a
               plain iframe, so the IFrame API never loaded and no watch
               progress was ever recorded. */
            'nocookie embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'nocookie without www' => ['https://youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch url with params before v' => ['https://www.youtube.com/watch?list=PL9&v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts url' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'old /v/ url' => ['https://www.youtube.com/v/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch url with extra params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ'],
            'short url' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'vimeo url falls back to null' => ['https://player.vimeo.com/video/12345678', null],
            'null video_url' => [null, null],
            'empty string' => ['', null],
        ];
    }
}
