<?php

namespace Tests\Support;

use Adige\core\Adige;
use Adige\core\http\http\WebResponse;
use Adige\core\routing\Route;
use Adige\core\routing\Router;
use PHPUnit\Framework\TestCase;

abstract class RouterTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Route::$routes = [];
        Router::$foundRoute = null;
        Adige::$app = null;

        parent::tearDown();
    }

    protected function createRouter(
        string $uri,
        string $method = 'GET',
        array $input = [],
        bool $autoDiscover = true,
        string $defaultController = 'index',
        string $defaultAction = 'index',
        mixed $response = null
    ): Router {
        $request = new TestRequest($uri, $method, $input);
        $router = new Router($request, $response, $autoDiscover, $defaultController, $defaultAction);
        $router->setControllerNamespaces(['tests\\Fixtures\\web\\controllers']);

        $app = new TestApp();
        $app->request = $request;
        $app->response = $response;
        $app->router = $router;

        Adige::$app = $app;

        return $router;
    }

    protected function runRouter(
        string $uri,
        string $method = 'GET',
        array $input = [],
        bool $autoDiscover = true,
        string $defaultController = 'index',
        string $defaultAction = 'index',
        mixed $response = null
    ): mixed {
        try {
            return $this->createRouter(
                $uri,
                $method,
                $input,
                $autoDiscover,
                $defaultController,
                $defaultAction,
                $response
            )->run();
        } catch (\Throwable $throwable) {
            self::fail((string)$throwable);
        }
    }

    protected function createWebResponse(): WebResponse
    {
        return new WebResponse();
    }
}
