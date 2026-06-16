<?php

namespace Adige\core\routing;

class Route extends BaseRoute
{
    public ?string $method = 'GET';

    public static function get($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('GET', $pattern, $controller, $action, $params);
    }

    public static function post($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('POST', $pattern, $controller, $action, $params);
    }

    public static function put($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('PUT', $pattern, $controller, $action, $params);
    }

    public static function delete($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('DELETE', $pattern, $controller, $action, $params);
    }

    public static function patch($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('PATCH', $pattern, $controller, $action, $params);
    }

    public static function options($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('OPTIONS', $pattern, $controller, $action, $params);
    }

    public static function head($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('HEAD', $pattern, $controller, $action, $params);
    }

    public static function all($pattern, $controller, $action = null, $params = []): static
    {
        return static::addRoute('ALL', $pattern, $controller, $action, $params);
    }
}
