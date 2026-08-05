<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;

/**
 * Where a visit came from, in the four words anybody actually plans around.
 *
 * The raw referrer is kept, but on its own it is close to useless: nobody
 * budgets their week around "l.facebook.com" versus "m.facebook.com" versus
 * "lm.facebook.com". Channel collapses those into Social, and the report above
 * it becomes a decision instead of a list.
 *
 * An explicit utm_* tag always wins. If a link was tagged, whoever tagged it
 * knew more about that link than any guess made from a Referer header, and
 * plenty of clients strip the header entirely.
 */
final class Channel
{
    public const DIRECT = 'direct';

    public const SEARCH = 'search';

    public const SOCIAL = 'social';

    public const REFERRAL = 'referral';

    public const CAMPAIGN = 'campaign';

    public const EMAIL = 'email';

    public const INTERNAL = 'internal';

    public const LABELS = [
        self::DIRECT => 'Direct',
        self::SEARCH => 'Search',
        self::SOCIAL => 'Social',
        self::REFERRAL => 'Referral',
        self::CAMPAIGN => 'Campaign',
        self::EMAIL => 'Email',
        self::INTERNAL => 'Internal',
    ];

    private const SEARCH_ENGINES = [
        'google' => 'Google', 'bing' => 'Bing', 'duckduckgo' => 'DuckDuckGo',
        'yahoo' => 'Yahoo', 'yandex' => 'Yandex', 'baidu' => 'Baidu',
        'ecosia' => 'Ecosia', 'brave' => 'Brave Search', 'startpage' => 'Startpage',
        'qwant' => 'Qwant', 'ask.com' => 'Ask',
    ];

    private const SOCIAL_NETWORKS = [
        'youtube' => 'YouTube', 'youtu.be' => 'YouTube',
        'facebook' => 'Facebook', 'fb.com' => 'Facebook', 'fb.me' => 'Facebook',
        'instagram' => 'Instagram', 'twitter' => 'X', 'x.com' => 'X', 't.co' => 'X',
        'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn',
        'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp',
        'telegram' => 'Telegram', 't.me' => 'Telegram',
        'tiktok' => 'TikTok', 'reddit' => 'Reddit', 'pinterest' => 'Pinterest',
        'quora' => 'Quora', 'medium.com' => 'Medium', 'github' => 'GitHub',
        'stackoverflow' => 'Stack Overflow', 'dev.to' => 'DEV',
    ];

    private const MAIL_HOSTS = ['mail.google', 'outlook', 'mail.yahoo', 'webmail', 'zoho', 'proton'];

    /**
     * @return array{channel: string, source: ?string, medium: ?string, campaign: ?string, referrer: ?string, referrer_host: ?string}
     */
    public static function resolve(Request $request): array
    {
        $referrer = self::trim($request->headers->get('referer'), 255);
        $host = $referrer ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $host = $host !== null && $host !== '' ? preg_replace('/^www\./', '', $host) : null;

        $utmSource = self::trim($request->query('utm_source'), 64);
        $utmMedium = self::trim($request->query('utm_medium'), 32);
        $campaign = self::trim($request->query('utm_campaign'), 64);

        // A tagged link is a stated intention. Believe it.
        if ($utmSource || $utmMedium || $campaign) {
            return [
                'channel' => self::channelForMedium($utmMedium),
                'source' => $utmSource ?: $host,
                'medium' => $utmMedium ?: 'campaign',
                'campaign' => $campaign,
                'referrer' => $referrer,
                'referrer_host' => $host,
            ];
        }

        // Shortened share links carry the click but not the platform, so the
        // one thing they do tell us (which app opened them) is worth keeping.
        if ($ref = self::trim($request->query('ref'), 64)) {
            return [
                'channel' => self::REFERRAL, 'source' => $ref, 'medium' => 'referral',
                'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
            ];
        }

        if ($host === null) {
            return [
                'channel' => self::DIRECT, 'source' => null, 'medium' => null,
                'campaign' => null, 'referrer' => null, 'referrer_host' => null,
            ];
        }

        if ($host === self::ownHost()) {
            return [
                'channel' => self::INTERNAL, 'source' => $host, 'medium' => 'internal',
                'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
            ];
        }

        foreach (self::SEARCH_ENGINES as $needle => $label) {
            if (str_contains($host, $needle)) {
                return [
                    'channel' => self::SEARCH, 'source' => $label, 'medium' => 'organic',
                    'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
                ];
            }
        }

        foreach (self::SOCIAL_NETWORKS as $needle => $label) {
            if (str_contains($host, $needle)) {
                return [
                    'channel' => self::SOCIAL, 'source' => $label, 'medium' => 'social',
                    'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
                ];
            }
        }

        foreach (self::MAIL_HOSTS as $needle) {
            if (str_contains($host, $needle)) {
                return [
                    'channel' => self::EMAIL, 'source' => $host, 'medium' => 'email',
                    'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
                ];
            }
        }

        return [
            'channel' => self::REFERRAL, 'source' => $host, 'medium' => 'referral',
            'campaign' => null, 'referrer' => $referrer, 'referrer_host' => $host,
        ];
    }

    public static function label(?string $channel): string
    {
        return self::LABELS[$channel] ?? 'Unknown';
    }

    private static function channelForMedium(?string $medium): string
    {
        return match (strtolower((string) $medium)) {
            'organic', 'search' => self::SEARCH,
            'social', 'social-network', 'sm' => self::SOCIAL,
            'email', 'newsletter', 'mail' => self::EMAIL,
            'referral' => self::REFERRAL,
            default => self::CAMPAIGN,
        };
    }

    private static function ownHost(): string
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return preg_replace('/^www\./', '', strtolower($host)) ?: 'localhost';
    }

    private static function trim(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
