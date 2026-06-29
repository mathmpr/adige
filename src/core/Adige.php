<?php

namespace Adige\core;

use Adige\core\controller\exceptions\ControllerClassNotExists;
use Adige\core\controller\exceptions\RequiredParamNotFound;
use Adige\core\http\http\exceptions\NotImplemented;
use ReflectionException;
use RuntimeException;
use Throwable;

class Adige extends BaseObject
{
    const REQUEST_HANDLER = 'request';
    const RESPONSE_HANDLER = 'response';
    const CACHE_HANDLER = 'cache';
    const ROUTER_HANDLER = 'router';
    const DB_HANDLER = 'db';
    const VIEW_HANDLER = 'view';
    const SCHEMA_CONFIG = 'schema';
    const MIGRATIONS_CONFIG = 'migrations';

    /** @var class-string<App> */
    protected static string $appClass = App::class;

    protected static bool $commonsInitialized = false;

    protected static ?ExceptionHandler $exceptionHandler = null;

    protected static ?string $basePath = null;

    protected static ?string $vendorDir = null;

    public static App|null $app = null;

    public static function loadEnv(): void
    {
        static::ensurePathConstants();

        $envs = [
            static::basePath() . '.env',
            static::packageRoot() . '.env',
        ];

        foreach (array_values(array_unique($envs)) as $env) {
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
    public static function run(?string $appClass = null, ?string $basePath = null): void
    {
        if ($basePath !== null) {
            static::setBasePath($basePath);
        }

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

    public static function setBasePath(string $basePath): void
    {
        $normalized = rtrim(trim($basePath), DIRECTORY_SEPARATOR);
        if ($normalized === '') {
            throw new RuntimeException('Base path must not be empty.');
        }

        static::$basePath = $normalized . DIRECTORY_SEPARATOR;
    }

    public static function basePath(): string
    {
        if (static::$basePath === null) {
            static::$basePath = static::resolveDefaultBasePath();
        }

        return static::$basePath;
    }

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
    }

    public static function vendorDir(): string
    {
        if (static::$vendorDir !== null) {
            return static::$vendorDir;
        }

        foreach (static::searchPathAncestors(static::packageRoot()) as $directory) {
            $composerDirectory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer';
            if (is_file($composerDirectory . DIRECTORY_SEPARATOR . 'autoload_psr4.php')) {
                static::$vendorDir = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                return static::$vendorDir;
            }

            $vendorDirectory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vendor';
            if (is_file($vendorDirectory . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_psr4.php')) {
                static::$vendorDir = rtrim($vendorDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                return static::$vendorDir;
            }
        }

        static::$vendorDir = static::packageRoot() . 'vendor' . DIRECTORY_SEPARATOR;
        return static::$vendorDir;
    }

    public static function exceptionHandler(): ExceptionHandler
    {
        if (static::$exceptionHandler === null) {
            static::$exceptionHandler = new ExceptionHandler();
        }

        return static::$exceptionHandler;
    }

    protected static function ensurePathConstants(): void
    {
        if (!defined('ROOT')) {
            define('ROOT', static::packageRoot());
        }

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', static::basePath());
        }
    }

    protected static function resolveDefaultBasePath(): string
    {
        if (defined('APP_ROOT')) {
            return static::normalizePathConstant(APP_ROOT);
        }

        if (defined('BASE_PATH')) {
            return static::normalizePathConstant(BASE_PATH);
        }

        $envBasePath = getenv('ADIGE_BASE_PATH');
        if (is_string($envBasePath) && trim($envBasePath) !== '') {
            return static::normalizePathConstant($envBasePath);
        }

        $vendorDirectory = static::vendorDir();
        if (is_dir($vendorDirectory)) {
            return dirname(rtrim($vendorDirectory, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR;
        }

        $workingDirectory = getcwd();
        if (is_string($workingDirectory) && $workingDirectory !== '') {
            return rtrim($workingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return static::packageRoot();
    }

    protected static function normalizePathConstant(string $path): string
    {
        $realPath = realpath($path);
        return rtrim($realPath !== false ? $realPath : $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array<int, string>
     */
    protected static function searchPathAncestors(string $path): array
    {
        $ancestors = [];
        $current = rtrim($path, DIRECTORY_SEPARATOR);

        while ($current !== '') {
            $ancestors[] = $current;
            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        return $ancestors;
    }
}
