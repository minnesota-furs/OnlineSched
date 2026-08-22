<?php
// OnlineSched Social Login Providers Config
return [
    'providers' => [
        'Google' => [
            'enabled' => true,
            'keys' => [
                'id' => '',
                'secret' => '',
            ],
            'scope' => 'email',
            'use-favicon' => [
                'enabled' => true,
                'favicon' => 'fa-google',
                'color' => '4285F4',
            ],
        ],
        'Telegram' => [
            'enabled' => true,
            'keys' => [
                'id' => '',
                'secret' => '',
            ],
            'scope' => 'openid profile',
            'use-favicon' => [
                'enabled' => true,
                'favicon' => 'fa-telegram',
                'color' => '229ED9',
            ],
        ],
        'Discord' => [
            'enabled' => true,
            'keys' => [
                'id' => '',
                'secret' => '',
            ],
            'scope' => 'identify email',
            'use-favicon' => [
                'enabled' => true,
                'favicon' => 'fa-discord',
                'color' => '5865F2',
            ],
        ],
        'Facebook'  => [
            'enabled' => true,
            'keys' => [
                'id' => '',
                'secret' => '',
            ],
            'scope' => 'email',
            'use-favicon' => [
                'enabled' => true,
                'favicon' => 'fa-facebook',
                'color' => '1877F2',
            ],
        ],
        'Steam' => [
            'enabled' => false,
            'no_keys' => true, // Steam does not require keys
            'scope' => '', // Steam does not use scopes
            'use-favicon' => [
                'enabled' => true,
                'favicon' => 'fa-steam',
                'color' => '171A21',
            ],
        ],
        // Further HybridAuth providers follow the same shape as the entries
        // above.
    ],
];
