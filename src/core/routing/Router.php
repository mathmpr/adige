<?php

namespace Adige\core\routing;

use Adige\core\BaseEnvironment;
use Adige\core\BaseObject;
use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\file\Directory;
use Adige\helpers\Str;
use Adige\http\http\exceptions\NotImplemented;
use Adige\http\http\Request;
use Adige\http\http\Response;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use ReflectionException;


class Router extends BaseObject
{
    /**
     * @return null|string|Response
     */
    public Request $request;

    public Response $response;

    public static ?Route $foundRoute;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
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
        $route = false;
        if (BaseEnvironment::getEnv('AUTO_DISCOVER_CONTROLLER')) {
            try {
                $route = $this->autoDiscoverController();
            } catch (ControllerClassNotExists $e) {
                $route = $this->findRoute();
            }
        }
        if (!$route) {
            $route = $this->findRoute();
        }
        if ($route) {
            return $route->handle();
        }

        throw new NotImplemented();
    }

    /**
     * @throws ControllerClassNotExists
     */
    private function autoDiscoverController(): ?Route
    {
        $uri = $this->request->getUriParts();
        $possibleDir = '';
        $key = 0;
        $directory = new Directory(ROOT . '/app/controllers/');
        foreach ($uri as $key => $value) {
            $previousDir = $possibleDir;
            $possibleDir .= $value . '/';
            $directory = new Directory(ROOT . '/app/controllers/' . $possibleDir);
            if (!$directory->exists()) {
                $directory = new Directory(ROOT . '/app/controllers/' . $previousDir);
                break;
            }
            unset($uri[$key]);
        }

        if ($key > 0 && isset($uri[$key + 1])) {
            $controller = Str::ucfirst(Str::camel($uri[$key + 1])) . 'Controller';
            $action = isset($uri[$key + 2]) ? Str::camel($uri[$key + 2]) : 'actionIndex';
        } else {
            $controller = array_shift($uri);
            $defaultController = (
            BaseEnvironment::getEnv('DEFAULT_CONTROLLER') ?
                (Str::ucfirst(Str::camel(BaseEnvironment::getEnv('DEFAULT_CONTROLLER'))) . 'Controller') :
                ('IndexController')
            );

            $controller = $controller
                ? (Str::ucfirst(Str::camel($controller)) . 'Controller')
                : $defaultController;

            $forceActionIndex = false;
            if ($controller != $defaultController) {
                $forceActionIndex = true;
            }

            $action = array_shift($uri);
            $action = $action ? ('action' . Str::ucfirst(Str::camel($action))) :
                (
                BaseEnvironment::getEnv('DEFAULT_ACTION') && !$forceActionIndex ?
                    ('action' . Str::ucfirst(Str::camel(BaseEnvironment::getEnv('DEFAULT_ACTION')))) :
                    ('actionIndex')
                );
        }
        $controllerClass = $directory->locationToNamespace(ROOT) . $controller;
        $route = Route::all($this->request->getUri(), $controllerClass, $action, $this->request->get());
        $route->instantiateController();
        return $route;
    }

    /**
     * @return Route|null
     * @throws RequiredParamNotFound
     * @throws ReflectionException
     * @throws ControllerClassNotExists
     */
    private function findRoute(): ?Route
    {
        $uri = $this->request->getUri();
        $method = $this->request->getMethod();
        $routes = Route::$routes;

        $patternSize = [];
        foreach ($routes as $route) {
            $patternSize[$route->pattern] = count(explode('/', $route->pattern));
        }
        arsort($patternSize);
        $patternSize = array_keys($patternSize);
        $rearrangedRoutes = [];
        foreach ($patternSize as $key => $value) {
            foreach ($routes as $route) {
                if ($route->pattern === $value) {
                    $rearrangedRoutes[$key] = $route;
                    break;
                }
            }
        }
        Route::$routes = $rearrangedRoutes;
        $routes = Route::$routes;

        foreach ($routes as &$route) {
            if (mb_strtolower($route->method) !== mb_strtolower($method)) {
                continue;
            }

            $route->pattern = trim($route->pattern, '/');
            $uri = trim($uri, '/');

            $explodedPattern = explode('/', $route->pattern);
            $explodedUri = explode('/', $uri);

            if (count($explodedPattern) !== count($explodedUri)) {
                continue;
            }

            $params = [];
            foreach ($explodedPattern as $key => $value) {
                if (mb_substr($value, 0, 1) === '{' && mb_substr($value, -1) === '}') {
                    $params[trim($value, '{ }')] = $explodedUri[$key];
                } else {
                    if ($value !== $explodedUri[$key]) {
                        continue 2;
                    }
                }
            }

            $route->params = $params;

            $route->instantiateController();

            self::$foundRoute = $route;

            return $route;
        }
        return null;
    }

}