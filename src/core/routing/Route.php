<?php

namespace Adige\core\routing;

use Adige\core\Adige;
use Adige\core\BaseObject;
use Adige\core\controller\Controller;
use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\middleware\exceptions\MiddlewareClassNotExists;
use Adige\core\middleware\Middleware;
use Adige\http\http\Response;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionFunction;

class Route extends BaseObject
{
    public static array $routes = [];

    public ?string $name;

    public ?string $pattern;

    public $controller;

    public ?string $action;

    public ?string $method = 'GET';

    public array $params = [];

    public ?int $groupedInIndex = null;

    private static int $groupIndex = -1;

    private static array $groupName = [];

    private static array $groupMiddleware = [];

    public function __construct(
        string $method,
        ?string $pattern,
        string|callable|null $controller,
        ?string $action,
        array $params = []
    ) {
        $this->method = $method;
        $this->pattern = $pattern;
        $this->controller = $controller;
        $this->action = $action;
        $this->params = $params;
        parent::__construct();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Route
    {
        $this->name = $name;
        return $this;
    }

    public static function addRoute(
        string $method,
        string $pattern,
        string $controller,
        string $action,
        $params = []
    ): Route {
        $route = new Route($method, $pattern, $controller, $action, $params);
        if (self::$groupIndex >= 0) {
            $route->groupedInIndex = self::$groupIndex;
            $route->pattern = self::buildFullPrefix() . $route->pattern;
        }
        self::$routes[] = $route;
        return $route;
    }

    public static function get($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('GET', $pattern, $controller, $action, $params);
    }

    public static function post($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('POST', $pattern, $controller, $action, $params);
    }

    public static function put($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('PUT', $pattern, $controller, $action, $params);
    }

    public static function delete($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('DELETE', $pattern, $controller, $action, $params);
    }

    public static function patch($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('PATCH', $pattern, $controller, $action, $params);
    }

    public static function options($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('OPTIONS', $pattern, $controller, $action, $params);
    }

    public static function head($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('HEAD', $pattern, $controller, $action, $params);
    }

    public static function all($pattern, $controller, $action, $params = []): Route
    {
        return self::addRoute('ALL', $pattern, $controller, $action, $params);
    }

    /**
     * @param string|null $prefix
     * @param callable $grouper
     * @param Middleware|string|null $middleware
     * @return void
     * @throws MiddlewareClassNotExists
     */
    public static function group(?string $prefix, callable $grouper, Middleware|string|null $middleware = null): void
    {
        self::$groupIndex++;
        self::$groupName[self::$groupIndex] = $prefix;
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new MiddlewareClassNotExists($middleware);
            }
            $middleware = new $middleware();
        }
        self::$groupMiddleware[self::$groupIndex] = $middleware;
        $grouper();
        self::$groupIndex--;
    }

    public static function buildFullPrefix(): string
    {
        $prefix = '';
        for ($x = 0; $x <= self::$groupIndex + 1; $x++) {
            if (isset(self::$groupName[$x])) {
                $prefix .= '/' . self::$groupName[$x];
            }
        }
        return str_replace('//', '/', $prefix);
    }

    /**
     * @return string|array|object|null
     * @throws ReflectionException
     * @throws RequiredParamNotFound
     */
    public function handle(): string|array|object|null
    {
        if ($this->groupedInIndex) {
            for ($x = 0; $x <= $this->groupedInIndex; $x++) {
                $middleware = self::$groupMiddleware[$x] ?? null;
                if ($middleware) {
                    $return = $middleware->handle(Adige::$request, Adige::$response);
                    if ($return instanceof Response) {
                        return $return;
                    }
                }
            }
        }
        if (is_callable($this->controller)) {
            $method = new ReflectionFunction($this->controller);
            $parameters = $this->buildArgs($method);
            return call_user_func_array($this->controller, $parameters);
        }
        if (is_object($this->controller)) {
            $controller = new ReflectionClass($this->controller);
            $method = $controller->getMethod($this->action);
            $parameters = $this->buildArgs($method);
            $beforeAction = $controller->getMethod('beforeAction');
            $returnBefore = $beforeAction->invokeArgs($this->controller, [$this->action]);
            if (!is_null($returnBefore)) {
                return $returnBefore;
            }
            $returnValue = $method->invokeArgs($this->controller, $parameters);
            $afterAction = $controller->getMethod('afterAction');
            $returnAfter = $afterAction->invokeArgs($this->controller, [$this->action, $returnValue]);
            if (!is_null($returnAfter)) {
                return $returnAfter;
            }
            return $returnValue;
        }
        return null;
    }

    /**
     * @param ReflectionMethod|ReflectionFunction $method
     * @return array
     * @throws RequiredParamNotFound
     */
    private function buildArgs(ReflectionMethod|ReflectionFunction $method): array
    {
        $request = Adige::$request;
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $get = $request->get($parameter->getName());
            if ($parameter->isDefaultValueAvailable()) {
                $parameters[$parameter->getName()] = $get
                    ? determine_var($get)
                    : $parameter->getDefaultValue() ?? null;
            } elseif ($parameter->isOptional()) {
                $parameters[$parameter->getName()] = $get
                    ? determine_var($get)
                    : null;
            } elseif (isset($request->get()[$parameter->getName()])) {
                $parameters[$parameter->getName()] = $get;
            } else {
                throw new RequiredParamNotFound($this->controller);
            }
        }
        return $parameters;
    }

    public function getParams($name = null): array|string|null
    {
        if ($name) {
            return $this->params[$name] ?? null;
        }
        return $this->params;
    }

    public function setParams(array $params): Route
    {
        $this->params = $params;
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): Route
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getController(): callable|Controller|string|null
    {
        return $this->controller;
    }

    public function setController(callable|Controller|string|null $controller): Route
    {
        $this->controller = $controller;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): Route
    {
        $this->action = $action;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): Route
    {
        $this->method = $method;
        return $this;
    }

    public function getGroupedInIndex(): ?int
    {
        return $this->groupedInIndex;
    }

    public function setGroupedInIndex(?int $groupedInIndex): Route
    {
        $this->groupedInIndex = $groupedInIndex;
        return $this;
    }

    /**
     * @return mixed
     * @throws ControllerClassNotExists
     */
    public function instantiateController(): Controller
    {
        if (is_string($this->controller) && !is_object($this->controller)) {
            if (!class_exists($this->controller)) {
                throw new ControllerClassNotExists($this->controller);
            }
            $this->controller = new $this->controller(Adige::$router);
            $this->controller->setRoute($this);
        }
        return $this->controller;
    }

}