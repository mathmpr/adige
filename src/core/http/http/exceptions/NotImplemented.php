<?php

namespace Adige\core\http\http\exceptions;

use Adige\core\BaseException;

class NotImplemented extends BaseException
{
    public function __construct($message = 'Method not implemented', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
