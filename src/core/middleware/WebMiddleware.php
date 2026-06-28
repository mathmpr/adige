<?php

namespace Adige\core\middleware;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\BaseObject;
use Adige\core\http\http\WebRequest;
use Adige\core\http\http\WebResponse;

abstract class WebMiddleware extends Middleware
{
    /**
     * Middleware runs before the route handler.
     * Returning a response short-circuits the route execution.
     */
    abstract public function handle(WebRequest|BaseRequest $request, WebResponse|BaseResponse|null $response): ?WebResponse;

    public function __construct()
    {
        parent::__construct();
    }
}
