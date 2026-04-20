<?php

return [
    'default_center' => [
        'lat' => (float) env('MAP_DEFAULT_LAT', -6.7924),
        'lng' => (float) env('MAP_DEFAULT_LNG', 39.2083),
    ],

    'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 12),
    'min_zoom' => (int) env('MAP_MIN_ZOOM', 5),
    'max_zoom' => (int) env('MAP_MAX_ZOOM', 19),

    'tiles' => [
        'url' => env('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env(
            'MAP_TILE_ATTRIBUTION',
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        ),
    ],

    'geocoder' => [
        'provider' => env('MAP_GEOCODER_PROVIDER', 'locationiq'),
        'base_url' => env('MAP_GEOCODER_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'email' => env('MAP_GEOCODER_EMAIL'),
        'language' => env('MAP_GEOCODER_LANGUAGE', 'en'),
        'timeout' => (int) env('MAP_GEOCODER_TIMEOUT', 8),
        'search_limit' => (int) env('MAP_GEOCODER_SEARCH_LIMIT', 5),
        'cache_ttl_minutes' => (int) env('MAP_GEOCODER_CACHE_TTL_MINUTES', 15),
        'verify_ssl' => filter_var(env('MAP_GEOCODER_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
        'user_agent' => env('MAP_GEOCODER_USER_AGENT', env('APP_NAME', 'RSRS') . '/1.0'),
        'autocomplete' => [
            'provider' => env('MAP_AUTOCOMPLETE_PROVIDER', env('MAP_GEOCODER_PROVIDER', 'locationiq')),
            'base_url' => env('MAP_AUTOCOMPLETE_BASE_URL', 'https://api.locationiq.com/v1'),
            'api_key' => env('MAP_AUTOCOMPLETE_API_KEY'),
            'countrycodes' => env('MAP_AUTOCOMPLETE_COUNTRYCODES', 'tz'),
        ],
    ],
];
