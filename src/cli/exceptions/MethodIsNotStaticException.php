<?php

namespace Adige\cli\exceptions;

use Adige\core\BaseException;
use Throwable;

class MethodIsNotStaticException extends BaseException
{
    public function __construct(string $method = "", $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Method: ' . $method . ' in class: ' . $class . ' is a not static method.';
        parent::__construct($message, $code, $previous);
    }
}