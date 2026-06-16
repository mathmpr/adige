<?php

namespace Adige\core;

use Adige\core\http\http\WebRequest;
use Adige\core\http\http\WebResponse;
use Adige\core\http\http\exceptions\MethodNotAllowed;
use Adige\core\http\http\exceptions\NotImplemented;
use Adige\core\http\http\exceptions\RouteNotFound;
use ErrorException;
use Throwable;

class ExceptionHandler extends BaseObject
{
    protected const GENERIC_MESSAGE = 'An internal error occurred.';

    protected int $baseOutputBufferLevel = 0;

    public function init(): void
    {
        $this->baseOutputBufferLevel = ob_get_level();
        parent::init();
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);

        set_exception_handler(function (Throwable $throwable): void {
            $this->handleThrowable($throwable);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $this->handleThrowable(
                new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        });
    }

    public function handleError(
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleThrowable(Throwable $throwable, bool $terminate = true): void
    {
        if (!$this->isDebugMode()) {
            $this->logThrowable($throwable);
        }

        if ($this->isWebRequest()) {
            $this->renderWebThrowable($throwable)->dispatch();
        } else {
            fwrite(STDERR, $this->buildConsoleErrorMessage($throwable));
        }

        if ($terminate) {
            exit(1);
        }
    }

    public function renderWebThrowable(Throwable $throwable): WebResponse
    {
        while (ob_get_level() > $this->baseOutputBufferLevel) {
            ob_end_clean();
        }

        $request = Adige::$app?->request;
        $response = Adige::$app?->response instanceof WebResponse
            ? Adige::$app->response
            : new WebResponse(500);

        $response->setStatusCode($this->resolveWebThrowableStatusCode($throwable));
        $this->applyWebThrowableHeaders($response, $throwable);

        if ($request instanceof WebRequest && $request->acceptsJson()) {
            $response->getHeaders()?->setHeader('Content-Type', 'application/json');
            $response->setBody(encode_json($this->buildErrorPayload($throwable)));
            return $response;
        }

        $response->getHeaders()?->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->setBody($this->buildHtmlErrorPage($throwable));
        return $response;
    }

    public function buildConsoleErrorMessage(Throwable $throwable): string
    {
        if (!$this->isDebugMode()) {
            return sprintf(
                "Error: %s\n",
                $this->resolveUserFacingMessage($throwable)
            );
        }

        return sprintf(
            "Error: %s\n\n%s:%d\n\n%s\n",
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTraceAsString()
        );
    }

    public function buildErrorPayload(Throwable $throwable): array
    {
        $payload = [
            'error' => true,
            'status' => $this->resolveWebThrowableStatusCode($throwable),
            'message' => $this->resolveUserFacingMessage($throwable),
        ];

        if ($this->isDebugMode()) {
            $payload['type'] = $throwable::class;
            $payload['message'] = $throwable->getMessage();
            $payload['file'] = $throwable->getFile();
            $payload['line'] = $throwable->getLine();
            $payload['trace'] = $throwable->getTrace();
        }

        return $payload;
    }

    public function resolveWebThrowableStatusCode(Throwable $throwable): int
    {
        return match (true) {
            $throwable instanceof RouteNotFound => 404,
            $throwable instanceof MethodNotAllowed => 405,
            $throwable instanceof NotImplemented => 501,
            default => 500,
        };
    }

    public function applyWebThrowableHeaders(WebResponse $response, Throwable $throwable): void
    {
        if ($throwable instanceof MethodNotAllowed && !empty($throwable->getAllowedMethods())) {
            $response->getHeaders()?->setHeader('Allow', implode(', ', $throwable->getAllowedMethods()));
        }
    }

    public function buildHtmlErrorPage(Throwable $throwable): string
    {
        $status = $this->resolveWebThrowableStatusCode($throwable);
        $title = htmlspecialchars($this->resolveUserFacingMessage($throwable), ENT_QUOTES, 'UTF-8');

        if (!$this->isDebugMode()) {
            return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; margin: 0; padding: 24px; }
        h1 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>Error {$status}</h1>
    <p>{$title}</p>
</body>
</html>
HTML;
        }

        $file = htmlspecialchars($throwable->getFile(), ENT_QUOTES, 'UTF-8');
        $trace = htmlspecialchars($throwable->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Server Error</title>
    <style>
        body { font-family: monospace; background: #111; color: #eee; margin: 0; padding: 24px; }
        h1 { margin-top: 0; }
        .meta { color: #aaa; margin-bottom: 16px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #1b1b1b; padding: 16px; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>Error {$status}</h1>
    <div class="meta">{$file}:{$throwable->getLine()}</div>
    <p>{$title}</p>
    <pre>{$trace}</pre>
</body>
</html>
HTML;
    }

    protected function isWebRequest(): bool
    {
        return Adige::$app?->request instanceof WebRequest || !BaseEnvironment::isConsoleApp();
    }

    protected function isDebugMode(): bool
    {
        return determine_var(BaseEnvironment::getEnv('APP_DEBUG', false)) === true;
    }

    protected function resolveUserFacingMessage(Throwable $throwable): string
    {
        if ($this->isDebugMode()) {
            return $throwable->getMessage();
        }

        return match (true) {
            $throwable instanceof RouteNotFound => 'Route not found.',
            $throwable instanceof MethodNotAllowed => 'Method not allowed.',
            default => self::GENERIC_MESSAGE,
        };
    }

    protected function logThrowable(Throwable $throwable): void
    {
        error_log(sprintf(
            '[%s] %s in %s:%d',
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine()
        ));
    }
}
