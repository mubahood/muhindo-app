<?php

/*
 * Everything SOLAVIA GROUP LIMITED sells or operates, grouped the way a
 * reviewer asks about it: what you buy here, what organisations buy, and what
 * consumers install.
 *
 * `url` is null wherever the destination does not currently return 200. The
 * Play Store listings for the Android apps are down while the developer
 * account is being restored, and a dead link on the page a payment provider is
 * using to verify the business is worse than no link: it reads as a product
 * that does not exist. Each of those carries `link_note` instead, so the card
 * says plainly why there is nothing to click.
 */
return [
    [
        'heading' => 'Learning and digital products',
        'blurb' => 'Sold directly on this site. Paid once, delivered immediately.',
        'items' => [
            [
                'name' => 'e-Learning courses',
                'description' => 'Practical programming courses in plain English, with a certificate you can verify.',
                'price' => 'UGX 40,000 to UGX 60,000 per course',
                'platforms' => ['Web'],
                'url' => '/e-learning',
                'image' => 'images/courses/ai-powered-web-development-html-css-github-copilot.png',
            ],
            [
                'name' => 'Source code and templates',
                'description' => 'Complete, working systems you can buy, open and build on: inventory, e-commerce, hotel and school starters.',
                'price' => 'UGX 280,000 to UGX 550,000',
                'platforms' => ['Web', 'Instant download'],
                'url' => '/source-code',
                'image' => 'images/products/invetotrack-inventory-management-system.svg',
            ],
        ],
    ],

    [
        'heading' => 'Software for organisations',
        'blurb' => 'Systems deployed for schools, health facilities and government.',
        'items' => [
            [
                'name' => 'School Dynamics',
                'description' => 'School management SaaS covering admissions, fees, attendance, results and parent portals.',
                'price' => 'Subscription, quoted per school',
                'platforms' => ['Web', 'Android', 'iOS'],
                'url' => 'https://schooldynamics.ug',
                'image' => 'images/systems/school-dynamics.svg',
            ],
            [
                'name' => 'Hospital Management System',
                'description' => 'End-to-end healthcare records: appointments, pharmacy, laboratory, billing and insurance claims.',
                'price' => 'Quoted per facility',
                'platforms' => ['Web'],
                'url' => 'https://globalhealthrescue.com',
                'image' => 'images/systems/hospital-management.svg',
            ],
            [
                'name' => 'ULITS',
                'description' => 'National livestock identification and traceability system, built for the Ministry of Agriculture, Animal Industry and Fisheries.',
                'price' => null,
                'price_note' => 'Delivered project',
                'platforms' => ['Web', 'Android', 'iOS'],
                'url' => 'https://u-lits.com',
                'image' => 'images/systems/ulits.svg',
            ],
        ],
    ],

    [
        'heading' => 'Consumer apps',
        'blurb' => 'Streaming built for Ugandan audiences at home and in the diaspora.',
        'items' => [
            [
                'name' => 'LugaFlix',
                'description' => 'Luganda translated movies and series, streamed or downloaded for offline viewing.',
                'price' => 'Subscription UGX 2,500 to UGX 50,000',
                'platforms' => ['Android', 'iOS', 'Web'],
                'url' => 'https://movies.mruodel.com',
                'extra_links' => [
                    ['label' => 'App Store', 'url' => 'https://apps.apple.com/us/app/lugaflix-luganda-movies-tra/id6777522770'],
                ],
                // The app's own icon, so the card shows the thing a customer
                // actually taps rather than a placeholder monogram.
                'image' => 'images/systems/lugaflix.png',
            ],
        ],
    ],
];
