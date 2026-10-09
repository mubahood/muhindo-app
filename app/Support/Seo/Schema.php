<?php

namespace App\Support\Seo;

/**
 * Builders for the structured data every page should carry.
 *
 * The course page already had a good set, built inline in its controller, and
 * every other page type had either a fragment or nothing: the case studies had
 * no structured data at all, and neither did any listing page. Extracted here
 * so a new page type gets the same shape without copying a controller, and so
 * the handful of easy mistakes are made once and fixed once.
 *
 * Breadcrumbs matter more than their size suggests. They are what turns a
 * result's grey URL line into a readable path, which is the difference between
 * "muhindomubaraka.com › e-learning › laravel-essentials" and a bare address.
 * Google reads them even when the page draws no breadcrumb of its own.
 */
final class Schema
{
    /**
     * A breadcrumb trail. The home page is prepended, so callers pass only the
     * steps below it.
     *
     * @param  array<string,string>  $trail  label => absolute url
     * @return array<string,mixed>
     */
    public static function breadcrumb(array $trail): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')]];
        $position = 1;

        foreach ($trail as $name => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                'item' => $url,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * An ordered list of things on a listing page.
     *
     * Only the url is given per item, not a nested object. A listing page that
     * describes each item in full competes with the item's own page for the
     * same query, and the item's page is the one that should win.
     *
     * @param  array<int,string>  $urls
     * @return array<string,mixed>
     */
    public static function itemList(string $name, array $urls): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => count($urls),
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'itemListElement' => array_values(array_map(
                fn (int $i, string $url) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url],
                array_keys(array_values($urls)),
                array_values($urls),
            )),
        ];
    }

    /**
     * The organisation behind the site, for pages that are about the work
     * rather than about a product.
     *
     * @return array<string,mixed>
     */
    public static function organisation(): array
    {
        $address = config('company.address');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('company.name'),
            'legalName' => config('company.name'),
            'identifier' => config('company.registration_number'),
            'url' => route('home'),
            'logo' => asset('images/icon-512.png'),
            'email' => config('company.email'),
            'telephone' => config('company.phone_e164'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'] ?? null,
                'addressLocality' => $address['locality'] ?? null,
                'addressRegion' => $address['region'] ?? null,
                'addressCountry' => 'UG',
            ],
            'sameAs' => array_values(array_column((array) config('company.social'), 'url')),
        ]);
    }

    /**
     * A delivered project, described as work rather than as a product.
     *
     * CreativeWork, not Product or SoftwareApplication: these are systems built
     * for one client and not on sale, and marking them up as products invites
     * a price and availability that do not exist.
     *
     * @return array<string,mixed>
     */
    public static function project(string $name, ?string $description, ?string $url, ?string $image = null): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'creator' => [
                '@type' => 'Organization',
                'name' => config('company.name'),
                'url' => route('home'),
            ],
            'inLanguage' => 'en',
        ]);
    }
}
