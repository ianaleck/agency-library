<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clerk Configuration
    |--------------------------------------------------------------------------
    */
    'clerk' => [
        'secret_key' => env('CLERK_SECRET_KEY'),
        'publishable_key' => env('CLERK_PUBLISHABLE_KEY'),
        'session_token_cookie' => '__session',
        'frontend_api' => env('CLERK_FRONTEND_API', 'https://api.clerk.dev/v1'),
        'backend_api' => env('CLERK_BACKEND_API', 'https://api.clerk.dev/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Resolution
    |--------------------------------------------------------------------------
    |
    | Define how users should be matched with your application's database
    */
    'user_model' => \App\Models\User::class,
    'user_identifier' => 'clerk_id', // Column in your users table that stores Clerk ID

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'web' => [
            'driver' => 'clerk',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'clerk',
            'provider' => 'users',
        ],
    ],
];