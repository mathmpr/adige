<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\http\socket\Server;

try {
    Console::addCommands(Server::class, [
        new Command('start', Console::DEFAULT_COMMAND)
    ], 'serve');
} catch (Throwable $exception) {
    die($exception->getMessage());
}
