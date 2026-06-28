<?php

namespace Tests\Unit\Routing;

use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\routing\Route;
use Tests\Support\RouterTestCase;

class RouteActionParamsTest extends RouterTestCase
{
    public function testRequiredParameterIsInjectedFromRequestInput(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/required',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionRequired'
        );

        $result = $this->runRouter('/params/required', 'GET', ['name' => 'Matheus'], false);

        self::assertSame(['name' => 'Matheus'], $result);
    }

    public function testRequiredParameterUsesScalarCoercion(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/route/{id}',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionRoute'
        );

        $result = $this->runRouter('/params/route/42', 'GET', [], false);

        self::assertSame([
            'id' => 42,
            'page' => 1,
            'enabled' => true,
        ], $result);
    }

    public function testMissingRequiredParameterThrowsException(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/required',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionRequired'
        );

        $router = $this->createRouter('/params/required', 'GET', [], false);

        $this->expectException(RequiredParamNotFound::class);

        $router->run();
    }

    public function testOptionalParameterDefaultsToNullWhenAbsent(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/optional',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionOptional'
        );

        $result = $this->runRouter('/params/optional', 'GET', [], false);

        self::assertSame(['name' => null], $result);
    }

    public function testOptionalParameterUsesProvidedValue(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/optional',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionOptional'
        );

        $result = $this->runRouter('/params/optional', 'GET', ['name' => 'Provided'], false);

        self::assertSame(['name' => 'Provided'], $result);
    }

    public function testDefaultValuesAreUsedWhenInputIsMissing(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/defaults',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionDefaults'
        );

        $result = $this->runRouter('/params/defaults', 'GET', [], false);

        self::assertSame([
            'count' => 5,
            'enabled' => true,
            'name' => 'fallback',
        ], $result);
    }

    public function testFalsyInputOverridesDefaultValues(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/defaults',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionDefaults'
        );

        $result = $this->runRouter('/params/defaults', 'GET', [
            'count' => '0',
            'enabled' => 'false',
            'name' => '',
        ], false);

        self::assertSame([
            'count' => 0,
            'enabled' => false,
            'name' => '',
        ], $result);
    }

    public function testRouteParamsHavePrecedenceOverRequestInput(): void
    {
        Route::$routes[] = new Route(
            'GET',
            '/params/route/{id}',
            'tests\\Fixtures\\web\\controllers\\ParamsController',
            'actionRoute'
        );

        $result = $this->runRouter('/params/route/44', 'GET', [
            'id' => '99',
            'page' => '7',
            'enabled' => 'false',
        ], false);

        self::assertSame([
            'id' => 44,
            'page' => 7,
            'enabled' => false,
        ], $result);
    }
}
