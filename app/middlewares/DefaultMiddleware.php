<?php

namespace app\middlewares;

use Adige\core\middleware\Middleware;
use Adige\http\http\Request;
use Adige\http\http\Response;

class DefaultMiddleware extends Middleware
{
    public function handle(Request $request, Response $response)
    {
        $request->setGet(['message' => 'We can manipulate the request here!']);
    }
}