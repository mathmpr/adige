<?php

namespace Adige\cli\exceptions;

use Adige\core\BaseException;
use Throwable;

class MethodNotExistsException extends BaseException
{
    public function __construct(string $method = "", $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Method: ' . $method . ' not exists in class: ' . $class;
        parent::__construct($message, $code, $previous);
    }
}