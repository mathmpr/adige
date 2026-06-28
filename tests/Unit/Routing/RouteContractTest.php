<?php

namespace Tests\Unit\Routing;

use Adige\core\routing\Route;
use PHPUnit\Framework\TestCase;

class RouteContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Route::$routes = [];
        parent::tearDown();
    }

    public function testRouteFactoriesRegisterExpectedHttpMethods(): void
    {
        $routes = [
            Route::get('/get', static fn() => 'get'),
            Route::post('/post', static fn() => 'post'),
            Route::put('/put', static fn() => 'put'),
            Route::delete('/delete', static fn() => 'delete'),
            Route::patch('/patch', static fn() => 'patch'),
            Route::options('/options', static fn() => 'options'),
            Route::head('/head', static fn() => 'head'),
            Route::all('/all', static fn() => 'all'),
        ];

        self::assertSame(
            ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'ALL'],
            array_map(static fn(Route $route): ?string => $route->getMethod(), $routes)
        );
    }

    public function testGroupedRoutesReceiveCombinedPrefix(): void
    {
        Route::group('api', function () {
            Route::group('v1', function () {
                Route::get('/users', static fn() => 'users');
            });
        });

        self::assertCount(1, Route::$routes);
        self::assertSame('/api/v1/users', Route::$routes[0]->getPattern());
    }

    public function testRouteFactoriesAcceptCallableHandlersWithoutAction(): void
    {
        $route = Route::get('/health', static fn() => 'ok');

        self::assertIsCallable($route->getController());
        self::assertNull($route->getAction());
    }
}
