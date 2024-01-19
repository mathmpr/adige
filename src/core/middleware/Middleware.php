<?php

namespace Adige\core\middleware;

use Adige\core\BaseObject;
use Adige\http\http\Request;
use Adige\http\http\Response;

abstract class Middleware extends BaseObject
{
    /**
     * if return is Response, the request will be stopped
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    abstract public function handle(Request $request, Response $response);

    public function __construct()
    {
        parent::__construct();
    }
}