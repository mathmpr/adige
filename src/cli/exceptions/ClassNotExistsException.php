<?php

namespace Adige\cli\exceptions;

use Exception;
use Throwable;

class ClassNotExistsException extends Exception
{
    public function __construct(string $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Class: ' . $class . ' not exists.';
        parent::__construct($message, $code, $previous);
    }
}