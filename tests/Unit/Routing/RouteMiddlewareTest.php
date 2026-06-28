<?php

namespace Tests\Unit\Routing;

use Adige\core\Adige;
use Adige\core\http\http\WebResponse;
use Adige\core\middleware\exceptions\MiddlewareExecutionException;
use Adige\core\routing\Route;
use Tests\Fixtures\middlewares\AppendLogMiddleware;
use Tests\Fixtures\middlewares\StopMiddleware;
use Tests\Fixtures\middlewares\ThrowingMiddleware;
use Tests\Support\RouterTestCase;

class RouteMiddlewareTest extends RouterTestCase
{
    public function testNestedGroupMiddlewareExecutesInOuterThenInnerOrder(): void
    {
        Route::group('outer', function () {
            Route::group('inner', function () {
                Route::get('/inspect', static fn() => Adige::$app->request->input('log'), null);
            }, new AppendLogMiddleware('inner'));
        }, new AppendLogMiddleware('outer'));

        $result = $this->runRouter('/outer/inner/inspect', 'GET', [], false);

        self::assertSame(['outer', 'inner'], $result);
    }

    public function testMiddlewareCanShortCircuitRouteExecution(): void
    {
        $blockedResponse = (new WebResponse())
            ->setStatusCode(403)
            ->setBody('blocked');

        Route::group('secure', function () {
            Route::get('/area', static fn() => ['fixture' => 'should-not-run'], null);
        }, new StopMiddleware($blockedResponse));

        $result = $this->runRouter('/secure/area', 'GET', [], false);

        self::assertSame($blockedResponse, $result);
        self::assertSame(403, $result->getStatusCode());
        self::assertSame('blocked', $result->getBody());
    }

    public function testMiddlewareCanMutateRequestBeforeActionExecution(): void
    {
        Route::group('decorate', function () {
            Route::get('/input', static fn() => Adige::$app->request->input('message'), null);
        }, new AppendLogMiddleware('decorated'));

        $result = $this->runRouter('/decorate/input', 'GET', ['message' => 'original'], false);

        self::assertSame('original', $result);
        self::assertSame(['decorated'], Adige::$app->request->input('log'));
    }

    public function testMiddlewareFailuresAreWrappedInSemanticException(): void
    {
        Route::group('broken', function () {
            Route::get('/flow', static fn() => 'should-not-run', null);
        }, new ThrowingMiddleware());

        $this->expectException(MiddlewareExecutionException::class);
        $this->expectExceptionMessage('Tests\\Fixtures\\middlewares\\ThrowingMiddleware');

        $this->createRouter('/broken/flow', 'GET', [], false)->run();
    }
}
