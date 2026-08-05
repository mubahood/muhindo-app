<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The YouTube IFrame API wrapper renders for YouTube URLs; everything else degrades to a plain iframe. */
class PlayerRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentForLesson(array $lessonAttributes): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0], $lessonAttributes));
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student];
    }

    public function test_a_youtube_lesson_renders_the_iframe_api_player_and_speed_controls(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ']);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('x-data="youtubePlayer(', false)
            ->assertSee('yt-player-'.$lesson->id, false)
            ->assertSee('learn-speed', false);
    }

    public function test_a_non_youtube_video_url_falls_back_to_a_plain_iframe(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_url' => 'https://player.vimeo.com/video/12345678']);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk()
            ->assertSee('<iframe src="https://player.vimeo.com/video/12345678"', false)
            ->assertDontSee('x-data="youtubePlayer(', false);
    }

    public function test_a_lesson_with_no_video_renders_neither_player(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson(['video_url' => null, 'content' => 'Just text.']);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertDontSee('x-data="youtubePlayer(', false)
            ->assertDontSee('<iframe', false);
    }
}
