<?php

namespace App\Support\Analytics;

/**
 * What is at the other end of the request, read off the user-agent string.
 *
 * Deliberately not a package. Every user-agent library is a large regex table
 * that ages badly and needs updating to keep telling you the same four things
 * this site reports: phone or laptop, which browser, which system, and whether
 * it is a person at all. The last of those is the only one that has to be
 * right, because a crawler counted as a visitor inflates every number above it.
 */
final class Agent
{
    public function __construct(
        public readonly string $device,   // desktop|mobile|tablet|bot
        public readonly ?string $browser,
        public readonly ?string $os,
        public readonly bool $isBot,
    ) {}

    /**
     * Substrings that mean "not a person". Ordered by how often they show up
     * in a small site's logs, since the first match wins.
     */
    private const BOTS = [
        'bot', 'crawl', 'spider', 'slurp', 'search', 'fetch', 'monitor', 'ping',
        'preview', 'validator', 'scanner', 'archiver', 'index',
        'curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'okhttp', 'axios', 'node-fetch', 'guzzle', 'postman', 'insomnia',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'lighthouse', 'gtmetrix', 'pingdom', 'uptimerobot', 'statuscake',
        'facebookexternalhit', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot',
        'twitterbot', 'linkedinbot', 'embedly', 'quora link preview', 'skypeuripreview',
        'apache-httpclient', 'libwww-perl', 'zgrab', 'masscan', 'nmap', 'nikto',
        'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'bytespider', 'dataforseo',
        'gptbot', 'ccbot', 'claudebot', 'anthropic', 'perplexity', 'applebot',
    ];

    /** Longest name first, because "Edg" lives inside a Chrome UA and Chrome lives inside most others. */
    private const BROWSERS = [
        'SamsungBrowser' => 'Samsung Internet',
        'UCBrowser' => 'UC Browser',
        'YaBrowser' => 'Yandex',
        'OPR/' => 'Opera',
        'Opera' => 'Opera',
        'Edg' => 'Edge',
        'Vivaldi' => 'Vivaldi',
        'Brave' => 'Brave',
        'Firefox' => 'Firefox',
        'Chrome' => 'Chrome',
        'Safari' => 'Safari',
        'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer',
    ];

    private const SYSTEMS = [
        'Windows NT 10' => 'Windows',
        'Windows NT' => 'Windows',
        'Windows' => 'Windows',
        'Android' => 'Android',
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'iPod' => 'iOS',
        'Mac OS X' => 'macOS',
        'Macintosh' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Ubuntu' => 'Ubuntu',
        'Linux' => 'Linux',
    ];

    public static function parse(?string $userAgent): self
    {
        $ua = trim((string) $userAgent);

        // An empty user-agent is a script that could not be bothered to lie.
        if ($ua === '') {
            return new self('bot', null, null, true);
        }

        $lower = strtolower($ua);

        foreach (self::BOTS as $needle) {
            if (str_contains($lower, $needle)) {
                return new self('bot', self::labelFor($ua, self::BROWSERS), self::labelFor($ua, self::SYSTEMS), true);
            }
        }

        $os = self::labelFor($ua, self::SYSTEMS);

        $device = match (true) {
            str_contains($lower, 'ipad'), str_contains($lower, 'tablet'),
            // Android without "Mobile" is the documented way to spot an Android tablet.
            str_contains($lower, 'android') && ! str_contains($lower, 'mobile') => 'tablet',
            str_contains($lower, 'mobi'), str_contains($lower, 'iphone'),
            str_contains($lower, 'ipod'), str_contains($lower, 'android') => 'mobile',
            default => 'desktop',
        };

        return new self($device, self::labelFor($ua, self::BROWSERS), $os, false);
    }

    /** @param array<string, string> $table */
    private static function labelFor(string $ua, array $table): ?string
    {
        foreach ($table as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }
}
