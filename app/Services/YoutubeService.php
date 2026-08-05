<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * YouTube Data API v3 lookups for the curriculum builder. oEmbed (no key needed) doesn't
 * expose a video's duration at all, so this is the real API, used only when YOUTUBE_API_KEY is
 * configured; entirely optional everywhere it's called from.
 */
class YoutubeService
{
    public function isConfigured(): bool
    {
        return filled(config('services.youtube.key'));
    }

    /** Returns whole-minute duration (rounded), or null if unconfigured/not found/on any API error. */
    public function fetchDurationMinutes(string $videoId): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'contentDetails',
            'id' => $videoId,
            'key' => config('services.youtube.key'),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $iso8601 = $response->json('items.0.contentDetails.duration');
        if (! $iso8601) {
            return null;
        }

        return $this->parseIso8601DurationToMinutes($iso8601);
    }

    /** e.g. "PT15M33S" -> 16 (rounded to the nearest minute), "PT1H2M" -> 62. */
    private function parseIso8601DurationToMinutes(string $iso8601): ?int
    {
        if (! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso8601, $m)) {
            return null;
        }

        $hours = (int) ($m[1] ?? 0);
        $minutes = (int) ($m[2] ?? 0);
        $seconds = (int) ($m[3] ?? 0);

        return (int) round((($hours * 3600) + ($minutes * 60) + $seconds) / 60);
    }
}
