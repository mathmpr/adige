<?php

namespace Adige\core\routing;

use Adige\core\Adige;
use Adige\core\BaseObject;
use Adige\core\BaseResponse;
use Adige\core\controller\BaseController;
use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\middleware\exceptions\MiddlewareClassNotExists;
use Adige\core\middleware\exceptions\MiddlewareExecutionException;
use Adige\core\middleware\Middleware;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

class BaseRoute extends BaseObject
{
    public static array $routes = [];

    public ?string $name;

    public ?string $pattern;

    public $controller;

    public ?string $action;

    public ?string $method = null;

    public array $params = [];

    public ?int $groupedInIndex = null;

    private static int $groupIndex = -1;

    private static array $groupName = [];

    private static array $groupMiddleware = [];

    public function __construct(
        ?string $method,
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

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public static function addRoute(
        ?string $method,
        string $pattern,
        string|callable $controller,
        ?string $action,
        array $params = []
    ): static {
        $route = new static($method, $pattern, $controller, $action, $params);
        if (self::$groupIndex >= 0) {
            $route->groupedInIndex = self::$groupIndex;
            $route->pattern = self::buildFullPrefix() . $route->pattern;
        }
        self::$routes[] = $route;
        return $route;
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
        for ($x = 0; $x <= self::$groupIndex; $x++) {
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
        if ($this->groupedInIndex !== null) {
            for ($x = 0; $x <= $this->groupedInIndex; $x++) {
                $middleware = self::$groupMiddleware[$x] ?? null;
                if ($middleware) {
                    try {
                        $return = $middleware->handle(Adige::$app->request, Adige::$app?->response);
                    } catch (Throwable $throwable) {
                        throw new MiddlewareExecutionException($middleware::class, $throwable);
                    }

                    if ($return instanceof BaseResponse) {
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
    protected function buildArgs(ReflectionMethod|ReflectionFunction $method): array
    {
        $request = Adige::$app->request;
        $parameters = [];
        $input = $request->input();
        $routeParams = $this->getParams();
        foreach ($method->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $hasRouteValue = is_array($routeParams) && array_key_exists($parameterName, $routeParams);
            $hasInputValue = array_key_exists($parameterName, $input);
            $hasValue = $hasRouteValue || $hasInputValue;
            $value = $hasRouteValue
                ? $routeParams[$parameterName]
                : $request->input($parameterName);

            if ($parameter->isDefaultValueAvailable()) {
                $parameters[$parameterName] = $hasValue
                    ? determine_var($value)
                    : $parameter->getDefaultValue() ?? null;
            } elseif ($parameter->isOptional()) {
                $parameters[$parameterName] = $hasValue
                    ? determine_var($value)
                    : null;
            } elseif ($hasValue) {
                $parameters[$parameterName] = determine_var($value);
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

    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getController(): callable|BaseController|string|null
    {
        return $this->controller;
    }

    public function setController(callable|BaseController|string|null $controller): static
    {
        $this->controller = $controller;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getGroupedInIndex(): ?int
    {
        return $this->groupedInIndex;
    }

    public function setGroupedInIndex(?int $groupedInIndex): static
    {
        $this->groupedInIndex = $groupedInIndex;
        return $this;
    }

    /**
     * @return mixed
     * @throws ControllerClassNotExists
     */
    public function instantiateController(): mixed
    {
        if (is_string($this->controller) && !is_object($this->controller)) {
            if (!class_exists($this->controller)) {
                throw new ControllerClassNotExists($this->controller);
            }
            $this->controller = new $this->controller(Adige::$app->router);
            $this->controller->setRoute($this);
        }
        return $this->controller;
    }
}
