<?php

namespace Adige\core;

use Adige\console\ConsoleResponse;
use Adige\console\ConsoleRequest;
use Adige\core\collection\Collection;
use Adige\core\database\ActiveRecord;
use Adige\core\database\Schema;
use Adige\core\events\Observable;
use Adige\core\file\File;
use Adige\core\http\http\FileResponse;
use Adige\core\http\http\JsonResponse;
use Adige\core\http\http\WebRequest;
use Adige\core\http\http\WebResponse;
use Adige\core\routing\Router;

/**
 * @property ConsoleRequest|WebRequest $request
 * @property WebResponse|ConsoleResponse|null $response
 * @property Router $router
 */
class App extends BaseObject
{
    use Observable;

    public const EVENT_BEFORE_NORMALIZE_RESPONSE = 'beforeNormalizeResponse';
    public const EVENT_AFTER_NORMALIZE_RESPONSE = 'afterNormalizeResponse';
    public const EVENT_BEFORE_EMIT_RESPONSE = 'beforeEmitResponse';
    public const EVENT_AFTER_EMIT_RESPONSE = 'afterEmitResponse';

    protected array $definitions = [];

    protected array $handlers = [];

    public function init(): void
    {
        $bootstrap = $this->bootstrap();
        $this->applyBootstrapConfiguration($bootstrap);
        $this->definitions = deep_merge(
            $this->defaultHandlers(),
            $bootstrap
        );
        $this->initializeInstantHandlers();
        parent::init();
    }

    protected function defaultHandlers(): array
    {
        $isConsoleApp = BaseEnvironment::isConsoleApp();

        return [
            Adige::REQUEST_HANDLER => $isConsoleApp
                ? ConsoleRequest::class
                : WebRequest::class,
            Adige::RESPONSE_HANDLER => $isConsoleApp
                ? ConsoleResponse::class
                : WebResponse::class,
            Adige::ROUTER_HANDLER => [
                'class' => Router::class,
                '__construct()' => [
                    '@request',
                    '@response',
                    true,
                    $isConsoleApp ? 'index' : 'server'
                ],
                'controllerNamespaces' => $this->defaultControllerNamespaces($isConsoleApp),
            ],
        ];
    }

    protected function defaultControllerNamespaces(bool $isConsoleApp): array
    {
        return $isConsoleApp
            ? [
                'Adige\\console\\controllers',
                'app\\console\\controllers',
            ]
            : [
                'app\\web\\controllers',
                'app\\controllers',
            ];
    }

    private function bootstrap(): array
    {
        $directories = [
            ROOT . 'app/common',
            ROOT . 'app/console',
            ROOT . 'app/web'
        ];
        $bootstrap = [];
        foreach ($directories as $directory) {
            if (BaseEnvironment::isConsoleApp() && str_ends_with($directory, 'web')) {
                continue;
            }
            if (is_dir($directory)) {
                $files = scandir($directory);
                foreach ($files as $file) {
                    if (str_ends_with($file, 'bootstrap.php')) {
                        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
                        $config = include $path;
                        if (is_array($config)) {
                            $bootstrap = deep_merge($bootstrap, $config);
                        }
                    }
                }
            }
        }
        return $bootstrap;
    }

    protected function applyBootstrapConfiguration(array &$bootstrap): void
    {
        if (isset($bootstrap['schema']) && is_array($bootstrap['schema'])) {
            Schema::configure($bootstrap['schema']);
            unset($bootstrap['schema']);
        }
    }

    protected function make(mixed $definition): mixed
    {
        if (is_callable($definition)) {
            return $definition($this);
        }

        if (is_string($definition)) {
            return new $definition();
        }

        if (is_array($definition) && isset($definition[0]) && is_string($definition[0])) {
            $class = $definition[0];
            $args = $definition[1] ?? [];

            if (is_callable($args)) {
                $args = $args($this);
            }

            if (!is_array($args)) {
                $args = [$args];
            }

            return new $class(...$args);
        }

        if (is_array($definition) && isset($definition['class']) && is_string($definition['class'])) {
            $class = $definition['class'];
            $args = $this->resolveReferences($definition['__construct()'] ?? []);

            if (!is_array($args)) {
                $args = [$args];
            }

            $object = new $class(...$args);

            foreach ($definition as $property => $value) {
                if (in_array($property, ['instant', 'class', '__construct()'], true)) {
                    continue;
                }
                $object->$property = $this->resolveReferences($value);
            }

            return $object;
        }

        return $definition;
    }

    protected function resolveReferences(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, '@')) {
            return $this->{substr($value, 1)};
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->resolveReferences($item);
            }
        }

        return $value;
    }

    protected function initializeInstantHandlers(): void
    {
        foreach ($this->definitions as $name => $definition) {
            if (!$this->shouldInstantiateInstantly($definition)) {
                continue;
            }

            $this->__get($name);
        }
    }

    protected function shouldInstantiateInstantly(mixed $definition): bool
    {
        return is_array($definition)
            && array_key_exists('instant', $definition)
            && $definition['instant'] === true;
    }

    public function normalizeResponse(mixed $result, string $buffer = ''): BaseResponse
    {
        $this->trigger(self::EVENT_BEFORE_NORMALIZE_RESPONSE, $result, $buffer);
        $response = $this->createResponse($result, 200, [], $buffer);
        $this->trigger(self::EVENT_AFTER_NORMALIZE_RESPONSE, $response, $result, $buffer);
        return $response;
    }

    public function createResponse(
        mixed  $result,
        int    $statusCode = 200,
        array  $headers = [],
        string $buffer = ''
    ): BaseResponse
    {
        if ($result instanceof BaseResponse) {
            return $result;
        }

        if (
            !is_null($result)
            && !is_string($result)
            && !is_array($result)
            && !is_object($result)
            && !$result instanceof File
        ) {
            throw new InvalidResponseException(
                'Unsupported response type: ' . get_debug_type($result)
            );
        }

        if ($this->response instanceof WebResponse) {
            return $this->normalizeWebResponse($result, $statusCode, $headers, $buffer);
        }

        return $this->normalizeConsoleResponse($result, $buffer);
    }

    protected function normalizeWebResponse(
        mixed  $result,
        int    $statusCode = 200,
        array  $headers = [],
        string $buffer = ''
    ): WebResponse
    {
        $response = $this->response instanceof WebResponse
            ? $this->response
            : new WebResponse();
        $response->setStatusCode($statusCode);

        foreach ($headers as $key => $value) {
            $response->getHeaders()?->setHeader($key, $value);
        }

        if ($result instanceof File) {
            return new FileResponse($result, $response->getStatusCode(), $headers);
        }

        $result = $this->normalizeSerializableValue($result);

        if (is_array($result) || is_object($result)) {
            $jsonResponse = new JsonResponse($result, $response->getStatusCode(), $headers);
            if ($buffer !== '') {
                $jsonResponse->setBody(($jsonResponse->getBody() ?? '') . $buffer);
            }
            return $jsonResponse;
        }

        if (is_string($result)) {
            $response->setBody($result . $buffer);
            return $response;
        }

        $response->setBody($buffer);
        return $response;
    }

    protected function normalizeConsoleResponse(mixed $result, string $buffer = ''): ConsoleResponse
    {
        $response = $this->response instanceof ConsoleResponse
            ? $this->response
            : new ConsoleResponse();

        $response->setExitCode(0);
        $result = $this->normalizeSerializableValue($result);

        if (is_array($result) || is_object($result)) {
            $response->appendStdout(encode_json($result) . $buffer);
        } elseif (is_string($result)) {
            $response->appendStdout($result . $buffer);
        } elseif ($result instanceof File) {
            $response->appendStdout(file_get_contents($result->getLocation()) ?: '');
            $response->appendStdout($buffer);
        } else {
            $response->appendStdout($buffer);
        }

        return $response;
    }

    protected function normalizeSerializableValue(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->toArray();
        }

        if ($value instanceof ActiveRecord) {
            return $value->toArray();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    public function emitResponse(BaseResponse $response): void
    {
        $this->trigger(self::EVENT_BEFORE_EMIT_RESPONSE, $response);
        $response->dispatch();
        $this->trigger(self::EVENT_AFTER_EMIT_RESPONSE, $response);

        if ($response instanceof ConsoleResponse) {
            exit($response->getExitCode());
        }
    }

    public function __get($name)
    {
        if (!array_key_exists($name, $this->handlers) && array_key_exists($name, $this->definitions)) {
            $this->handlers[$name] = $this->make($this->definitions[$name]);
        }
        return $this->handlers[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->handlers[$name] = $value;
    }
}
