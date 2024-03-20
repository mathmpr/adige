<?php

namespace Adige\core\controller\exceptions;

use Adige\core\BaseException;
use Adige\core\controller\Controller;

class RequiredParamNotFound extends BaseException
{
    private ?Controller $controller;

    public function __construct(?Controller $controller, $message = 'Required param not found', $code = 0, $previous = null)
    {
        $this->controller = $controller;
        $message .= ' for > ' . (is_object($controller) ? get_class($controller) : 'anonymous function');
        parent::__construct($message, $code, $previous);
    }

    public function getController(): ?Controller
    {
        return $this->controller;
    }

}