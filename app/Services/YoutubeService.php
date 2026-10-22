<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * YouTube duration lookups for the curriculum builder.
 *
 * oEmbed (no key needed) doesn't expose a video's duration at all, so the real
 * Data API v3 is used whenever YOUTUBE_API_KEY is configured. Without a key
 * there is still the public watch page, which carries the length in the player
 * payload it ships to every visitor. That fallback is why a catalogue of
 * hundreds of lessons can show its runtime without anybody provisioning a
 * Google Cloud project first: a sales page that says "43 lessons" and no hours
 * is advertising less than it has.
 *
 * Every path returns null rather than throwing. A duration is a nice-to-have on
 * a lesson, never a reason for a save to fail.
 */
class YoutubeService
{
    public function isConfigured(): bool
    {
        return filled(config('services.youtube.key'));
    }

    /** Returns whole-minute duration (rounded), or null if it cannot be determined. */
    public function fetchDurationMinutes(string $videoId): ?int
    {
        return $this->fromDataApi($videoId) ?? $this->fromWatchPage($videoId);
    }

    /** The Data API, when a key is configured. Null when it is not, or on any API error. */
    private function fromDataApi(string $videoId): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'contentDetails',
                'id' => $videoId,
                'key' => config('services.youtube.key'),
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $iso8601 = $response->json('items.0.contentDetails.duration');
        if (! $iso8601) {
            return null;
        }

        return $this->parseIso8601DurationToMinutes($iso8601);
    }

    /**
     * The public watch page, which needs no key.
     *
     * The length sits in the player payload as "lengthSeconds":"1744". It
     * appears more than once per page and the copies can differ by a second,
     * so the first match is taken rather than the last; to the nearest minute
     * they are the same number anyway.
     */
    private function fromWatchPage(string $videoId): ?int
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; course-catalogue/1.0)'])
                ->get('https://www.youtube.com/watch', ['v' => $videoId]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! preg_match('/"lengthSeconds":"(\d+)"/', $response->body(), $m)) {
            return null;
        }

        $seconds = (int) $m[1];

        // A live stream or a stripped page reports zero. Never store "0 minutes":
        // a lesson with no duration is honest, one claiming none is not.
        return $seconds > 0 ? max(1, (int) round($seconds / 60)) : null;
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
