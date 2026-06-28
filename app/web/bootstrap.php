<?php

use Adige\core\Adige;
use Adige\core\http\http\WebResponse;
use Adige\core\database\Connection;
use Adige\core\BaseView;

return [
    Adige::ROUTER_HANDLER => [
        '__construct()' => [
            '@request',
            '@response',
            true
        ],
    ],
    Adige::RESPONSE_HANDLER => WebResponse::class,
    Adige::DB_HANDLER => [
        'class' => Connection::class,
        'instant' => true,
        '__construct()' => [
            'host' => env('DB_HOST', 'localhost'),
            'user' => env('DB_USER', 'root'),
            'password' => env('DB_PASSWORD', 'root'),
            'database' => env('DB_DATABASE', 'adige'),
            'port' => '3306',
        ],
    ],
    Adige::SCHEMA_CONFIG => [
        'cache' => 'file',
        'path' => APP_ROOT . 'schema.json',
    ],
    Adige::VIEW_HANDLER => [
        'class' => BaseView::class,
        'instant' => true,
        '__construct()' => [
            'viewDirectory' => APP_ROOT . 'views',
        ],
    ]
];