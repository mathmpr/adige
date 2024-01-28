<?php

namespace Adige\core\database\exceptions;

use Adige\core\BaseException;

class DefaultConnectionNotDefinedException extends BaseException
{
    public function __construct($message = 'Default connection not defined', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}