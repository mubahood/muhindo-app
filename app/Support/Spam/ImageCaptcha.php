<?php

namespace App\Support\Spam;

use Illuminate\Support\Str;

/**
 * A small image challenge drawn with GD, kept in the session.
 *
 * This exists because the reCAPTCHA wrapper beside it fails open when no keys
 * are configured, and no keys are configured, so until now the only thing
 * standing in front of every public form was the honeypot. A bot that fills a
 * form properly and waits two seconds was getting through.
 *
 * Deliberately not reCAPTCHA. This needs no Google account, no third party
 * request on page load, no JavaScript, and nothing leaves the server, which
 * also means it keeps working for a visitor who blocks trackers or has
 * scripting off. It is weaker than reCAPTCHA against a determined attacker
 * running OCR. It is much stronger than nothing against the volume traffic
 * that public forms actually get, which is scripted POSTs and cheap crawlers.
 *
 * Layering is the point. FormShield's honeypot catches anything that fills
 * every field, its timing check catches anything that submits instantly, and
 * this catches anything that cannot see. Each is cheap and each covers a
 * different failure of the others.
 */
final class ImageCaptcha
{
    public const FIELD = 'visual_answer';

    private const SESSION_KEY = 'captcha.answer';

    private const WIDTH = 190;

    private const HEIGHT = 56;

    /**
     * Characters a human will not misread.
     *
     * No 0 or O, no 1 or I or l, no 5 or S, no 2 or Z, no 8 or B. Every one of
     * those pairs produces a person who typed what they saw, was told they were
     * wrong, and left. The alphabet is smaller for it, which costs a little
     * entropy and buys the thing the form is actually for.
     */
    private const ALPHABET = 'ACDEFGHJKMNPQRTUVWXY34679';

    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    /** Draws the current challenge as a PNG, creating one if there is none. */
    public static function png(): string
    {
        $answer = session(self::SESSION_KEY) ?: self::regenerate();

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $background = imagecolorallocate($image, 247, 246, 242);
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $background);

        // Noise first, so the characters sit on top and stay the most legible
        // thing in the frame. Noise that obscures the answer only defeats the
        // human; a machine reading pixel runs is barely slowed by it.
        for ($i = 0; $i < 6; $i++) {
            $line = imagecolorallocate($image, random_int(185, 215), random_int(185, 215), random_int(185, 215));
            imageline($image, random_int(0, self::WIDTH), random_int(0, self::HEIGHT),
                random_int(0, self::WIDTH), random_int(0, self::HEIGHT), $line);
        }
        for ($i = 0; $i < 220; $i++) {
            $dot = imagecolorallocate($image, random_int(195, 225), random_int(195, 225), random_int(195, 225));
            imagesetpixel($image, random_int(0, self::WIDTH - 1), random_int(0, self::HEIGHT - 1), $dot);
        }

        $characters = str_split($answer);
        $step = (int) (self::WIDTH / (count($characters) + 1));

        foreach ($characters as $index => $character) {
            $ink = imagecolorallocate($image, random_int(8, 48), random_int(18, 58), random_int(38, 78));

            // Each character is drawn on its own small canvas and rotated, so
            // the baseline is uneven and the glyphs do not line up for a naive
            // segmenting reader.
            $glyph = imagecreatetruecolor(28, 40);
            imagefill($glyph, 0, 0, $background);
            imagecolortransparent($glyph, $background);
            imagestring($glyph, 5, random_int(4, 8), random_int(8, 14), $character, $ink);

            $rotated = imagerotate($glyph, random_int(-26, 26), $background);
            imagecolortransparent($rotated, $background);
            imagecopy($image, $rotated, $step * ($index + 1) - 14, random_int(2, 12), 0, 0,
                imagesx($rotated), imagesy($rotated));

            imagedestroy($glyph);
            imagedestroy($rotated);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** A new challenge. Called when one is served and after every check. */
    public static function regenerate(): string
    {
        $answer = '';
        $alphabet = self::ALPHABET;

        for ($i = 0; $i < 5; $i++) {
            $answer .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        session([self::SESSION_KEY => $answer]);

        return $answer;
    }

    /**
     * Case insensitive, and the challenge is burned either way.
     *
     * Burning it on failure as well as success is what stops a script keeping
     * one image and brute forcing the five characters against it: every guess
     * costs a fresh page load.
     */
    public static function check(?string $given): bool
    {
        $expected = session(self::SESSION_KEY);
        session()->forget(self::SESSION_KEY);

        if (! is_string($expected) || $expected === '' || $given === null) {
            return false;
        }

        return hash_equals(Str::upper($expected), Str::upper(trim($given)));
    }

    /**
     * Validation rules, empty when GD is missing.
     *
     * The same fail open reasoning as the reCAPTCHA wrapper: a server without
     * GD should not have every public form start rejecting every visitor. The
     * honeypot and the timing check still cover them.
     *
     * @return array<string,mixed>
     */
    public static function rules(): array
    {
        return self::available() ? [self::FIELD => ['required', 'string', 'max:12']] : [];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            self::FIELD.'.required' => 'Please type the characters from the picture.',
        ];
    }
}
