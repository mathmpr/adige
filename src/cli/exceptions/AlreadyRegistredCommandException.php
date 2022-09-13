<?php

namespace Adige\cli\exceptions;

use Exception;
use Throwable;

class AlreadyRegistredCommandException extends Exception
{
    public function __construct(string $command = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Can\'t register command: ' . $command . ' because it already registred by another Cli.php';
        parent::__construct($message, $code, $previous);
    }
}