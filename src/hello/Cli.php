<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\hello\Hello;
use Adige\cli\Exceptions\AlreadyRegistredCommandException;
use Adige\cli\Exceptions\ClassNotExistsException;
use Adige\cli\Exceptions\MethodNotExistsException;
use Adige\cli\Exceptions\MethodIsNotStaticException;

try {
    Console::addCommands(Hello::class, [
        new Command('hello', [], Console::DEFAULT_COMMAND)
    ]);
} catch (AlreadyRegistredCommandException $alreadyRegistredCommandException) {
    die($alreadyRegistredCommandException->getMessage());
} catch (ClassNotExistsException $classNotExistsException) {
    die($classNotExistsException->getMessage());
} catch (MethodNotExistsException $methodNotExistsException) {
    die($methodNotExistsException->getMessage());
} catch (MethodIsNotStaticException $methodIsNotStaticException) {
    die($methodIsNotStaticException->getMessage());
}

