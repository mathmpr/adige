<?php

namespace Tests\Unit\Routing;

use Adige\core\routing\Route;
use RuntimeException;
use Tests\Support\RouterTestCase;

class RouterAutoDiscoverTest extends RouterTestCase
{
    public function testAutoDiscoverUsesNestedIndexControllerForSingleSegmentUri(): void
    {
        $result = $this->runRouter('/admin');

        self::assertSame(['fixture' => 'admin-index'], $result);
    }

    public function testAutoDiscoverUsesSpecificControllerBeforeHigherLevelActionFallback(): void
    {
        $result = $this->runRouter('/admin/login');

        self::assertSame(['fixture' => 'admin-login-index'], $result);
    }

    public function testAutoDiscoverSupportsDeepNestedIndexController(): void
    {
        $result = $this->runRouter('/alpha/beta');

        self::assertSame(['fixture' => 'alpha-beta-index'], $result);
    }

    public function testAutoDiscoverFallsBackToHigherLevelActionWhenMoreSpecificControllersDoNotExist(): void
    {
        $result = $this->runRouter('/admin/user/login');

        self::assertSame(['fixture' => 'admin-controller-user-login'], $result);
    }

    public function testAutoDiscoverUsesDefaultsForEmptyUri(): void
    {
        $result = $this->runRouter('/', 'GET', [], true, 'admin', 'index');

        self::assertSame(['fixture' => 'admin-controller-index'], $result);
    }

    public function testExplicitAllRouteMatchesAnyMethod(): void
    {
        Route::$routes[] = new Route(
            'ALL',
            '/health',
            static fn() => ['fixture' => 'all-route'],
            null
        );

        $result = $this->runRouter('/health', 'PATCH');

        self::assertSame(['fixture' => 'all-route'], $result);
    }

    public function testExplicitControllerRouteStillMatchesAllMethod(): void
    {
        Route::$routes[] = new Route(
            'ALL',
            '/health/controller',
            'tests\\Fixtures\\web\\controllers\\AdminController',
            'actionLogin'
        );

        $result = $this->runRouter('/health/controller', 'PATCH');

        self::assertSame(['fixture' => 'admin-controller-login'], $result);
    }

    public function testAutoDiscoverRequiresExplicitControllerNamespaces(): void
    {
        $router = $this->createRouter('/admin');
        $router->setControllerNamespaces([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller autodiscovery requires explicit controllerNamespaces configuration.');

        $router->run();
    }

}
