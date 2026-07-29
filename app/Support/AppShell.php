<?php

namespace App\Support;

/**
 * Where the signed-in application shell begins and ends.
 *
 * Everything listed here renders inside the same chrome and loads the same
 * stylesheets, which is exactly the condition for SPA navigation to be safe:
 * Livewire's head merge appends the incoming page's assets without removing the
 * outgoing ones, so navigating between two different design systems would leave
 * both loaded at once. Links outside these paths get an ordinary page load.
 */
class AppShell
{
    /** URL paths owned by the app shell, relative to the deployment's base path. */
    private const PREFIXES = ['/dashboard', '/account', '/notifications', '/admin', '/learn', '/portal'];

    /** Paths that sit under a public prefix but render in the app shell. */
    private const SUFFIXES = ['/checkout'];

    /**
     * @return array{prefixes: list<string>, suffixes: list<string>}
     */
    public static function paths(): array
    {
        return [
            'prefixes' => array_map(
                fn (string $path) => rtrim((string) parse_url(url($path), PHP_URL_PATH), '/'),
                self::PREFIXES
            ),
            'suffixes' => self::SUFFIXES,
        ];
    }
}
