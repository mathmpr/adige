<?php

namespace Adige\core\routing;

use Adige\core\BaseEnvironment;
use Adige\core\BaseObject;
use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\http\http\WebResponse;
use Adige\core\http\http\exceptions\MethodNotAllowed;
use Adige\core\http\http\exceptions\NotImplemented;
use Adige\core\http\http\exceptions\RouteNotFound;
use Adige\helpers\Str;
use ReflectionException;
use RuntimeException;


class Router extends BaseObject
{
    public BaseRequest $request;

    public null|BaseResponse $response;

    public static ?Route $foundRoute;

    private ?bool $isAutoDiscoverController = null;

    private ?string $defaultController = null;

    private ?string $defaultAction = null;

    protected array $controllerNamespaces = [];

    public function __construct(
        BaseRequest $request,
        null|BaseResponse $response,
        bool $autoDiscoverController = false,
        string $defaultController = 'index',
        string $defaultAction = 'index'
    )
    {
        $this->request = $request;
        $this->response = $response;

        $this->defaultController = env('DEFAULT_CONTROLLER', $defaultController);
        $this->defaultAction = env('DEFAULT_ACTION', $defaultAction);
        $this->isAutoDiscoverController = env('AUTO_DISCOVER_CONTROLLER', $autoDiscoverController);

        parent::__construct();
    }

    /**
     * @return string|array|object|null
     * @throws RequiredParamNotFound
     * @throws ReflectionException
     * @throws NotImplemented
     * @throws ControllerClassNotExists
     */
    public function run(): object|array|string|null
    {
        $route = $this->findRoute();

        if (!$route && $this->isAutoDiscoverController) {
            $route = $this->autoDiscoverController();
        }

        if ($route) {
            return $route->handle();
        }

        $this->throwRouteNotFound();
    }

    /**
     * @throws RouteNotFound
     */
    private function autoDiscoverController(): ?Route
    {
        $this->assertAutoDiscoverNamespacesConfigured();

        $uri = $this->request->getUriParts();
        if (empty($uri)) {
            return $this->buildDefaultRoute($uri);
        }

        return $this->resolveAutoDiscoveredRoute($uri);
    }

    /**
     * @return Route|null
     * @throws RequiredParamNotFound
     * @throws ReflectionException
     * @throws ControllerClassNotExists
     * @throws MethodNotAllowed
     */
    private function findRoute(): ?Route
    {
        $uri = $this->request->getUri();
        $method = $this->request->getMethod();
        $routes = $this->sortRoutesBySpecificity(Route::$routes);
        $allowedMethods = [];

        foreach ($routes as $route) {
            $normalizedPattern = trim((string) $route->pattern, '/');
            $normalizedUri = trim($uri, '/');

            if (!$this->matchesRoutePattern($normalizedPattern, $normalizedUri)) {
                continue;
            }

            $route->params = $this->extractRouteParams($normalizedPattern, $normalizedUri);

            if (!$this->matchesRouteMethod($route, $method)) {
                if ($route->method !== null) {
                    $allowedMethods[] = $route->method;
                }
                continue;
            }

            $route->instantiateController();

            self::$foundRoute = $route;

            return $route;
        }

        if (!empty($allowedMethods)) {
            $this->throwMethodNotAllowed($allowedMethods);
        }

        return null;
    }

    /**
     * @param array<int, Route> $routes
     * @return array<int, Route>
     */
    protected function sortRoutesBySpecificity(array $routes): array
    {
        $indexedRoutes = [];
        foreach ($routes as $index => $route) {
            $indexedRoutes[] = [
                'index' => $index,
                'route' => $route,
            ];
        }

        usort($indexedRoutes, function (array $left, array $right): int {
            $leftScore = $this->calculateRouteSpecificity($left['route']);
            $rightScore = $this->calculateRouteSpecificity($right['route']);

            foreach ([0, 1, 2] as $position) {
                $comparison = $rightScore[$position] <=> $leftScore[$position];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $left['index'] <=> $right['index'];
        });

        return array_map(
            static fn(array $indexedRoute): Route => $indexedRoute['route'],
            $indexedRoutes
        );
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    protected function calculateRouteSpecificity(Route $route): array
    {
        $segments = $this->explodeRouteSegments((string) $route->pattern);
        $staticSegments = 0;
        $dynamicSegments = 0;

        foreach ($segments as $segment) {
            if ($this->isDynamicSegment($segment)) {
                $dynamicSegments++;
                continue;
            }

            $staticSegments++;
        }

        return [count($segments), $staticSegments, -$dynamicSegments];
    }

    protected function matchesRouteMethod(Route $route, ?string $method): bool
    {
        return mb_strtolower((string) $route->method) === 'all'
            || mb_strtolower((string) $route->method) === mb_strtolower((string) $method);
    }

    protected function matchesRoutePattern(string $pattern, string $uri): bool
    {
        return count($this->explodeRouteSegments($pattern)) === count($this->explodeRouteSegments($uri))
            && $this->extractRouteParams($pattern, $uri) !== null;
    }

    protected function extractRouteParams(string $pattern, string $uri): ?array
    {
        $explodedPattern = $this->explodeRouteSegments($pattern);
        $explodedUri = $this->explodeRouteSegments($uri);

        if (count($explodedPattern) !== count($explodedUri)) {
            return null;
        }

        $params = [];
        foreach ($explodedPattern as $key => $value) {
            if ($this->isDynamicSegment($value)) {
                $params[trim($value, '{ }')] = $explodedUri[$key];
                continue;
            }

            if ($value !== $explodedUri[$key]) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @return array<int, string>
     */
    protected function explodeRouteSegments(string $value): array
    {
        $trimmed = trim($value, '/');
        if ($trimmed === '') {
            return [''];
        }

        return explode('/', $trimmed);
    }

    protected function isDynamicSegment(string $segment): bool
    {
        return mb_substr($segment, 0, 1) === '{' && mb_substr($segment, -1) === '}';
    }

    protected function resolveControllerClass(array $directoryParts, string $controller): string
    {
        $directorySuffix = implode('\\', $directoryParts);

        foreach ($this->getAutoDiscoverControllerNamespaces() as $controllerNamespace) {
            $controllerClass = trim($controllerNamespace, '\\')
                . (!empty($directorySuffix) ? '\\' . $directorySuffix : '')
                . '\\' . $controller;
            if (class_exists($controllerClass)) {
                return $controllerClass;
            }
        }

        throw new ControllerClassNotExists($controller);
    }

    /**
     * @throws RouteNotFound
     */
    protected function createAutoDiscoveredRoute(string $controllerClass, string $action): Route
    {
        $route = Route::all($this->request->getUri(), $controllerClass, $action, $this->request->input());
        $route->instantiateController();
        return $route;
    }

    /**
     * @param array<int, string> $uriParts
     * @return array<int, array{directory: array<int, string>, controller: string, action: string}>
     */
    protected function buildAutoDiscoverCandidates(array $uriParts): array
    {
        $candidates = [[
            'directory' => $uriParts,
            'controller' => 'IndexController',
            'action' => 'actionIndex',
        ]];

        for ($controllerIndex = count($uriParts) - 1; $controllerIndex >= 0; $controllerIndex--) {
            $remainingActionParts = array_slice($uriParts, $controllerIndex + 1);

            $candidates[] = [
                'directory' => array_slice($uriParts, 0, $controllerIndex),
                'controller' => Str::ucfirst(Str::camel($uriParts[$controllerIndex])) . 'Controller',
                'action' => empty($remainingActionParts)
                    ? 'actionIndex'
                    : 'action' . Str::ucfirst(Str::camel(implode('-', $remainingActionParts))),
            ];
        }

        return array_values(array_unique($candidates, SORT_REGULAR));
    }

    /**
     * @param array<int, string> $uriParts
     * @throws RouteNotFound
     */
    protected function resolveAutoDiscoveredRoute(array $uriParts): Route
    {
        foreach ($this->buildAutoDiscoverCandidates($uriParts) as $candidate) {
            try {
                $controllerClass = $this->resolveControllerClass(
                    $candidate['directory'],
                    $candidate['controller']
                );
            } catch (ControllerClassNotExists) {
                continue;
            }

            if (!$this->controllerHasAction($controllerClass, $candidate['action'])) {
                continue;
            }

            return $this->createAutoDiscoveredRoute($controllerClass, $candidate['action']);
        }

        $this->throwRouteNotFound();
    }

    protected function controllerHasAction(string $controllerClass, string $action): bool
    {
        return method_exists($controllerClass, $action);
    }

    protected function getAutoDiscoverControllerNamespaces(): array
    {
        return array_values(array_unique(array_filter($this->controllerNamespaces)));
    }

    protected function assertAutoDiscoverNamespacesConfigured(): void
    {
        if ($this->getAutoDiscoverControllerNamespaces() !== []) {
            return;
        }

        throw new RuntimeException(
            'Controller autodiscovery requires explicit controllerNamespaces configuration.'
        );
    }

    /**
     * @throws RouteNotFound
     */
    protected function buildDefaultRoute(array $uriParts): Route
    {
        $defaultController = $this->defaultController
            ? (Str::ucfirst(Str::camel($this->defaultController)) . 'Controller')
            : 'IndexController';

        $actionSegment = $uriParts[0] ?? null;
        $action = $actionSegment
            ? ('action' . Str::ucfirst(Str::camel($actionSegment)))
            : ($this->defaultAction ? ('action' . Str::ucfirst(Str::camel($this->defaultAction))) : 'actionIndex');

        if (BaseEnvironment::isConsoleApp() && $defaultController === 'IndexController') {
            $controllerClass = 'Adige\\console\\controllers\\IndexController';
        } else {
            try {
                $controllerClass = $this->resolveControllerClass([], $defaultController);
            } catch (ControllerClassNotExists) {
                $this->throwRouteNotFound();
            }
        }

        $route = Route::all($this->request->getUri(), $controllerClass, $action, $this->request->input());

        $route->instantiateController();
        return $route;
    }

    protected function throwRouteNotFound(): never
    {
        $this->prepareHttpErrorResponse(404);
        throw new RouteNotFound('Route not found: ' . $this->request->getUri());
    }

    protected function throwMethodNotAllowed(array $allowedMethods): never
    {
        $allowedMethods = array_values(array_unique($allowedMethods));
        $this->prepareHttpErrorResponse(405, $allowedMethods);
        throw new MethodNotAllowed($allowedMethods);
    }

    protected function prepareHttpErrorResponse(int $statusCode, array $allowedMethods = []): void
    {
        if (!$this->response instanceof WebResponse) {
            return;
        }

        $this->response->setStatusCode($statusCode);

        if ($statusCode === 405 && !empty($allowedMethods)) {
            $this->response->getHeaders()?->setHeader('Allow', implode(', ', $allowedMethods));
        }
    }

    public function getControllerNamespaces(): array
    {
        return $this->controllerNamespaces;
    }

    public function setControllerNamespaces(array $controllerNamespaces): static
    {
        $this->controllerNamespaces = array_values(array_filter(array_map(
            static fn(string $namespace) => trim($namespace, '\\'),
            $controllerNamespaces
        )));
        return $this;
    }

}
