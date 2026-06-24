<?php

namespace Adige\core;

use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\http\http\exceptions\NotImplemented;
use ReflectionException;
use Throwable;

class Adige extends BaseObject
{
    const REQUEST_HANDLER = 'request';
    const RESPONSE_HANDLER = 'response';
    const CACHE_HANDLER = 'cache';
    const ROUTER_HANDLER = 'router';
    const DB_HANDLER = 'db';
    const SCHEMA_CONFIG = 'schema';

    const HANDLERS = [
        self::REQUEST_HANDLER,
        self::RESPONSE_HANDLER,
        self::CACHE_HANDLER,
        self::DB_HANDLER,
        self::ROUTER_HANDLER,
    ];

    /** @var class-string<App> */
    protected static string $appClass = App::class;

    protected static bool $commonsInitialized = false;

    protected static ?ExceptionHandler $exceptionHandler = null;

    public static App|null $app = null;

    public static function loadEnv(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', ROOT);
        }

        $envs = [
            ROOT . '.env',
            APP_ROOT . '.env',
        ];

        foreach ($envs as $env) {
            if (file_exists($env)) {
                BaseEnvironment::readEnv($env);
            }
        }
    }

    public static function commons(): void
    {
        self::loadEnv();

        if (static::$commonsInitialized) {
            return;
        }

        static::$commonsInitialized = true;
        static::exceptionHandler()->register();
    }

    /**
     * @param string|null $appClass
     * @return void
     * @throws BaseException
     * @throws ControllerClassNotExists
     * @throws NotImplemented
     * @throws ReflectionException
     * @throws RequiredParamNotFound
     */
    public static function run(?string $appClass = null): void
    {
        static::commons();

        $appClass = $appClass ?: static::$appClass;
        if (!is_a($appClass, App::class, true)) {
            throw new BaseException(
                "App class must extend or be " . App::class
            );
        }
        static::$app = static::createApp($appClass);
        ob_start();

        try {
            $result = static::$app->router->run();
            $buffer = ob_get_clean() ?: '';
            $response = static::$app->normalizeResponse($result, $buffer);
            static::$app->emitResponse($response);
        } catch (Throwable $throwable) {
            ob_get_clean();
            static::exceptionHandler()->handleThrowable($throwable);
        }
    }

    /**
     * @param class-string<App> $appClass
     */
    protected static function createApp(string $appClass): App
    {
        return new $appClass();
    }

    public static function exceptionHandler(): ExceptionHandler
    {
        if (static::$exceptionHandler === null) {
            static::$exceptionHandler = new ExceptionHandler();
        }

        return static::$exceptionHandler;
    }
}
