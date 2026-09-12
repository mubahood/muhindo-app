<?php

/*
 * SOLAVIA GROUP LIMITED: the company behind this site and everything it sells.
 *
 * One definition, because the same facts appear on the company pages, in the
 * footer of every page, in the structured data a search engine reads, in
 * .well-known/company.json, and in what a payment provider checks when it
 * reviews the business. Several hand-written copies of a registration number
 * is several chances for one of them to be wrong, and the wrong one is the one
 * somebody official reads.
 */
return [
    'name' => 'SOLAVIA GROUP LIMITED',
    'registration_number' => '80048169153974',
    'registrar' => 'Uganda Registration Services Bureau',
    'incorporated_on' => '22 July 2026',
    'business' => 'Software development and digital services',
    'isic' => '6201',

    'address' => [
        'street' => 'Plot 2335, Buwambo-Katadde-Najjo Road',
        'trading_centre' => 'Buwambo Trading Center',
        'division' => 'Gombe Division',
        'locality' => 'Nansana Municipality',
        'region' => 'Wakiso District',
        'country' => 'Uganda',
        'po_box' => 'P.O. Box 214231, Kampala GPO',
    ],

    'phone' => '+256 783 204 665',
    'phone_e164' => '+256783204665',
    'email' => 'solaviaug@gmail.com',
    'whatsapp' => 'https://wa.me/256783204665',

    /*
     * Muvule Green and Equator Ember, taken from the logo artwork and
     * confirmed against the letterhead itself, whose green samples at #073b31.
     * See the note in the build report: the brief quoted #1a7a4a, which is a
     * noticeably lighter green and would clash with the logo beside it.
     */
    'brand' => ['green' => '#0C3B2E', 'ember' => '#E8602C'],

    'leadership' => [
        ['name' => 'Muhindo Mubaraka', 'title' => 'Managing Director', 'photo' => 'images/portrait.jpg'],
        ['name' => 'Katushabe Aminah', 'title' => 'Director', 'photo' => null],
        ['name' => 'Ndugwa Adam', 'title' => 'Director', 'photo' => null],
    ],

    /*
     * Scale, stated once. These are the two numbers the company quotes for
     * itself; they are deliberately not computed from this site's own database,
     * which knows nothing about the app platforms.
     */
    'scale' => [
        'registered_users' => '118,000',
        'completed_payments' => '18,000',
    ],

    'social' => [
        ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f', 'url' => 'https://www.facebook.com/mubahood2/'],
        ['label' => 'Instagram', 'icon' => 'fab fa-instagram', 'url' => 'https://www.instagram.com/ugnewz24/'],
        ['label' => 'X', 'icon' => 'fab fa-x-twitter', 'url' => 'https://x.com/mubahood360'],
    ],
];
