<?php

namespace Tests\Unit\Core;

use Adige\core\BaseEnvironment;
use Adige\core\ExceptionHandler;
use Adige\core\http\http\JsonResponse;
use Adige\core\http\http\RedirectResponse;
use Adige\core\http\http\WebResponse;
use Adige\core\routing\Route;
use Tests\Support\RouterTestCase;

class WebActionResponseFlowTest extends RouterTestCase
{
    protected function tearDown(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        parent::tearDown();
    }

    public function testActionReturningArrayBecomesJsonResponse(): void
    {
        Route::get('/responses/json', 'tests\\Fixtures\\web\\controllers\\ResponseController', 'actionJson');

        $router = $this->createRouter('/responses/json', 'GET', [], false, 'index', 'index', new WebResponse());
        $result = $router->run();
        $response = \Adige\core\Adige::$app->createResponse($result);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('application/json', $response->getHeaders()?->getHeader('Content-Type'));
        self::assertSame(encode_json(['fixture' => 'json-response']), $response->getBody());
    }

    public function testActionReturningRedirectResponseIsPreserved(): void
    {
        Route::get('/responses/redirect', 'tests\\Fixtures\\web\\controllers\\ResponseController', 'actionRedirect');

        $router = $this->createRouter('/responses/redirect', 'GET', [], false, 'index', 'index', new WebResponse());
        $result = $router->run();
        $response = \Adige\core\Adige::$app->createResponse($result);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaders()?->getHeader('Location'));
    }

    public function testInternalErrorFromActionBecomesHttp500(): void
    {
        BaseEnvironment::setEnv('APP_DEBUG', 'false');
        Route::get('/responses/error', 'tests\\Fixtures\\web\\controllers\\ResponseController', 'actionError');

        $router = $this->createRouter('/responses/error', 'GET', [], false, 'index', 'index', new WebResponse());

        try {
            $router->run();
            self::fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $exception) {
            $response = (new ExceptionHandler())->renderWebThrowable($exception);

            self::assertSame(500, $response->getStatusCode());
            self::assertSame('text/html; charset=UTF-8', $response->getHeaders()?->getHeader('Content-Type'));
            self::assertStringContainsString('Error 500', (string) $response->getBody());
        }
    }
}
