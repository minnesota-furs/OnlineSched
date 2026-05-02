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
		            'color' =>  '4285F4',
	        ]

        ],
	    'Telegram' => [
				'enabled' => true,
		    'keys' => [
				'id' => '',
			    'secret' => '',
		    ],
			'scope' => 'email',
		    'use-favicon' => [
			    'enabled' => true,
			    'favicon' => 'fa-telegram',
			    'color' =>  '4285F4',
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
			    'color' =>  '4285F4',
		    ],
	    ],
	    'Facebook'  => [
            'enabled' => true,
            'keys' => [
                'id' => '',
                'secret' => '',
            ],
            'scope' => 'email',
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
        // Add more providers here as needed
        // 'Yahoo' => [...],
        // 'Facebook' => [...],
        // 'Twitter' => [...],
        // 'Instagram' => [...],
    ],
];
