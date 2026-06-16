<?php

namespace Tests\Unit\Routing;

use Adige\console\ConsoleResponse;
use Adige\core\http\http\exceptions\MethodNotAllowed;
use Adige\core\http\http\exceptions\RouteNotFound;
use Adige\core\routing\Route;
use Adige\core\routing\Router;
use Tests\Support\RouterTestCase;

class RouterFindRouteTest extends RouterTestCase
{
    public function testExplicitStaticRouteMatchesClosureHandler(): void
    {
        Route::$routes[] = new Route('GET', '/health', static fn() => ['fixture' => 'health-check'], null);

        $result = $this->runRouter('/health', 'GET', [], false);

        self::assertSame(['fixture' => 'health-check'], $result);
    }

    public function testExplicitDynamicRouteExtractsParamsIntoFoundRoute(): void
    {
        Route::$routes[] = new Route('GET', '/users/{id}', static fn() => ['fixture' => 'user-show'], null);

        $result = $this->runRouter('/users/42', 'GET', [], false);

        self::assertSame(['fixture' => 'user-show'], $result);
        self::assertSame('42', Router::$foundRoute?->getParams('id'));
        self::assertSame(['id' => '42'], Router::$foundRoute?->getParams());
    }

    public function testExplicitRouteWinsOverAutoDiscoverWhenBothMatch(): void
    {
        Route::$routes[] = new Route('GET', '/admin', static fn() => ['fixture' => 'explicit-admin'], null);

        $result = $this->runRouter('/admin');

        self::assertSame(['fixture' => 'explicit-admin'], $result);
    }

    public function testStaticRouteWinsOverDynamicRouteWithSameUriShape(): void
    {
        Route::$routes[] = new Route('GET', '/users/{id}', static fn() => ['fixture' => 'dynamic-user-show'], null);
        Route::$routes[] = new Route('GET', '/users/list', static fn() => ['fixture' => 'static-user-list'], null);

        $result = $this->runRouter('/users/list', 'GET', [], false);

        self::assertSame(['fixture' => 'static-user-list'], $result);
    }

    public function testNotFoundThrowsRouteNotFoundAndSetsStatusCode(): void
    {
        $response = $this->createWebResponse();
        $router = $this->createRouter('/missing', 'GET', [], false, 'index', 'index', $response);

        $this->expectException(RouteNotFound::class);

        try {
            $router->run();
        } finally {
            self::assertSame(404, $response->getStatusCode());
        }
    }

    public function testAutoDiscoverNotFoundThrowsRouteNotFoundAndSetsStatusCode(): void
    {
        $response = $this->createWebResponse();
        $router = $this->createRouter('/missing/path', 'GET', [], true, 'index', 'index', $response);

        $this->expectException(RouteNotFound::class);

        try {
            $router->run();
        } finally {
            self::assertSame(404, $response->getStatusCode());
        }
    }

    public function testMethodMismatchThrowsMethodNotAllowedAndSetsStatusCode(): void
    {
        Route::$routes[] = new Route('GET', '/health', static fn() => ['fixture' => 'health-check'], null);
        $response = $this->createWebResponse();
        $router = $this->createRouter('/health', 'POST', [], false, 'index', 'index', $response);

        $this->expectException(MethodNotAllowed::class);

        try {
            $router->run();
        } finally {
            self::assertSame(405, $response->getStatusCode());
            self::assertSame('GET', $response->getHeaders()?->getHeader('Allow'));
        }
    }

    public function testMethodMismatchCollectsAllAllowedMethods(): void
    {
        Route::$routes[] = new Route('GET', '/health', static fn() => ['fixture' => 'health-get'], null);
        Route::$routes[] = new Route('PATCH', '/health', static fn() => ['fixture' => 'health-patch'], null);
        $response = $this->createWebResponse();
        $router = $this->createRouter('/health', 'POST', [], false, 'index', 'index', $response);

        try {
            $router->run();
            self::fail('Expected MethodNotAllowed to be thrown');
        } catch (MethodNotAllowed $exception) {
            self::assertSame(['GET', 'PATCH'], $exception->getAllowedMethods());
            self::assertSame(405, $response->getStatusCode());
            self::assertSame('GET, PATCH', $response->getHeaders()?->getHeader('Allow'));
        }
    }

    public function testMethodMismatchDoesNotApplyHttpHeadersToConsoleResponse(): void
    {
        Route::$routes[] = new Route('GET', '/health', static fn() => ['fixture' => 'health-check'], null);
        $response = new ConsoleResponse();
        $router = $this->createRouter('/health', 'POST', [], false, 'index', 'index', $response);

        try {
            $router->run();
            self::fail('Expected MethodNotAllowed to be thrown');
        } catch (MethodNotAllowed $exception) {
            self::assertSame(['GET'], $exception->getAllowedMethods());
            self::assertSame(0, $response->getExitCode());
            self::assertSame('', $response->getStdout());
            self::assertSame('', $response->getStderr());
        }
    }
}
