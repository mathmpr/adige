<?php

namespace app\web\middlewares;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\middleware\Middleware;

class DefaultMiddleware extends Middleware
{
    public function handle(BaseRequest $request, ?BaseResponse $response): ?BaseResponse
    {
        // Default middleware intentionally does not mutate the request.
        return null;
    }
}
