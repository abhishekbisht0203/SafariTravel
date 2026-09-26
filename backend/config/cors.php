<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    |
    | The browser only ever talks to WordPress, so the API is normally called
    | server-to-server. CORS still matters: a hardened WordPress build (or a
    | future headless front end) may call this API straight from the browser.
    |
    | Origins are opt-in. `*` disables the browser entirely, so leaving
    | API_ALLOWED_ORIGINS empty means "no cross-origin browser calls" rather
    | than "allow everything".
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('API_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-Safari-Api-Key'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
