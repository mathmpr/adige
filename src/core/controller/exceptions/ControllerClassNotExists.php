<?php

namespace Adige\core\controller\exceptions;

use Adige\core\BaseException;

class ControllerClassNotExists extends BaseException
{
    public function __construct(string $class, $message = 'Controller class not exists', $code = 0, $previous = null)
    {
        parent::__construct($message . ': ' . $class, $code, $previous);
    }
}