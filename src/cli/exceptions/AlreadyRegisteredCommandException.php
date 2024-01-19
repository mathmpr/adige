<?php

namespace Adige\cli\exceptions;

use Adige\core\BaseException;
use Throwable;

class AlreadyRegisteredCommandException extends BaseException
{
    public function __construct(string $command = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Can\'t register command: ' . $command . ' because it already registred by another Cli.php';
        parent::__construct($message, $code, $previous);
    }
}