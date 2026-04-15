<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
        'lat' => env('OPENWEATHER_LAT', 6.9214),
        'lon' => env('OPENWEATHER_LON', 122.079),
        'verify_ssl' => filter_var(env('OPENWEATHER_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    'zcwd' => [
        'url' => env('ZCWD_WATER_URL', 'https://zcwd.gov.ph/production_new_bak.php'),
        'cache_ttl' => (int) env('ZCWD_WATER_CACHE_SECONDS', 900),
        'verify_ssl' => filter_var(env('ZCWD_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    'zamcelco' => [
        'power_rates_api_url' => env('ZAMCELCO_POWER_RATES_API_URL', 'https://consumers.zamcelco.com.ph/api/v1/mobile/power-rates'),
        'cache_ttl' => (int) env('ZAMCELCO_CACHE_SECONDS', 3600),
        'verify_ssl' => filter_var(env('ZAMCELCO_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | MetroFuel Tracker — optional; Dashboard fuel widget uses Gasmoto by default (see gasmoto).
    */
    'metrofuel' => [
        'base_url' => env('METROFUEL_BASE_URL', 'https://metrofueltracker.com'),
        'north' => env('METROFUEL_NORTH', 7.6108),
        'south' => env('METROFUEL_SOUTH', 6.9512),
        'east' => env('METROFUEL_EAST', 125.7022),
        'west' => env('METROFUEL_WEST', 125.2126),
        'region_label' => env('METROFUEL_REGION_LABEL', 'Metro Davao (Davao City area)'),
        'disclaimer' => env('METROFUEL_DISCLAIMER', 'MetroFuel does not list Zamboanga City yet. Prices are community reports from the mapped region below.'),
        'table_rows' => (int) env('METROFUEL_TABLE_ROWS', 6),
        'cache_ttl' => (int) env('METROFUEL_CACHE_SECONDS', 1800),
        'verify_ssl' => filter_var(env('METROFUEL_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Gasmoto (https://gasmoto.app/map) — city-level prices via public Supabase REST (same anon key as the web app).
    | city_id 653 = Zamboanga City in their cities table.
    */
    'gasmoto' => [
        'supabase_url' => env('GASMOTO_SUPABASE_URL', 'https://haleazxkbtnvngpktaxc.supabase.co'),
        'supabase_anon_key' => env('GASMOTO_SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhhbGVhenhrYnRudm5ncGt0YXhjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzM0MzAxOTksImV4cCI6MjA4OTAwNjE5OX0.-iPxoqTZKzUKfSzHZsyVzpZOz7H1Q4vWZe6uQJX_oPA'),
        'city_id' => (int) env('GASMOTO_CITY_ID', 653),
        'city_label' => env('GASMOTO_CITY_LABEL', 'Zamboanga City'),
        'stations_area' => env('GASMOTO_STATIONS_AREA', 'Zamboanga City'),
        'region_label' => env('GASMOTO_REGION_LABEL', 'Gasmoto · Zamboanga City'),
        'disclaimer' => env('GASMOTO_DISCLAIMER', 'Indicative city rates from Gasmoto (community-sourced). Per-station prices may differ; confirm at the pump.'),
        'map_url' => env('GASMOTO_MAP_URL', 'https://gasmoto.app/map'),
        'table_rows' => (int) env('GASMOTO_TABLE_ROWS', 6),
        'cache_ttl' => (int) env('GASMOTO_CACHE_SECONDS', 1800),
        'verify_ssl' => filter_var(env('GASMOTO_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
        /*
        | DOE-style columns (labels). Keys map Gasmoto product names in GasmotoFuelService.
        */
        'doe_columns' => [
            'unleaded_doe' => 'UnleadedDOE',
            'premium_95_doe' => 'Premium95DOE',
            'premium_98' => 'Premium98',
            'diesel_doe' => 'DieselDOE',
            'premium_diesel_doe' => 'Premium DieselDOE',
            'kerosene' => 'Kerosene',
        ],
    ],

    /*
    | PCSO lotto: optional JSON (PCSO_LOTTO_API_URL), else HTML scrape of PCSO_LOTTO_PAGE_URL (default lottopcso.com).
    | JSON success uses PCSO_LOTTO_PCSO_OFFICIAL_URL for the dashboard “official” link.
    */
    'pcso' => [
        'enabled' => filter_var(env('PCSO_LOTTO_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'api_url' => env('PCSO_LOTTO_API_URL'),
        'page_url' => env('PCSO_LOTTO_PAGE_URL', env('PCSO_LOTTO_SEARCH_URL', 'https://www.lottopcso.com/')),
        'pcso_official_url' => env('PCSO_LOTTO_PCSO_OFFICIAL_URL', 'https://www.pcso.gov.ph/SearchLottoResult.aspx'),
        'cache_ttl' => (int) env('PCSO_LOTTO_CACHE_SECONDS', 3600),
        'carousel_max' => (int) env('PCSO_LOTTO_CAROUSEL_MAX', 15),
        'verify_ssl' => filter_var(env('PCSO_LOTTO_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

];
