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

];
