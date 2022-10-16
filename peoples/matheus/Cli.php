<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\cli\Output;
use peoples\matheus\exercises\FirstProgram;
use peoples\matheus\exercises\MathOperationsExamples;

try{

    Console::addCommands(FirstProgram::class, [
        new Command('init'),
    ], 'matheus');

    Console::addCommands(MathOperationsExamples::class, [
        new Command('init'),
    ], 'matheus_math');

} catch (Exception $exception) {
    Output::red($exception->getMessage() . "\n", Output::INSTANT, Output::DIE);
}

