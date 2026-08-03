<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Draws a cover for any course that has none.
 *
 * Not generated art — drawn, deterministically, from the same two inks and the
 * same flat geometry as the commissioned covers: navy and gold on warm paper.
 * A course keeps the same cover every run, because the composition is seeded
 * from its number.
 *
 * It exists because half a catalogue of commissioned screen prints beside half
 * a catalogue of neon stock renders looks worse than either on its own. This
 * fills the gap in the house style until real artwork replaces it — and when it
 * does, the file is simply overwritten.
 *
 * No text is drawn, for the same reason the prompts forbid it: the title is set
 * in HTML over the card, and a title baked into an image is wrong the moment
 * somebody edits the course.
 */
class MakeCourseCovers extends Command
{
    protected $signature = 'courses:make-covers
        {--force : Redraw covers that already exist}
        {--course= : Only this course number}';

    protected $description = 'Draw a branded cover for every course that has none';

    private const W = 1280;

    private const H = 720;

    private const PAPER = '#F5F2EA';

    private const NAVY = '#0B1F3A';

    private const GOLD = '#B8933F';

    public function handle(): int
    {
        $magick = trim((string) shell_exec('command -v magick || command -v convert'));

        if ($magick === '') {
            $this->error('ImageMagick is not installed — no `magick` or `convert` on PATH.');

            return self::FAILURE;
        }

        $courses = Course::orderBy('course_number')
            ->when($this->option('course'), fn ($q, $n) => $q->where('course_number', $n))
            ->get();

        $drawn = 0;
        $kept = 0;

        foreach ($courses as $course) {
            $path = public_path("images/courses/{$course->slug}.png");

            // Never overwrite real artwork by accident.
            if (is_file($path) && ! $this->option('force')) {
                $kept++;

                continue;
            }

            $this->draw($magick, $path, (int) ($course->course_number ?? $course->id));

            $course->forceFill([
                'cover_image' => asset("images/courses/{$course->slug}.png"),
                'cover_alt' => $course->title.' — course cover',
            ])->save();

            $this->line("  drawn  {$course->slug}");
            $drawn++;
        }

        $this->info("Drew {$drawn} cover(s); left {$kept} existing one(s) alone.");

        return self::SUCCESS;
    }

    /**
     * One of five geometric families, chosen by course number so the set has
     * variety without any two neighbours looking alike.
     */
    private function draw(string $magick, string $path, int $seed): void
    {
        $args = [
            $magick, '-size', self::W.'x'.self::H, 'xc:'.self::PAPER,
            '-fill', 'none',
        ];

        $args = array_merge($args, match ($seed % 5) {
            0 => $this->arcs($seed),
            1 => $this->bars($seed),
            2 => $this->grid($seed),
            3 => $this->converge($seed),
            default => $this->nested($seed),
        });

        // Paper tooth. Flat colour with no grain is the giveaway that
        // something was filled by a machine rather than printed.
        $args = array_merge($args, [
            '-attenuate', '0.28', '+noise', 'Gaussian',
            '-colors', '96', '-strip', 'PNG8:'.$path,
        ]);

        (new Process($args))->setTimeout(60)->mustRun();
    }

    /** Concentric compass arcs, cut by a horizon. */
    private function arcs(int $seed): array
    {
        $cx = 880 + ($seed % 3) * 60;
        $d = ['-stroke', self::NAVY, '-strokewidth', '14', '-fill', 'none'];

        for ($i = 1; $i <= 6; $i++) {
            $r = 90 * $i;
            $d[] = '-draw';
            $d[] = "circle {$cx},420 ".($cx + $r).',420';
        }

        return array_merge($d, [
            '-fill', self::GOLD, '-stroke', 'none',
            '-draw', 'rectangle 0,600 '.self::W.','.self::H,
            '-fill', self::NAVY,
            '-draw', "circle {$cx},420 ".($cx + 88).',420',
        ]);
    }

    /** Isometric stacked bars climbing left to right. */
    private function bars(int $seed): array
    {
        $d = ['-stroke', 'none'];

        for ($i = 0; $i < 9; $i++) {
            $x = 90 + $i * 122;
            $h = 120 + (($seed + $i * 7) % 5) * 78;
            $y = 640 - $h;
            $d[] = '-fill';
            $d[] = $i % 3 === 2 ? self::GOLD : self::NAVY;
            $d[] = '-draw';
            $d[] = "rectangle {$x},{$y} ".($x + 86).',640';
        }

        return array_merge($d, [
            '-fill', self::NAVY, '-draw', 'rectangle 0,646 '.self::W.',664',
        ]);
    }

    /** A modular grid with some cells solid. */
    private function grid(int $seed): array
    {
        $d = [];
        $cols = 8;
        $rows = 4;
        $w = 132;
        $h = 132;
        $ox = 112;
        $oy = 108;

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $x = $ox + $c * ($w + 12);
                $y = $oy + $r * ($h + 12);
                $n = ($seed * 13 + $r * 11 + $c * 7) % 10;

                if ($n < 3) {
                    $d = array_merge($d, ['-fill', self::GOLD, '-stroke', 'none']);
                } elseif ($n < 7) {
                    $d = array_merge($d, ['-fill', self::NAVY, '-stroke', 'none']);
                } else {
                    $d = array_merge($d, ['-fill', 'none', '-stroke', self::NAVY, '-strokewidth', '10']);
                }

                $d[] = '-draw';
                $d[] = "rectangle {$x},{$y} ".($x + $w).','.($y + $h);
            }
        }

        return $d;
    }

    /** Lines entering left, converging into one heavy gold line. */
    private function converge(int $seed): array
    {
        $d = ['-stroke', self::NAVY, '-strokewidth', '16', '-fill', 'none'];

        for ($i = 0; $i < 7; $i++) {
            $y = 110 + $i * 84;
            $d[] = '-draw';
            $d[] = "path 'M 0,{$y} L 520,{$y} Q 760,{$y} 860,360'";
        }

        return array_merge($d, [
            '-stroke', self::GOLD, '-strokewidth', '34',
            '-draw', 'line 860,360 '.self::W.',360',
            '-stroke', 'none', '-fill', self::NAVY,
            '-draw', 'rectangle 0,0 '.(90 + ($seed % 3) * 40).','.self::H,
        ]);
    }

    /** Nested rectangles, one filled gold. */
    private function nested(int $seed): array
    {
        $d = [];
        $steps = 7;

        for ($i = 0; $i < $steps; $i++) {
            $inset = 44 + $i * 46;
            $x2 = self::W - $inset - ($seed % 4) * 30;
            $y2 = self::H - $inset;

            $d = array_merge($d, $i === $steps - 2
                ? ['-fill', self::GOLD, '-stroke', 'none']
                : ['-fill', 'none', '-stroke', self::NAVY, '-strokewidth', '13']);

            $d[] = '-draw';
            $d[] = "rectangle {$inset},{$inset} {$x2},{$y2}";
        }

        return $d;
    }
}
