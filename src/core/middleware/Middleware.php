<?php

namespace Adige\core\middleware;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\BaseObject;

abstract class Middleware extends BaseObject
{
    /**
     * Middleware runs before the route handler.
     * Returning a response short-circuits the route execution.
     */
    abstract public function handle(BaseRequest $request, ?BaseResponse $response): ?BaseResponse;

    public function __construct()
    {
        parent::__construct();
    }
}
