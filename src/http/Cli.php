<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\http\socket\Server;

try {
    Console::addCommands(Server::class, [
        new Command('start', [
            'port' => [
                'short' => 'p',
                'default' => 8080,
                'description' => 'Port to listen'
            ],
            'host' => [
                'short' => 'h',
                'default' => '',
            ]
        ], Console::DEFAULT_COMMAND)
    ], 'serve');
} catch (Throwable $exception) {
    die($exception->getMessage());
}
