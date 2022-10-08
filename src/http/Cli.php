<?php

use Adige\cli\Console;
use Adige\cli\Command;
use Adige\http\socket\Server;
use Adige\cli\Exceptions\AlreadyRegistredCommandException;
use Adige\cli\Exceptions\ClassNotExistsException;
use Adige\cli\Exceptions\MethodNotExistsException;
use Adige\cli\Exceptions\MethodIsNotStaticException;

try {
    Console::addCommands(Server::class, [
        new Command('start', [], Console::DEFAULT_COMMAND),
    ], 'serve');
} catch (AlreadyRegistredCommandException $alreadyRegistredCommandException) {
    die($alreadyRegistredCommandException->getMessage());
} catch (ClassNotExistsException $classNotExistsException) {
    die($classNotExistsException->getMessage());
} catch (MethodNotExistsException $methodNotExistsException) {
    die($methodNotExistsException->getMessage());
} catch (MethodIsNotStaticException $methodIsNotStaticException) {
    die($methodIsNotStaticException->getMessage());
}

