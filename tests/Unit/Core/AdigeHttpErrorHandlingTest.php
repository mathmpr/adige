<?php

namespace Tests\Unit\Core;

use Adige\core\BaseEnvironment;
use Adige\core\ExceptionHandler;
use Adige\core\http\http\WebResponse;
use Adige\core\http\http\exceptions\MethodNotAllowed;
use Adige\core\http\http\exceptions\NotImplemented;
use Adige\core\http\http\exceptions\RouteNotFound;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApp;
use Tests\Support\TestRequest;
use Adige\core\Adige;
use Adige\core\routing\Router;

class AdigeHttpErrorHandlingTest extends TestCase
{
    protected function tearDown(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        Adige::$app = null;
        parent::tearDown();
    }

    public function testResolveWebThrowableStatusCodeMapsFrameworkExceptions(): void
    {
        $handler = new ExceptionHandler();

        self::assertSame(404, $handler->resolveWebThrowableStatusCode(new RouteNotFound()));
        self::assertSame(405, $handler->resolveWebThrowableStatusCode(new MethodNotAllowed(['GET'])));
        self::assertSame(501, $handler->resolveWebThrowableStatusCode(new NotImplemented()));
        self::assertSame(500, $handler->resolveWebThrowableStatusCode(new \RuntimeException('boom')));
    }

    public function testApplyWebThrowableHeadersSetsAllowHeaderForMethodNotAllowed(): void
    {
        $response = new WebResponse();
        $handler = new ExceptionHandler();

        $handler->applyWebThrowableHeaders($response, new MethodNotAllowed(['GET', 'PATCH']));

        self::assertSame('GET, PATCH', $response->getHeaders()?->getHeader('Allow'));
    }

    public function testBuildConsoleErrorMessageContainsCoreDetails(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'true');
        $handler = new ExceptionHandler();
        $exception = new \RuntimeException('boom');

        $message = $handler->buildConsoleErrorMessage($exception);

        self::assertStringContainsString('Error: boom', $message);
        self::assertStringContainsString('.php:', $message);
    }

    public function testBuildConsoleErrorMessageSuggestsSimilarConsoleCommand(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        $request = new TestRequest('server/star', 'CONSOLE');
        $router = new Router($request, null, true);
        $router->setControllerNamespaces(['Adige\\console\\controllers']);

        $app = new TestApp();
        $app->request = $request;
        $app->router = $router;
        Adige::$app = $app;

        $handler = new ExceptionHandler();

        $message = $handler->buildConsoleErrorMessage(new RouteNotFound('Route not found: server/star'));

        self::assertStringContainsString('Did you mean:', $message);
        self::assertStringContainsString('server/start', $message);
    }

    public function testBuildErrorPayloadIsDetailedInDebugMode(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'true');
        $handler = new ExceptionHandler();
        $exception = new \RuntimeException('boom');

        $payload = $handler->buildErrorPayload($exception);

        self::assertSame(true, $payload['error']);
        self::assertSame(500, $payload['status']);
        self::assertSame('boom', $payload['message']);
        self::assertSame(\RuntimeException::class, $payload['type']);
        self::assertArrayHasKey('trace', $payload);
    }

    public function testBuildErrorPayloadIsGenericInProductionMode(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        $handler = new ExceptionHandler();

        $payload = $handler->buildErrorPayload(new \RuntimeException('boom'));

        self::assertSame(true, $payload['error']);
        self::assertSame(500, $payload['status']);
        self::assertSame('An internal error occurred.', $payload['message']);
        self::assertArrayNotHasKey('type', $payload);
        self::assertArrayNotHasKey('trace', $payload);
    }

    public function testBuildHtmlErrorPageIsGenericInProductionMode(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        $handler = new ExceptionHandler();

        $html = $handler->buildHtmlErrorPage(new \RuntimeException('boom'));

        self::assertStringContainsString('Error 500', $html);
        self::assertStringContainsString('An internal error occurred.', $html);
        self::assertStringNotContainsString('boom', $html);
        self::assertStringNotContainsString('.php:', $html);
    }
}
