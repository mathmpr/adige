<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\cli\Output;
use peoples\matheus\exercises\FirstProgram;

try{
    Console::addCommands(FirstProgram::class, [
        new Command('init'),
    ], 'matheus');
} catch (Exception $exception) {
    Output::red($exception->getMessage() . "\n", true, true);
}

