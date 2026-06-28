<?php

namespace Adige\core\middleware\exceptions;

use Adige\core\BaseException;
use Throwable;

class MiddlewareExecutionException extends BaseException
{
    public function __construct(string $middlewareClass, ?Throwable $previous = null)
    {
        parent::__construct(
            'Middleware execution failed: ' . $middlewareClass,
            previous: $previous
        );
    }
}
