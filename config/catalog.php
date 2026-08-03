<?php

/*
|--------------------------------------------------------------------------
| Course catalogue — pricing and publication
|--------------------------------------------------------------------------
|
| OWNER: review these before publishing. Nothing here was decided for you.
|
| Every price in the catalogue comes from this one file. Change a number,
| run `php artisan courses:apply-pricing`, and the catalogue follows — you
| never edit 21 database rows by hand.
|
| The suggested figures below are a starting point, not a recommendation I can
| stand behind: I do not know your market, what your students earn, or what you
| already charge people privately. They are anchored on Tier 1 being free —
| which is a deliberate and defensible strategy (the foundations bring people
| in, the capstones are what they pay for) — and on the capstones being real
| portfolio systems that take weeks of work.
|
| UGX. Change freely.
|
*/

return [

    /*
    | What each tier costs, and whether importing publishes it.
    |
    | Tier 1 is free: it is the on-ramp.
    |
    | All three tiers are public at the owner's instruction. NOTE that the Tier
    | 2 and 3 figures below are still the suggested ones — they are live prices
    | a student can be charged, so they want a decision rather than a default.
    */
    'tiers' => [
        1 => [
            'label' => 'Foundations',
            'price' => 0,           // free — the on-ramp
            'publish' => true,
        ],
        2 => [
            'label' => 'Frameworks & Mobile',
            'price' => 120_000,     // SUGGESTED — the owner has not set this yet
            'publish' => true,      // owner asked for the whole catalogue to be public
        ],
        3 => [
            'label' => 'Capstone Systems',
            'price' => 250_000,     // SUGGESTED — the owner has not set this yet
            'publish' => true,
        ],
    ],

    /*
    | Per-course overrides, by course number. Anything set here wins over its
    | tier. Use this for a course that is longer, deeper or more in demand than
    | its tier suggests.
    |
    | Example — the flagship capstone is 47 lessons across a Laravel back
    | office, a REST API and a Flutter app, which is not the same job as the
    | other Tier 3 courses:
    |
    |   16 => ['price' => 350_000],
    */
    'overrides' => [
        // 16 => ['price' => 350_000, 'publish' => false],
    ],

    'currency' => 'UGX',

];
