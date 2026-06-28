<?php

namespace Adige\core\controller\exceptions;

use Adige\core\BaseException;
use Adige\core\controller\BaseController;

class RequiredParamNotFound extends BaseException
{
    private ?BaseController $controller;

    public function __construct(?BaseController $controller, $message = 'Required param not found', $code = 0, $previous = null)
    {
        $this->controller = $controller;
        $message .= ' for > ' . (is_object($controller) ? get_class($controller) : 'anonymous function');
        parent::__construct($message, $code, $previous);
    }

    public function getController(): ?BaseController
    {
        return $this->controller;
    }

}