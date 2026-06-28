<?php

namespace Tests\Unit\Core;

use Adige\console\ConsoleResponse;
use Adige\core\file\File;
use Adige\core\InvalidResponseException;
use Adige\core\http\http\FileResponse;
use Adige\core\http\http\JsonResponse;
use Adige\core\http\http\WebResponse;
use Adige\core\http\http\exceptions\JsonEncodingException;
use Tests\Fixtures\models\FakeModel;
use Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

class AppResponseNormalizationTest extends TestCase
{
    public function testStringResponsePreservesStatusHeadersAndBuffer(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();

        $response = $app->createResponse('hello', 201, ['X-Test' => '1'], '!');

        self::assertInstanceOf(WebResponse::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('1', $response->getHeaders()?->getHeader('X-Test'));
        self::assertSame('hello!', $response->getBody());
    }

    public function testArrayResponseBecomesJsonResponseWithPredictableHeadersAndStatus(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();

        $response = $app->createResponse(['ok' => true], 202, ['X-Test' => '1']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('1', $response->getHeaders()?->getHeader('X-Test'));
        self::assertSame('application/json', $response->getHeaders()?->getHeader('Content-Type'));
        self::assertSame(encode_json(['ok' => true]), $response->getBody());
    }

    public function testFileResponsePreservesStatusAndCustomHeaders(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();
        $file = new File(ROOT . 'README.md');

        $response = $app->createResponse($file, 206, ['X-Test' => '1']);

        self::assertInstanceOf(FileResponse::class, $response);
        self::assertSame(206, $response->getStatusCode());
        self::assertSame('1', $response->getHeaders()?->getHeader('X-Test'));
        self::assertSame($file, $response->getBody());
    }

    public function testActiveRecordModelIsNormalizedViaToArrayBeforeJsonEncoding(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();
        $model = new FakeModel(['id' => 7, 'name' => 'Ada']);

        $response = $app->createResponse($model);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(encode_json(['id' => 7, 'name' => 'Ada']), $response->getBody());
    }

    public function testNonSerializableObjectThrowsJsonEncodingExceptionInWebNormalization(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();
        $recursive = new \stdClass();
        $recursive->self = $recursive;

        $this->expectException(JsonEncodingException::class);

        $app->createResponse($recursive);
    }

    public function testNonSerializableObjectThrowsJsonEncodingExceptionInConsoleNormalization(): void
    {
        $app = new TestApp();
        $app->response = new ConsoleResponse();
        $recursive = new \stdClass();
        $recursive->self = $recursive;

        $this->expectException(JsonEncodingException::class);

        $app->createResponse($recursive);
    }

    public function testUnsupportedScalarResponseThrowsInvalidResponseException(): void
    {
        $app = new TestApp();
        $app->response = new WebResponse();

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('bool');

        $app->createResponse(false);
    }
}
