<?php

namespace Adige\http\http\exceptions;


use Adige\core\Adige;
use Adige\core\BaseException;

class NotImplemented extends BaseException
{
    public function __construct($message = 'Method not implemented', $code = 0, $previous = null)
    {
        Adige::$response->setStatusCode(501);
        parent::__construct($message, $code, $previous);
    }
}