<?php

/*
|--------------------------------------------------------------------------
| Course catalogue, pricing, currency and publication
|--------------------------------------------------------------------------
|
| OWNER: review these. Every price in the catalogue comes from this one file.
| Change a number, run `php artisan courses:apply-pricing`, and the catalogue
| follows. You never edit 21 database rows by hand.
|
| Two currencies, both set by hand. Deliberately NOT a live conversion: an FX
| API makes the price on the page depend on a third party being up, and can
| move what a student is charged between reading it and paying it. The USD
| figures below sit near the UGX ones at roughly 3,800/USD, rounded to prices
| that read like prices rather than conversions ($15, not $13.16).
|
| Nothing in the catalogue is free. Course 02 used to be, as a shop window,
| and is now priced with the rest: the two courses that are actually finished
| and recorded carry the catalogue, so they are what the shop window sells.
|
| 'open' below is the other half of the decision, and the more important one.
| A price is only an offer once the course is open for enrolment.
|
*/

return [

    'tiers' => [
        1 => [
            'label' => 'Foundations',
            'price' => 60_000,      // ~USD 16
            'price_usd' => 15,
        ],
        2 => [
            'label' => 'Frameworks & Mobile',
            'price' => 140_000,     // ~USD 37
            'price_usd' => 35,
        ],
        3 => [
            'label' => 'Capstone Systems',
            'price' => 280_000,     // ~USD 74
            'price_usd' => 70,
        ],
    ],

    /*
    | Per-course overrides, by course number. Anything here beats its tier.
    */
    'overrides' => [
        // The two recorded courses. Both are open for enrolment (see 'open'
        // below), and both are priced the same because they are the same
        // promise: a complete course with every lesson on video. The rest of
        // the catalogue is still being recorded, so its tier price is a
        // placeholder behind a waitlist rather than a number anyone pays.
        //
        // Course 02 was free until it became one of the two. A free course is
        // a shop window, and a shop window stops earning its keep the moment
        // it is the best thing on the shelf.
        2 => ['price' => 130_000, 'price_usd' => 33],
        25 => ['price' => 130_000, 'price_usd' => 33],

        // 47 lessons across a Laravel back office, a REST API and a Flutter
        // app. That is materially more than the other capstones and priced
        // accordingly.
        16 => ['price' => 350_000, 'price_usd' => 90],

        // The shortest course in the catalogue at 6 lessons, a crash course,
        // and priced as one rather than as a full Tier 1.
        6 => ['price' => 40_000, 'price_usd' => 10],
    ],

    /*
    | Open for enrolment, by course number.
    |
    | Published and open are two different questions. Published decides whether
    | the page exists; open decides whether there is anything to buy on it. A
    | course that is published but not open keeps its page, keeps its place in
    | the sitemap and keeps collecting names on the waitlist, which is the only
    | useful thing an unrecorded course can do.
    |
    | Unlike publication, this list is authoritative in both directions:
    | `courses:apply-pricing` opens what is named here and closes what is not.
    | Opening a course is a decision about taking money, so it is made once,
    | here, in review, and never by hand on a production database.
    |
    | A course named here is also published, because an invisible course cannot
    | be sold. The reverse is not true: whether to start advertising a course
    | that is not open is a decision about the shopfront, and it stays with the
    | owner in the admin UI rather than falling out of a pricing run.
    |
    | 02  AI-Powered Web Development (HTML, CSS & GitHub Copilot)
    | 25  AI Powered - Frontend Development with Bootstrap
    */
    'open' => [2, 25],

    /*
    | Currency
    |
    | 'UGX' is home. A visitor is shown USD when we can tell they are outside
    | Uganda, and either way they can switch with the toggle in the header
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
