<?php
// Copy this file to config.local.php and edit for your environment.
// Values here override config/config.php.
return [
    'app' => [
        'debug'      => false,
        'base_url'   => 'https://your-domain.example',
        'jwt_secret' => 'replace-with-a-long-random-string',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'lucky_mall',
        'user' => 'lucky',
        'pass' => 'strong-password',
    ],
    'payments' => [
        'usdt' => [
            'address' => 'TYourTronUsdtAddress',
            'rate'    => 300,
        ],
    ],
];
