<?php

namespace App\Support\Catalog;

use App\Models\Course;
use Illuminate\Http\Request;

/**
 * Which currency a visitor sees, and how a price is written in it.
 *
 * The order matters, and only one rule in it is a guess:
 *
 *   1. What they chose. An explicit switch is a decision, and a decision
 *      always beats an inference about where somebody is sitting.
 *   2. Their country, when the edge tells us. Cloudflare and most CDNs send
 *      it as a header; it costs nothing and needs no lookup.
 *   3. Shillings. This is a Ugandan business. The home currency is the right
 *      thing to show when we genuinely do not know.
 *
 * There is deliberately no IP-geolocation API call here. It would put a
 * third-party request in front of every page render, fail closed on a bad day,
 * and be wrong for anybody on a VPN, while the toggle solves the same problem
 * instantly and honestly.
 */
class Currency
{
    public const SESSION_KEY = 'catalog.currency';

    /** Headers a CDN or proxy uses to report the visitor's country. */
    private const COUNTRY_HEADERS = [
        'CF-IPCountry',          // Cloudflare
        'X-Vercel-IP-Country',
        'X-AppEngine-Country',
        'X-Country-Code',
    ];

    public static function current(?Request $request = null): string
    {
        $request ??= request();
        $supported = array_keys(config('catalog.currencies'));

        $chosen = $request->session()->get(self::SESSION_KEY);
        if (is_string($chosen) && in_array($chosen, $supported, true)) {
            return $chosen;
        }

        $country = self::countryFrom($request);
        if ($country !== null) {
            return in_array($country, config('catalog.ugx_countries', ['UG']), true) ? 'UGX' : 'USD';
        }

        return config('catalog.default_currency', 'UGX');
    }

    public static function set(string $currency): bool
    {
        if (! array_key_exists($currency, config('catalog.currencies'))) {
            return false;
        }

        session([self::SESSION_KEY => $currency]);

        return true;
    }

    /** Whether the visitor has actually chosen, as opposed to being guessed at. */
    public static function wasChosen(?Request $request = null): bool
    {
        return ($request ?? request())->session()->has(self::SESSION_KEY);
    }

    private static function countryFrom(Request $request): ?string
    {
        foreach (self::COUNTRY_HEADERS as $header) {
            $value = $request->header($header);

            // Cloudflare sends XX for anonymised traffic and T1 for Tor,
            // neither is a country, and treating them as one would silently
            // put those visitors on the wrong currency.
            if (is_string($value) && preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'T1'], true)) {
                return $value;
            }
        }

        return null;
    }

    /** The price of a course in the currency being shown. */
    public static function priceOf(Course $course, ?string $currency = null): string
    {
        $currency ??= self::current();

        $amount = $currency === 'USD'
            // A course with no USD price set falls back to its shilling price
            // rather than showing 0, free is a claim, and a missing number
            // is not a claim that something costs nothing.
            ? ($course->price_usd ?? $course->price)
            : $course->price;

        return (string) $amount;
    }

    public static function isFreeIn(Course $course, ?string $currency = null): bool
    {
        return bccomp(self::priceOf($course, $currency), '0', 2) <= 0;
    }

    /** "UGX 140,000" or "$35". */
    public static function format(string|float|int $amount, ?string $currency = null): string
    {
        $currency ??= self::current();
        $config = config('catalog.currencies')[$currency] ?? ['symbol' => $currency, 'decimals' => 0];
        $number = number_format((float) $amount, $config['decimals']);

        // A symbol hugs its number; a code stands apart from it.
        return $config['symbol'] === '$' ? '$'.$number : $config['symbol'].' '.$number;
    }
}
