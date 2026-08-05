<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search engine ownership verification
    |--------------------------------------------------------------------------
    |
    | Meta tags proving to a search engine that this site belongs to you, which
    | is what unlocks Search Console, Bing Webmaster Tools and the rest.
    |
    | Keys are the meta name exactly as the provider specifies it; values are
    | the token they issue. An empty value renders nothing, so an unused
    | provider costs a line here and nothing in the page.
    |
    | Note that the token a provider gives you is tied to the METHOD you pick.
    | Google's "domain property" is verified by a DNS TXT record and never by a
    | tag, because the whole point of it is to cover every subdomain and every
    | protocol, which no single page can prove. If you are verifying a domain
    | property, the token belongs in DNS at your registrar; the tag below is
    | for a "URL prefix" property, where Google offers the HTML tag method.
    |
    | Defaults are filled in rather than left to env because these are public
    | values that appear in the page source of every request anyway, and a
    | verification that silently stops working after a deploy to a machine with
    | a different .env is worse than useless.
    |
    */

    'verifications' => [
        'google-site-verification' => env('GOOGLE_SITE_VERIFICATION', 'ygN9nLO7olHdFFO5BqdefFPco42A90ZD-3bvITTr0yw'),
        'msvalidate.01' => env('BING_SITE_VERIFICATION'),
        'yandex-verification' => env('YANDEX_SITE_VERIFICATION'),
        'p:domain_verify' => env('PINTEREST_SITE_VERIFICATION'),
        'facebook-domain-verification' => env('FACEBOOK_DOMAIN_VERIFICATION'),
    ],

];
