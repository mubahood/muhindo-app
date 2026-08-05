<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fills in country and city for visits whose IP nothing else resolved.
 *
 * Off unless config('analytics.geo.enabled') says otherwise, because it sends
 * visitor addresses to a third party and that has to be a decision somebody
 * made rather than a default they inherited. Where the host already sets a
 * GeoIP header, this is never needed.
 *
 * Batched and rate-limited to stay inside the free tier, and run on a schedule
 * rather than in a request: nobody waiting for a page should ever wait for
 * somebody else's API.
 */
class AnalyticsGeolocate extends Command
{
    protected $signature = 'analytics:geolocate {--limit=500 : Most addresses to resolve in one run}';

    protected $description = 'Resolve stored IP addresses to a country and city';

    public function handle(): int
    {
        if (! config('analytics.geo.enabled')) {
            $this->warn('Geolocation is off. Set ANALYTICS_GEO=true to enable it, and note that it sends visitor IPs to a third party.');

            return self::SUCCESS;
        }

        $size = (int) config('analytics.geo.batch', 100);
        $endpoint = (string) config('analytics.geo.endpoint');
        $resolved = 0;

        $pending = Visit::whereNull('country')
            ->whereNotNull('ip')
            ->where('ip', 'not like', '192.168.%')
            ->where('ip', 'not like', '10.%')
            ->whereNotIn('ip', ['127.0.0.1', '::1'])
            ->limit((int) $this->option('limit'))
            ->pluck('ip')
            ->unique()
            ->values();

        if ($pending->isEmpty()) {
            $this->info('Nothing to resolve.');

            return self::SUCCESS;
        }

        foreach ($pending->chunk($size) as $chunk) {
            $response = Http::timeout(20)->acceptJson()->post($endpoint, $chunk->map(fn ($ip) => [
                'query' => $ip,
                'fields' => 'status,countryCode,city,query',
            ])->values()->all());

            if (! $response->successful()) {
                $this->error('Lookup failed: HTTP '.$response->status());

                return self::FAILURE;
            }

            foreach ((array) $response->json() as $row) {
                if (($row['status'] ?? '') !== 'success') {
                    continue;
                }

                $country = strtoupper((string) ($row['countryCode'] ?? ''));
                $city = (string) ($row['city'] ?? '');

                Visit::where('ip', $row['query'])->whereNull('country')
                    ->update(['country' => $country ?: null, 'city' => $city ?: null]);
                Visitor::where('last_ip', $row['query'])->whereNull('last_country')
                    ->update(['last_country' => $country ?: null, 'last_city' => $city ?: null]);

                $resolved++;
            }

            // The free tier allows 15 batches a minute; this stays well inside it.
            sleep(5);
        }

        $this->info("Resolved {$resolved} ".str('address')->plural($resolved).'.');

        return self::SUCCESS;
    }
}
