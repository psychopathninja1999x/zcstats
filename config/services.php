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

    /*
    | Earthquakes near the civic reference point — U.S. Geological Survey FDSN API (free, no key).
    | Official Philippines agency: PHIVOLCS (link shown on dashboard for cross-check).
    */
    'earthquake' => [
        'enabled' => filter_var(env('EARTHQUAKE_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'lat' => env('EARTHQUAKE_LAT', env('OPENWEATHER_LAT', 6.9214)),
        'lon' => env('EARTHQUAKE_LON', env('OPENWEATHER_LON', 122.079)),
        'city_label' => env('EARTHQUAKE_CITY_LABEL', 'Zamboanga City'),
        'radius_km' => (float) env('EARTHQUAKE_RADIUS_KM', 650),
        'min_magnitude' => (float) env('EARTHQUAKE_MIN_MAGNITUDE', 4.0),
        'lookback_days' => (int) env('EARTHQUAKE_LOOKBACK_DAYS', 30),
        'limit' => (int) env('EARTHQUAKE_LIMIT', 35),
        'cache_ttl' => (int) env('EARTHQUAKE_CACHE_SECONDS', 600),
        'usgs_query_url' => env('EARTHQUAKE_USGS_QUERY_URL', 'https://earthquake.usgs.gov/fdsnws/event/1/query'),
        'usgs_home_url' => env('EARTHQUAKE_USGS_HOME_URL', 'https://earthquake.usgs.gov/earthquakes/map/'),
        'phivolcs_url' => env('EARTHQUAKE_PHIVOLCS_URL', 'https://www.phivolcs.dost.gov.ph/'),
        'verify_ssl' => filter_var(
            env('EARTHQUAKE_VERIFY_SSL', env('OPENWEATHER_VERIFY_SSL', 'true')),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
    | Tropical cyclones — GDACS public GeoJSON (event list + track geometry; sources include JTWC).
    | Official Philippines agency for domestic warnings: PAGASA (link on dashboard).
    */
    'typhoon' => [
        'enabled' => filter_var(env('TYPHOON_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'lat' => env('TYPHOON_LAT', env('OPENWEATHER_LAT', 6.9214)),
        'lon' => env('TYPHOON_LON', env('OPENWEATHER_LON', 122.079)),
        'city_label' => env('TYPHOON_CITY_LABEL', 'Zamboanga City'),
        'radius_km' => (float) env('TYPHOON_RADIUS_KM', 2800),
        'max_storms' => (int) env('TYPHOON_MAX_STORMS', 4),
        'list_lookback_days' => (int) env('TYPHOON_LIST_LOOKBACK_DAYS', 120),
        'list_cache_ttl' => (int) env('TYPHOON_LIST_CACHE_SECONDS', 1200),
        'geometry_cache_ttl' => (int) env('TYPHOON_GEOMETRY_CACHE_SECONDS', 1800),
        'gdacs_list_url' => env('TYPHOON_GDACS_LIST_URL', 'https://www.gdacs.org/gdacsapi/api/events/geteventlist/SEARCH'),
        'gdacs_geometry_url_template' => env(
            'TYPHOON_GDACS_GEOMETRY_URL',
            'https://www.gdacs.org/gdacsapi/api/polygons/getgeometry?eventtype={eventtype}&eventid={eventid}&episodeid={episodeid}'
        ),
        'gdacs_url' => env('TYPHOON_GDACS_HOME_URL', 'https://www.gdacs.org/'),
        'pagasa_url' => env('TYPHOON_PAGASA_URL', 'https://bagong.pagasa.dost.gov.ph/tropical-cyclone'),
        'verify_ssl' => filter_var(
            env('TYPHOON_VERIFY_SSL', env('OPENWEATHER_VERIFY_SSL', 'true')),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
    | Prayer times for Zamboanga City via Aladhan (JSON API). Muslim Pro’s web app
    | is behind Vercel bot protection, so it cannot be scraped from PHP; times here
    | use the same coordinates with a configurable calculation method (default MWL).
    */
    'prayer_times' => [
        'enabled' => filter_var(env('PRAYER_TIMES_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'api_base' => env('PRAYER_TIMES_API_BASE', 'https://api.aladhan.com/v1'),
        'lat' => env('PRAYER_TIMES_LAT', env('OPENWEATHER_LAT', 6.9214)),
        'lon' => env('PRAYER_TIMES_LON', env('OPENWEATHER_LON', 122.079)),
        'method' => (int) env('PRAYER_TIMES_METHOD', 3),
        'city_label' => env('PRAYER_TIMES_CITY_LABEL', 'Zamboanga City'),
        'source_url' => env('PRAYER_TIMES_SOURCE_URL', 'https://aladhan.com'),
        'cache_ttl' => (int) env('PRAYER_TIMES_CACHE_SECONDS', 3600),
        'verify_ssl' => filter_var(
            env('PRAYER_TIMES_VERIFY_SSL', env('OPENWEATHER_VERIFY_SSL', 'true')),
            FILTER_VALIDATE_BOOLEAN
        ),
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
    | DA Price Monitoring — scrapes https://www.da.gov.ph/price-monitoring/ for
    | Weekly Average Prices and Daily Price Index PDFs.
    */
    'da_prices' => [
        'url' => env('DA_PRICES_URL', 'https://www.da.gov.ph/price-monitoring/'),
        'cache_ttl' => (int) env('DA_PRICES_CACHE_SECONDS', 3600),
        'max_daily' => (int) env('DA_PRICES_MAX_DAILY', 7),
        'max_weekly' => (int) env('DA_PRICES_MAX_WEEKLY', 4),
        'verify_ssl' => filter_var(env('DA_PRICES_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
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
