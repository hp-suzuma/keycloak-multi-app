<?php

return [
    'keycloak' => [
        'issuer' => env('KEYCLOAK_ISSUER'),
        'internal_base_url' => env('KEYCLOAK_INTERNAL_BASE_URL', env('KEYCLOAK_ISSUER')),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
    ],
];
