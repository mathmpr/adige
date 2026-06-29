<?php

use Adige\core\Adige;
use Adige\core\BaseView;
use Adige\core\routing\Router;

return [
    Adige::VIEW_HANDLER => [
        'class' => BaseView::class,
        '__construct()' => [
            __DIR__ . '/views',
        ],
    ],
    Adige::MIGRATIONS_CONFIG => [
        'path' => __DIR__ . '/migrations',
    ],
    Adige::ROUTER_HANDLER => [
        'class' => Router::class,
        '__construct()' => [
            '@request',
            '@response',
            true,
            'index',
        ],
        'controllerNamespaces' => [
            'Adige\\console\\controllers',
            'Tests\\Fixtures\\consumerapp\\controllers',
        ],
    ],
];
