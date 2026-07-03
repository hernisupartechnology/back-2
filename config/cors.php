<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration — UparVital
    |--------------------------------------------------------------------------
    |
    | Solo se permite el dominio del frontend (FRONTEND_URL en .env).
    | Necesario para que Sanctum SPA (statefulApi) y las peticiones con
    | Authorization: Bearer funcionen desde front/ en desarrollo y producción.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
