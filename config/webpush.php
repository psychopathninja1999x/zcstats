<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Generate keys: php artisan zcstats:webpush-vapid
    | Subject must be a mailto: or https: URL (see Web Push spec).
    |
    */

    'enabled' => (bool) env('WEBPUSH_ENABLED', false)
        && is_string(env('WEBPUSH_VAPID_PUBLIC_KEY'))
        && env('WEBPUSH_VAPID_PUBLIC_KEY') !== ''
        && is_string(env('WEBPUSH_VAPID_PRIVATE_KEY'))
        && env('WEBPUSH_VAPID_PRIVATE_KEY') !== '',

    'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY', ''),

    'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY', ''),

    'subject' => env('WEBPUSH_VAPID_SUBJECT', 'mailto:admin@localhost'),

    /*
    | If push fails with "cURL error 60: SSL certificate problem: unable to get local issuer certificate",
    | set this to a readable PEM file (Mozilla CA bundle or your OS store). Examples:
    | - Download https://curl.se/ca/cacert.pem and use WEBPUSH_CURL_CAINFO=storage/app/cacert.pem
    | - Linux: /etc/ssl/certs/ca-certificates.crt
    */
    'curl_ca_bundle' => env('WEBPUSH_CURL_CAINFO', ''),

];
