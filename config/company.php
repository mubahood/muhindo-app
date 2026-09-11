<?php

/*
 * The legal entity behind the site, and the social accounts that belong to it.
 *
 * One definition, because the same facts appear in the refund policy, in the
 * footer, in the structured data a search engine reads and in what a payment
 * provider checks when it reviews the business. Three copies of a registration
 * number is three chances for one of them to be wrong, and the one that is
 * wrong is the one somebody official reads.
 */
return [
    'name' => 'SOLAVIA GROUP LIMITED',
    'registration_number' => '80048169153974',

    'address' => [
        'street' => 'Plot 2335, Buwambo-Katadde-Najjo Road',
        'locality' => 'Nansana Municipality',
        'region' => 'Wakiso District',
        'country' => 'Uganda',
        'po_box' => 'P.O. Box 214231, Kampala',
    ],

    'phone' => '+256 783 204 665',
    // The dialable form. The spaced one above is for reading.
    'phone_e164' => '+256783204665',
    'email' => 'solaviaug@gmail.com',

    /*
     * Public profiles. These are also emitted as schema.org sameAs, which is
     * how a search engine ties the site and the accounts to one organisation.
     */
    'social' => [
        ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'url' => 'https://www.facebook.com/mubahood2/'],
        ['label' => 'Instagram', 'icon' => 'fab fa-instagram', 'url' => 'https://www.instagram.com/ugnewz24/'],
        ['label' => 'X', 'icon' => 'fab fa-x-twitter', 'url' => 'https://x.com/mubahood360'],
    ],

    /* Other things this company runs, shown so each site vouches for the others. */
    'products' => [
        ['label' => 'UGNEWS24', 'url' => 'https://ugnews24.info', 'note' => 'Uganda local news'],
        ['label' => 'LugaFlix', 'url' => 'https://movies.mruodel.com', 'note' => 'Luganda translated movies'],
    ],
];
