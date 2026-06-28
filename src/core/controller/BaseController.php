<?php

namespace Adige\core\controller;

use Adige\core\Adige;
use Adige\core\BaseObject;
use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\routing\BaseRoute;
use Adige\core\routing\Router;
use RuntimeException;

class BaseController extends BaseObject
{
    protected ?Router $router;

    protected ?BaseRoute $route;

    protected ?BaseRequest $request;

    protected ?BaseResponse $response;

    public function __construct(Router $router)
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

    public function setRouter(?Router $router): BaseController
    {
        $this->router = $router;
        return $this;
    }

    public function getRoute(): ?BaseRoute
    {
        return $this->route;
    }

    public function setRoute(?BaseRoute $route): BaseController
    {
        $this->route = $route;
        return $this;
    }

    public function getRequest(): ?BaseRequest
    {
        return $this->request;
    }

    public function setRequest(?BaseRequest $request): BaseController
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse(): ?BaseResponse
    {
        return $this->response;
    }

    public function setResponse(?BaseResponse $response): BaseController
    {
        $this->response = $response;
        return $this;
    }

    public function respond(string|array|object|null $content, int $statusCode = 200, array $headers = []): BaseResponse
    {
        return Adige::$app->createResponse($content, $statusCode, $headers);
    }

    public function afterAction($action, $result)
    {

    }

    public function beforeAction($action)
    {
        
    }

    public function render(string $view, array $params = []): string
    {
        if (!Adige::$app->view)    {
            throw new RuntimeException("View component is not configured in the application");
        }
        return Adige::$app->view->render($view, $params);
    }

}
