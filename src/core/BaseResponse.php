<?php

namespace Adige\core;

use Adige\core\events\Observable;

abstract class BaseResponse extends BaseObject
{
    use Observable;

    public const EVENT_BEFORE_DISPATCH = 'beforeDispatch';
    public const EVENT_AFTER_DISPATCH = 'afterDispatch';

    abstract public function dispatch(): void;
}
