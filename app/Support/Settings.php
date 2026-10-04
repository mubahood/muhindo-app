<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Thin, cache-backed accessor over the key/value `settings` table.
 *
 * Safe to call before the table exists (fresh install / early boot): every
 * lookup falls back to the supplied default. Categories, regulatory bodies and
 * validity periods live in their own tables. This holds site-wide config only
 * (name, tagline, contacts, languages, verify rate-limit, QR base URL).
 */
class Settings
{
    private const CACHE_KEY = 'app.settings';

    /*
     * Resolved once per request. Held in the container rather than a static
     * property on purpose: a static survives for the life of the process, which
     * is one request under PHP-FPM but many requests under Octane and many
     * tests in one suite run. The container is rebuilt for each of those, so
     * this cannot leak one caller's settings into the next.
     */
    private const MEMO = 'app.settings.resolved';

    /**
     * @return array<string,mixed>
     *
     * The table check used to run before the cache, so it ran on every call.
     * Schema::hasTable is a query against information_schema, and a page that
     * asks for the site name, the tagline and three contact details paid for
     * five of them before touching the cache at all. Those are among the
     * slowest queries MySQL answers on shared hosting.
     *
     * Now a warm cache costs nothing: no schema query, no database query, and
     * after the first call in a request, not even a cache read. The check still
     * happens on a cold cache, and a missing table still returns an empty array
     * without caching it, so a fresh install starts working the moment the
     * migration runs rather than staying empty until something clears the cache.
     */
    public static function all(): array
    {
        if (app()->bound(self::MEMO)) {
            return app()->make(self::MEMO);
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            app()->instance(self::MEMO, $cached);

            return $cached;
        }

        if (! Schema::hasTable('settings')) {
            return [];
        }

        $values = \App\Models\Setting::query()->pluck('value', 'key')->toArray();
        Cache::forever(self::CACHE_KEY, $values);
        app()->instance(self::MEMO, $values);

        return $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        \App\Models\Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        self::flush();
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::MEMO);
        Cache::forget(self::CACHE_KEY);
    }
}
