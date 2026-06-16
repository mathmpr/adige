<?php

return [
    'connections' => [
        'default' => [
            'host' => env('DB_HOST', 'localhost'),
            'user' => env('DB_USER', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'database' => env('DB_DATABASE', 'adige'),
            'port' => env('DB_PORT', '3306'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ]
    ]
];