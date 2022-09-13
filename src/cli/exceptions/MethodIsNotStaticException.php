<?php

namespace Adige\cli\exceptions;

use Exception;
use Throwable;

class MethodIsNotStaticException extends Exception
{
    public function __construct(string $method = "", $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Method: ' . $method . ' in class: ' . $class . ' is a not static method.';
        parent::__construct($message, $code, $previous);
    }
}