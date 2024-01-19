<?php

namespace Adige\core;

use Adige\core\routing\Router;
use Adige\http\http\Request;
use Adige\http\http\Response;
use ReflectionException;
use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\http\http\exceptions\NotImplemented;

class Adige extends BaseObject
{
    public static ?Request $request = null;

    public static ?Response $response = null;

    public static ?Router $router = null;

    public static function commons(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error) {
                if (Adige::$response) {
                    header("HTTP/1.1 " . Adige::$response->getFullStatus());
                } else {
                    header("HTTP/1.1 500 Internal Server Error");
                }
                $fullMessage = explode("\n", $error['message']);
                $error['message'] = array_shift($fullMessage);
                array_shift($fullMessage);
                array_pop($fullMessage);
                $error['trace'] = join("\n", $fullMessage);
                dump($error);
                exit;
            }
        });
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws ControllerClassNotExists
     * @throws RequiredParamNotFound
     * @throws NotImplemented
     */
    public static function run(): void
    {
        static::$request = new Request();
        static::$response = new Response();
        static::$router = new Router(static::$request, static::$response);
        ob_start();
        $string = '';
        $response = static::$router->run();
        if (ob_get_level() > 0) {
            $string = ob_get_clean();
        }
        if ($response instanceof Response) {
            static::$response = $response;
        } elseif (is_array($response)) {
            static::$response->getHeaders()->setHeader('Content-Type', 'application/json');
            static::$response->setBody(json_encode($response) . $string);
        } elseif (is_string($response)) {
            static::$response->setBody($response . $string);
        } elseif (is_object($response)) {
            static::$response->setBody(json_encode($response) . $string);
        } else {
            static::$response->setBody($string);
        }
        static::$response->dispatch();
    }
}