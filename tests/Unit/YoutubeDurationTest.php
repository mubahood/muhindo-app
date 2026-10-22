<?php

namespace Tests\Unit;

use App\Services\YoutubeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Durations come from the Data API when a key is configured and from the public
 * watch page when one is not, because most of the catalogue was imported from a
 * markdown format that records no runtime at all.
 */
class YoutubeDurationTest extends TestCase
{
    private function service(): YoutubeService
    {
        return app(YoutubeService::class);
    }

    private function watchPage(int $seconds): string
    {
        // Two copies a second apart, as the real page ships them.
        return '{"videoDetails":{"lengthSeconds":"'.$seconds.'"},"x":{"lengthSeconds":"'.($seconds + 1).'"}}';
    }

    public function test_it_uses_the_data_api_when_a_key_is_configured(): void
    {
        config()->set('services.youtube.key', 'test-key');
        Http::fake([
            'googleapis.com/*' => Http::response(['items' => [['contentDetails' => ['duration' => 'PT15M33S']]]]),
        ]);

        $this->assertSame(16, $this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }

    public function test_it_reads_the_watch_page_when_there_is_no_key(): void
    {
        config()->set('services.youtube.key', null);
        Http::fake(['youtube.com/*' => Http::response($this->watchPage(1744))]);

        // 1744s is 29.07 minutes.
        $this->assertSame(29, $this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }

    public function test_it_falls_back_to_the_watch_page_when_the_api_fails(): void
    {
        config()->set('services.youtube.key', 'test-key');
        Http::fake([
            'googleapis.com/*' => Http::response(['error' => 'quota'], 403),
            'youtube.com/*' => Http::response($this->watchPage(415)),
        ]);

        $this->assertSame(7, $this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }

    public function test_a_video_shorter_than_a_minute_is_never_recorded_as_zero(): void
    {
        config()->set('services.youtube.key', null);
        Http::fake(['youtube.com/*' => Http::response($this->watchPage(20))]);

        $this->assertSame(1, $this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }

    public function test_a_zero_length_page_yields_no_duration_rather_than_zero(): void
    {
        config()->set('services.youtube.key', null);
        Http::fake(['youtube.com/*' => Http::response($this->watchPage(0))]);

        // A live stream or a stripped page. No duration is honest; "0 minutes" is not.
        $this->assertNull($this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }

    public function test_an_unreadable_page_yields_null_rather_than_throwing(): void
    {
        config()->set('services.youtube.key', null);
        Http::fake(['youtube.com/*' => Http::response('nothing useful here', 500)]);

        $this->assertNull($this->service()->fetchDurationMinutes('ZymFwt2gQKo'));
    }
}
