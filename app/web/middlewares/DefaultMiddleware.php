<?php

namespace app\web\middlewares;

use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\http\http\WebRequest;
use Adige\core\http\http\WebResponse;
use Adige\core\middleware\WebMiddleware;

class DefaultMiddleware extends WebMiddleware
{
    public function handle(WebRequest|BaseRequest $request, WebResponse|BaseResponse|null $response): ?WebResponse
    {
        $request->setGet(['message' => 'ok']);
        return null;
    }
}
