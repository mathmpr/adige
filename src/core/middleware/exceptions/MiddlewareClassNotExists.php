<?php

namespace Adige\core\middleware\exceptions;

use Adige\core\BaseException;

class MiddlewareClassNotExists extends BaseException
{
    public function __construct(string $class, $message = 'Middleware class not exists', $code = 0, $previous = null)
    {
        parent::__construct($message . ': ' . $class, $code, $previous);
    }
}