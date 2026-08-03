<?php

/*
|--------------------------------------------------------------------------
| Course catalogue — pricing, currency and publication
|--------------------------------------------------------------------------
|
| OWNER: review these. Every price in the catalogue comes from this one file.
| Change a number, run `php artisan courses:apply-pricing`, and the catalogue
| follows — you never edit 21 database rows by hand.
|
| Two currencies, both set by hand. Deliberately NOT a live conversion: an FX
| API makes the price on the page depend on a third party being up, and can
| move what a student is charged between reading it and paying it. The USD
| figures below sit near the UGX ones at roughly 3,800/USD, rounded to prices
| that read like prices rather than conversions ($15, not $13.16).
|
| Only Course 02 (AI-Powered Web Development) is free — it is the shop window.
| Everything else is paid.
|
*/

return [

    'tiers' => [
        1 => [
            'label' => 'Foundations',
            'price' => 60_000,      // ~USD 16
            'price_usd' => 15,
            'publish' => true,
        ],
        2 => [
            'label' => 'Frameworks & Mobile',
            'price' => 140_000,     // ~USD 37
            'price_usd' => 35,
            'publish' => true,
        ],
        3 => [
            'label' => 'Capstone Systems',
            'price' => 280_000,     // ~USD 74
            'price_usd' => 70,
            'publish' => true,
        ],
    ],

    /*
    | Per-course overrides, by course number. Anything here beats its tier.
    */
    'overrides' => [
        // The free one. It is the most-featured course in the catalogue and
        // the best possible advert for the rest: somebody who finishes it has
        // already learned how this instructor teaches.
        2 => ['price' => 0, 'price_usd' => 0],

        // 47 lessons across a Laravel back office, a REST API and a Flutter
        // app. That is materially more than the other capstones and priced
        // accordingly.
        16 => ['price' => 350_000, 'price_usd' => 90],

        // The shortest course in the catalogue at 6 lessons — a crash course,
        // and priced as one rather than as a full Tier 1.
        6 => ['price' => 40_000, 'price_usd' => 10],
    ],

    /*
    | Currency
    |
    | 'UGX' is home. A visitor is shown USD when we can tell they are outside
    | Uganda, and either way they can switch with the toggle in the header —
    | an explicit choice always wins over a guess about where somebody is.
    */
    'currencies' => [
        'UGX' => ['symbol' => 'UGX', 'label' => 'Ugandan shilling', 'decimals' => 0],
        'USD' => ['symbol' => '$', 'label' => 'US dollar', 'decimals' => 0],
    ],

    'default_currency' => 'UGX',

    // Countries billed in shillings. Everywhere else defaults to USD.
    'ugx_countries' => ['UG'],

];
