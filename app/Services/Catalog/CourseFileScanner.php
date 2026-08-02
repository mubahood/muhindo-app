<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;

/**
 * Finds every YouTube reference in the authored course files.
 *
 * Deliberately a scanner and not the importer's parser: this one only needs to
 * answer "which links exist, and where", and it must keep working even on a
 * file whose structure the importer would reject. The importer builds records;
 * this builds a list to check before any record exists.
 *
 * Link shapes across the 21 files (all real, all present):
 *   ▶ https://www.youtube.com/watch?v=ID
 *   1. Title — https://www.youtube.com/watch?v=ID
 *   https://youtu.be/ID
 *   ▶ Full playlist: https://www.youtube.com/playlist?list=PLID
 */
class CourseFileScanner
{
    /** 11 characters, the fixed width of a YouTube video id. */
    private const VIDEO = '/(?:youtu\.be\/|watch\?v=|youtube-nocookie\.com\/embed\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/';

    private const PLAYLIST = '/[?&]list=([A-Za-z0-9_-]+)/';

    /** @return Collection<int,string> Absolute paths, catalog order, index excluded. */
    public function files(string $directory): Collection
    {
        $files = glob(rtrim($directory, '/').'/[0-9][0-9]-*.md') ?: [];
        sort($files);

        // 00-CATALOG.md is the index, not a course. Left in, the importer would
        // try to build a course out of the table of contents.
        return collect($files)->reject(fn (string $path) => str_starts_with(basename($path), '00-'))->values();
    }

    /**
     * Every reference in one file, with the line it sits on and the human
     * label nearest to it — the report is useless without a way to find the
     * lesson a dead link belongs to.
     *
     * @return list<array{type:string, id:string, line:int, label:string, url:string}>
     */
    public function references(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $found = [];
        $lastLabel = '';

        foreach ($lines as $index => $line) {
            $label = $this->labelFrom($line);
            if ($label !== null) {
                $lastLabel = $label;
            }

            foreach ($this->matchesIn($line) as $reference) {
                $found[] = $reference + [
                    'line' => $index + 1,
                    // A bare "▶ https://…" line carries no title of its own, so
                    // it inherits the numbered lesson immediately above it.
                    'label' => $label ?? $lastLabel,
                ];
            }
        }

        return $found;
    }

    /** @return list<array{type:string, id:string, url:string}> */
    private function matchesIn(string $line): array
    {
        $out = [];

        // Playlists first: a playlist URL never carries a video id, but a
        // watch URL can carry both, and there the video is what matters.
        if (! preg_match(self::VIDEO, $line) && preg_match(self::PLAYLIST, $line, $m)) {
            $out[] = ['type' => 'playlist', 'id' => $m[1], 'url' => 'https://www.youtube.com/playlist?list='.$m[1]];
        }

        if (preg_match_all(self::VIDEO, $line, $matches)) {
            foreach ($matches[1] as $id) {
                $out[] = ['type' => 'video', 'id' => $id, 'url' => 'https://www.youtube.com/watch?v='.$id];
            }
        }

        return $out;
    }

    /**
     * The lesson title on a numbered line, in either shape:
     *   1. **Title** — description.
     *   1. Title — https://…
     */
    private function labelFrom(string $line): ?string
    {
        if (! preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
            return null;
        }

        $rest = trim($m[1]);

        if (preg_match('/^\*\*(.+?)\*\*/', $rest, $bold)) {
            return trim($bold[1]);
        }

        // Shorthand: everything before the em dash that precedes the URL.
        $title = preg_split('/\s+—\s+/u', $rest)[0] ?? $rest;

        return trim(preg_replace('/https?:\S+/', '', $title) ?? $title);
    }

    /** The course number and title from the `# Course NN ⭐ — Title` heading. */
    public function heading(string $path): array
    {
        $first = trim((string) (file($path, FILE_IGNORE_NEW_LINES)[0] ?? ''));

        preg_match('/^#\s*Course\s*(\d+)\s*(⭐)?\s*—\s*(.+)$/u', $first, $m);

        return [
            'number' => isset($m[1]) ? (int) $m[1] : null,
            'featured' => ! empty($m[2]),
            'title' => trim($m[3] ?? basename($path, '.md')),
        ];
    }
}
