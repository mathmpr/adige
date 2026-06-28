<?php

namespace Tests\Fixtures\middlewares;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\http\http\WebResponse;
use Adige\core\middleware\Middleware;
use RuntimeException;

class ThrowingMiddleware extends Middleware
{
    public function handle(BaseRequest $request, ?BaseResponse $response): ?WebResponse
    {
        throw new RuntimeException('middleware boom');
    }
}
