<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\hello\Hello;

try {
    Console::addCommands(Hello::class, [
        new Command('hello', Console::DEFAULT_COMMAND)
    ]);
} catch (Throwable $exception) {
    die($exception->getMessage());
}
