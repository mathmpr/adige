<?php

namespace Adige\core;

use Throwable;
use Exception;

class BaseException extends Exception
{

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}