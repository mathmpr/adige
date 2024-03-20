<?php

namespace Adige\cli\exceptions;

use Adige\core\BaseException;
use Throwable;

class ClassNotExistsException extends BaseException
{
    public function __construct(string $class = "", int $code = 0, ?Throwable $previous = null)
    {
        $message = 'Class: ' . $class . ' not exists.';
        parent::__construct($message, $code, $previous);
    }
}