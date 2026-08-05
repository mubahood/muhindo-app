<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Http;

/**
 * Asks YouTube whether a video or playlist is actually watchable, via oEmbed.
 *
 * oEmbed rather than the Data API: it needs no API key. But oEmbed alone
 * cannot tell a missing video from a perfectly good one whose owner disabled
 * embedding (both come back 401) and those two need opposite responses. A
 * gone video must be replaced or rewritten; a non-embeddable one is real
 * teaching that simply cannot play inside an iframe and must be linked out to.
 *
 * Measured on this catalogue: all 68 initial failures were the second kind,
 * live videos with embedding off. Treating them as dead would have destroyed
 * 68 of Muhindo's own lessons. So every oEmbed failure is confirmed against the
 * watch page before a verdict is recorded.
 *
 * Results are cached to a JSON file so re-runs are instant and so a repair pass
 * only pays for the links it changed. Only definitive answers are cached. A
 * network failure is never written down as a verdict.
 */
class YouTubeLinkChecker
{
    private const ENDPOINT = 'https://www.youtube.com/oembed';

    /** @var array<string,array{ok:bool,embeddable:bool,title:?string,reason:?string}> */
    private array $cache = [];

    private bool $cacheLoaded = false;

    public function __construct(
        private readonly string $cachePath,
        private readonly int $delayMs = 200,
    ) {}

    /** @return array{ok:bool,embeddable:bool,title:?string,reason:?string,cached:bool} */
    public function check(string $type, string $id): array
    {
        $this->loadCache();
        $key = $type.':'.$id;

        if (isset($this->cache[$key])) {
            return $this->cache[$key] + ['cached' => true];
        }

        $url = $type === 'playlist'
            ? 'https://www.youtube.com/playlist?list='.$id
            : 'https://www.youtube.com/watch?v='.$id;

        $verdict = $this->ask($url, $type, $id);

        // A transport failure says nothing about the link, so it is not cached
        // Otherwise one flaky minute would poison every later run.
        if ($verdict['reason'] !== 'unreachable') {
            $this->cache[$key] = $verdict;
            $this->persist();
        }

        usleep($this->delayMs * 1000);

        return $verdict + ['cached' => false];
    }

    /**
     * @return array{ok:bool,embeddable:bool,title:?string,reason:?string}
     */
    private function ask(string $url, string $type, string $id): array
    {
        try {
            $response = Http::timeout(12)->retry(2, 300, throw: false)->get(self::ENDPOINT, [
                'url' => $url,
                'format' => 'json',
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'embeddable' => false, 'title' => null, 'reason' => 'unreachable'];
        }

        if ($response->successful()) {
            return [
                'ok' => true,
                'embeddable' => true,
                'title' => (string) $response->json('title'),
                'reason' => null,
            ];
        }

        // oEmbed said no. That is not yet a verdict, ask the watch page
        // whether the video actually exists.
        if ($type === 'video') {
            return $this->confirmAgainstWatchPage($id, $response->status());
        }

        return [
            'ok' => false,
            'embeddable' => false,
            'title' => null,
            'reason' => 'playlist unavailable ('.$response->status().')',
        ];
    }

    /**
     * The tie-breaker. A live page carrying "status":"OK" and a real title is a
     * real video whose owner turned embedding off, watchable, just not inside
     * our player. Anything else is genuinely unavailable to a student.
     *
     * @return array{ok:bool,embeddable:bool,title:?string,reason:?string}
     */
    private function confirmAgainstWatchPage(string $id, int $oembedStatus): array
    {
        /*
         * No ->retry() here. A watch page is around a megabyte, and retrying a
         * response whose stream has already been touched throws "Unable to read
         * stream contents" which is a transport artefact, not a verdict about
         * the video. One attempt, and the body is read inside the try.
         */
        try {
            $page = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; muhindo-app link check)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get('https://www.youtube.com/watch', ['v' => $id]);

            $body = $page->body();
        } catch (\Throwable) {
            return ['ok' => false, 'embeddable' => false, 'title' => null, 'reason' => 'unreachable'];
        }

        if ($page->successful() && str_contains($body, '"status":"OK"')) {
            preg_match('/<meta name="title" content="([^"]*)"/', $body, $m);

            return [
                'ok' => true,
                'embeddable' => false,
                'title' => html_entity_decode($m[1] ?? '', ENT_QUOTES),
                'reason' => 'embedding disabled, plays on YouTube, not in our player',
            ];
        }

        return [
            'ok' => false,
            'embeddable' => false,
            'title' => null,
            'reason' => $oembedStatus === 404
                ? 'removed, or the id is wrong'
                : 'private or unavailable to viewers',
        ];
    }

    private function loadCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;

        if (is_file($this->cachePath)) {
            $this->cache = json_decode((string) file_get_contents($this->cachePath), true) ?: [];
        }
    }

    private function persist(): void
    {
        @mkdir(dirname($this->cachePath), 0775, true);
        file_put_contents($this->cachePath, json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
