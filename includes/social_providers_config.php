<?php
// OnlineSched Social Login Providers Config
return [
    'providers' => [
        'Google' => [
            'enabled' => true,
            'keys' => [
                'id' => '1085930528382-26pbncpv1161eil6gj82i7qd51ji67uo.apps.googleusercontent.com',
                'secret' => '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',
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
				'id' => 'xx',
			    'secret' => 'xx',
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
			'scope' => 'email',
		    'use-favicon' => [
			    'enabled' => true,
			    'favicon' => 'fa-discord',
			    'color' =>  '4285F4',
		    ],
	    ],
	    'Facebook'  => [
            'enabled' => true,
            'keys' => [
                'id' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'secret' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            ],
            'scope' => 'email',
        ],
        'Steam' => [
            'enabled' => true,
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
