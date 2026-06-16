<?php

namespace Adige\core;

abstract class BaseResponse extends BaseObject
{
    abstract public function dispatch(): void;
}
