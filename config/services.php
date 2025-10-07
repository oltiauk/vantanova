<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'mandrill' => [
        'secret' => env('MANDRILL_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'google' => [
        'client_id' => env('SSO_GOOGLE_CLIENT_ID'),
        'client_secret' => env('SSO_GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
        'hd' => env('SSO_GOOGLE_HOSTED_DOMAIN'),
    ],

    'spotify' => [
        
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
    ],

    'soundstats' => [
        'api_key' => env('SOUNDSTATS_API_KEY', 'ef5tgzWyAbRkl9faVfAKlVYrqr0T5HkGX8LwizjcpIs'),
        'base_url' => env('SOUNDSTATS_BASE_URL', 'https://soundstat.info'),
    ],
    'reccobeats' => [
        'base_url' => env('RECCOBEATS_BASE_URL', 'https://reccobeats.com'),
    ],
    
    'rapidapi' => [
        'host' => env('RAPIDAPI_HOST', 'spotify23.p.rapidapi.com'),
        'key' => env('RAPIDAPI_KEY'),
        'base_url' => env('RAPIDAPI_BASE_URL', 'https://spotify23.p.rapidapi.com'),
    ],

    'rapidapi_spotify' => [
        'primary_host' => env('RAPIDAPI_SPOTIFY_PRIMARY_HOST', env('RAPIDAPI_HOST', 'spotify81.p.rapidapi.com')),
        'primary_key' => env('RAPIDAPI_SPOTIFY_PRIMARY_KEY', env('RAPIDAPI_KEY')),
        'backup_host' => env('RAPIDAPI_SPOTIFY_BACKUP_HOST', 'spotify-web2.p.rapidapi.com'),
        'backup_key' => env('RAPIDAPI_SPOTIFY_BACKUP_KEY', env('RAPIDAPI_KEY')),
        'key' => env('RAPIDAPI_KEY'), // Use existing RAPIDAPI_KEY
    ],
    
    'soundcloud' => [
        'client_id' => env('SOUNDCLOUD_CLIENT_ID'),
        'client_secret' => env('SOUNDCLOUD_CLIENT_SECRET'),
    ],
];
