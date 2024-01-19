<?php

namespace Adige\core\controller;

use Adige\core\BaseObject;
use Adige\core\routing\Route;
use Adige\core\routing\Router;
use Adige\http\http\Request;
use Adige\http\http\Response;

class Controller extends BaseObject
{
    protected ?Router $router;

    protected ?Route $route;

    protected ?Request $request;

    protected ?Response $response;

    public function __construct(Router $router,)
    {
        $this->router = $router;
        $this->request = $router->request;
        $this->response = $router->response;
        parent::__construct();
    }

    public function getRouter(): ?Router
    {
        return $this->router;
    }

    public function setRouter(?Router $router): Controller
    {
        $this->router = $router;
        return $this;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): Controller
    {
        $this->route = $route;
        return $this;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): Controller
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): Controller
    {
        $this->response = $response;
        return $this;
    }

    public function respond(string|array|object $content, int $statusCode = 200, array $headers = []): Response
    {
        return respond($content, $statusCode, $headers);
    }

}