<?php

namespace Tests\Unit\Core;

use Adige\console\ConsoleResponse;
use Adige\core\App;
use Adige\core\BaseRequest;
use Adige\core\BaseResponse;
use Adige\core\ExceptionHandler;
use Adige\core\events\Event;
use Adige\core\http\http\WebResponse;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApp;
use Tests\Support\TestRequest;

class CoreLifecycleEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Event::clear();
        parent::tearDown();
    }

    public function testGlobalBaseRequestListenersReceiveSubclassLifecycleEvents(): void
    {
        $events = [];

        Event::on(BaseRequest::class, BaseRequest::EVENT_BEFORE_INIT, function (BaseRequest $request) use (&$events): void {
            $events[] = ['beforeInit', $request::class];
        });
        Event::on(BaseRequest::class, BaseRequest::EVENT_AFTER_FIX_URI, function (BaseRequest $request) use (&$events): void {
            $events[] = ['afterFixUri', $request->getUri()];
        });

        new TestRequest('health/check');

        self::assertSame([
            ['beforeInit', TestRequest::class],
            ['afterFixUri', '/health/check'],
        ], $events);
    }

    public function testGlobalBaseResponseListenersReceiveDispatchLifecycleEvents(): void
    {
        $events = [];

        Event::on(BaseResponse::class, BaseResponse::EVENT_BEFORE_DISPATCH, function (BaseResponse $response) use (&$events): void {
            $events[] = ['beforeDispatch', $response::class];
        });
        Event::on(BaseResponse::class, BaseResponse::EVENT_AFTER_DISPATCH, function (BaseResponse $response) use (&$events): void {
            $events[] = ['afterDispatch', $response::class];
        });

        $response = new ConsoleResponse();
        $response->dispatch();

        self::assertSame([
            ['beforeDispatch', ConsoleResponse::class],
            ['afterDispatch', ConsoleResponse::class],
        ], $events);
    }

    public function testAppEmitsNormalizeAndResponseEmissionEvents(): void
    {
        $app = new TestApp();
        $events = [];

        $app->on(App::EVENT_BEFORE_NORMALIZE_RESPONSE, function (App $app, mixed $result, string $buffer) use (&$events): void {
            $events[] = ['beforeNormalize', $result, $buffer];
        });
        $app->on(App::EVENT_AFTER_NORMALIZE_RESPONSE, function (App $app, BaseResponse $response, mixed $result, string $buffer) use (&$events): void {
            $events[] = ['afterNormalize', $response::class, $result, $buffer];
        });
        $app->on(App::EVENT_BEFORE_EMIT_RESPONSE, function (App $app, BaseResponse $response) use (&$events): void {
            $events[] = ['beforeEmit', $response::class];
        });
        $app->on(App::EVENT_AFTER_EMIT_RESPONSE, function (App $app, BaseResponse $response) use (&$events): void {
            $events[] = ['afterEmit', $response::class];
        });

        $response = new class extends BaseResponse {
            public function dispatch(): void
            {
            }
        };

        $normalized = $app->normalizeResponse('ok', 'buffer');
        $app->emitResponse($response);

        self::assertSame([
            ['beforeNormalize', 'ok', 'buffer'],
            ['afterNormalize', ConsoleResponse::class, 'ok', 'buffer'],
            ['beforeEmit', $response::class],
            ['afterEmit', $response::class],
        ], $events);
        self::assertInstanceOf(ConsoleResponse::class, $normalized);
    }

    public function testExceptionHandlerEmitsThrowableAndWebRenderEvents(): void
    {
        $handler = new class extends ExceptionHandler {
            protected function isWebRequest(): bool
            {
                return false;
            }

            protected function logThrowable(\Throwable $throwable): void
            {
            }

            public function buildConsoleErrorMessage(\Throwable $throwable): string
            {
                return '';
            }
        };
        $events = [];

        $handler->on(ExceptionHandler::EVENT_BEFORE_HANDLE_THROWABLE, function (
            ExceptionHandler $handler,
            \Throwable $throwable,
            bool $terminate
        ) use (&$events): void {
            $events[] = ['beforeHandle', $throwable->getMessage(), $terminate];
        });

        $handler->on(ExceptionHandler::EVENT_AFTER_HANDLE_THROWABLE, function (
            ExceptionHandler $handler,
            \Throwable $throwable,
            bool $terminate
        ) use (&$events): void {
            $events[] = ['afterHandle', $throwable->getMessage(), $terminate];
        });

        $handler->on(ExceptionHandler::EVENT_BEFORE_RENDER_WEB_THROWABLE, function (
            ExceptionHandler $handler,
            \Throwable $throwable
        ) use (&$events): void {
            $events[] = ['beforeRenderWeb', $throwable->getMessage()];
        });

        $handler->on(ExceptionHandler::EVENT_AFTER_RENDER_WEB_THROWABLE, function (
            ExceptionHandler $handler,
            \Throwable $throwable,
            WebResponse $response
        ) use (&$events): void {
            $events[] = ['afterRenderWeb', $throwable->getMessage(), $response->getStatusCode()];
        });

        $response = $handler->renderWebThrowable(new \RuntimeException('boom'));
        $handler->handleThrowable(new \RuntimeException('bang'), false);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([
            ['beforeRenderWeb', 'boom'],
            ['afterRenderWeb', 'boom', 500],
            ['beforeHandle', 'bang', false],
            ['afterHandle', 'bang', false],
        ], $events);
    }
}
