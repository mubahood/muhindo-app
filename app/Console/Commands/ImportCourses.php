<?php

namespace App\Console\Commands;

use App\Services\Catalog\CourseFileParser;
use App\Services\Catalog\CourseFileScanner;
use App\Services\Catalog\CourseImporter;
use Illuminate\Console\Command;

/**
 * Turns the authored course files into database records.
 *
 * Re-runnable by design: it matches on natural keys and updates, so the
 * catalogue can be re-synced every time a file is corrected without growing a
 * second copy of itself.
 */
class ImportCourses extends Command
{
    protected $signature = 'courses:import
        {file? : One file to import, e.g. 03-php-programming-step-by-step.md}
        {--dir=course-content : Directory holding the authored course files}
        {--dry-run : Report what would change and touch nothing}';

    protected $description = 'Import the authored course catalogue into the database';

    public function handle(CourseFileScanner $scanner, CourseFileParser $parser, CourseImporter $importer): int
    {
        $directory = base_path((string) $this->option('dir'));
        $files = $scanner->files($directory);

        if ($file = $this->argument('file')) {
            $files = $files->filter(fn ($f) => basename($f) === $file)->values();

            if ($files->isEmpty()) {
                $this->error("No such course file: {$file}");

                return self::FAILURE;
            }
        }

        // Whether a video can play in an iframe was settled by
        // courses:verify-links; re-asking YouTube 384 times during an import
        // would be slow and would make the import depend on the network.
        $embeddable = $this->embeddableMap($directory);

        if ($embeddable === [] && ! $this->option('dry-run')) {
            $this->warn('No link report cache found — run courses:verify-links first,');
            $this->warn('or every lesson will be assumed embeddable.');
        }

        $rows = [];

        foreach ($files as $path) {
            $parsed = $parser->parse($path);

            if ($this->option('dry-run')) {
                $lessons = collect($parsed['modules'])->sum(fn ($m) => count($m['lessons']));
                $rows[] = [
                    $parsed['course_number'],
                    mb_substr($parsed['title'], 0, 38),
                    count($parsed['modules']),
                    $lessons,
                    $parsed['assignment'] ? 'yes' : '—',
                    'dry run',
                ];

                continue;
            }

            $result = $importer->import($parsed, $embeddable);

            $rows[] = [
                $result['course']->course_number,
                mb_substr($result['course']->title, 0, 38),
                $result['modules'],
                $result['lessons'],
                $result['videos'].' video / '.$result['text'].' text',
                $result['created'] ? 'created' : 'updated',
            ];
        }

        $this->table(['#', 'Course', 'Modules', 'Lessons', 'Content', 'Result'], $rows);

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }

    /** @return array<string,bool> video id => can it play inside an iframe */
    private function embeddableMap(string $directory): array
    {
        $path = $directory.'/_link-cache.json';

        if (! is_file($path)) {
            return [];
        }

        $map = [];

        foreach (json_decode((string) file_get_contents($path), true) ?: [] as $key => $verdict) {
            if (str_starts_with($key, 'video:')) {
                $map[substr($key, 6)] = (bool) ($verdict['embeddable'] ?? true);
            }
        }

        return $map;
    }
}
