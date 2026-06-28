<?php

namespace Tests\Fixtures\middlewares;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\middleware\Middleware;

class StopMiddleware extends Middleware
{
    public function __construct(private readonly BaseResponse $response)
    {
        parent::__construct();
    }

    public function handle(BaseRequest $request, ?BaseResponse $response): ?BaseResponse
    {
        return $this->response;
    }
}
