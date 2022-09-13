<?php

namespace Adige\cli\exceptions;

use Exception;
use Throwable;

class MethodNotExistsException extends Exception
{
    public function __construct(string $method = "", $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Method: ' . $method . ' not exists in class: ' . $class;
        parent::__construct($message, $code, $previous);
    }
}