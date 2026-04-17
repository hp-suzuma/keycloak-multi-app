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

    'keycloak' => [
        'issuer' => env('KEYCLOAK_ISSUER'),
        'internal_base_url' => env('KEYCLOAK_INTERNAL_BASE_URL'),
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'accepted_client_ids' => array_values(array_filter(array_map(
            static fn (string $clientId): string => trim($clientId),
            explode(',', (string) env('KEYCLOAK_ACCEPTED_CLIENT_IDS', (string) env('KEYCLOAK_CLIENT_ID')))
        ))),
        'public_key' => env('KEYCLOAK_PUBLIC_KEY'),
        'jwks_url' => env('KEYCLOAK_JWKS_URL'),
        'jwks_cache_ttl' => env('KEYCLOAK_JWKS_CACHE_TTL', 300),
        'discovery_cache_ttl' => env('KEYCLOAK_DISCOVERY_CACHE_TTL', 300),
    ],

];
