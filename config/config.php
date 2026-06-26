<?php
/**
 * JackOne · 一夺 configuration. Edit the values below for your environment.
 * Copy this file to config.local.php and it will override these settings.
 */
return [
    'app' => [
        'name'      => 'JackOne',
        'env'       => getenv('APP_ENV') ?: 'production',
        'debug'     => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'base_url'  => rtrim(getenv('APP_URL') ?: '', '/'),
        'timezone'  => getenv('APP_TZ') ?: 'Asia/Colombo',
        'jwt_secret'=> getenv('APP_SECRET') ?: 'change-me-to-a-long-random-secret',
    ],
    'db' => [
        'host'      => getenv('DB_HOST') ?: '127.0.0.1',
        'port'      => (int)(getenv('DB_PORT') ?: 3306),
        'name'      => getenv('DB_NAME') ?: 'lucky_mall',
        'user'      => getenv('DB_USER') ?: 'root',
        'pass'      => getenv('DB_PASS') ?: '',
        'charset'   => 'utf8mb4',
    ],
    'session' => [
        'cookie'    => 'lm_session',
        'lifetime'  => 60 * 60 * 24 * 14,  // 14 days
    ],
    'upload' => [
        'path'      => __DIR__ . '/../public/uploads',
        'max_size'  => 5 * 1024 * 1024,
        'mime'      => ['image/jpeg','image/png','image/webp','image/gif'],
    ],
    'commission' => [
        'rate'      => 0.10,
    ],
    'payments' => [
        'gateways'  => ['manual','usdt','stripe'],
        'usdt'      => [
            'address'   => getenv('USDT_ADDRESS') ?: '',
            'rate'      => (float)(getenv('USDT_RATE') ?: 1),    // 1 USDT = N USD (pegged 1:1)
        ],
        'stripe'    => [
            'pk'        => getenv('STRIPE_PK') ?: '',
            'sk'        => getenv('STRIPE_SK') ?: '',
            'webhook'   => getenv('STRIPE_WEBHOOK') ?: '',
        ],
    ],
];
