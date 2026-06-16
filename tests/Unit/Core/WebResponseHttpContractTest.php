<?php

namespace Tests\Unit\Core;

use Adige\core\file\File;
use Adige\core\http\http\FileResponse;
use Adige\core\http\http\Headers;
use Adige\core\http\http\JsonResponse;
use Adige\core\http\http\WebResponse;
use PHPUnit\Framework\TestCase;

class WebResponseHttpContractTest extends TestCase
{
    public function testStringBodyDefaultsToHtmlContentType(): void
    {
        $response = (new WebResponse())
            ->setBody('<h1>Hello</h1>');

        self::assertSame('text/html; charset=UTF-8', $response->resolveContentType());
    }

    public function testJsonResponsePreservesExplicitContentTypeWhenProvided(): void
    {
        $response = new JsonResponse(['ok' => true], 200, [
            'Content-Type' => 'application/problem+json',
        ]);

        self::assertSame('application/problem+json', $response->resolveContentType());
    }

    public function testFileResponseDefaultsToBinaryContentType(): void
    {
        $response = new FileResponse(new File(ROOT . 'README.md'));

        self::assertSame('application/octet-stream', $response->resolveContentType());
    }

    public function testDispatchHeadersAlwaysEmitContentTypeFirst(): void
    {
        $response = new WebResponse(200, [
            'X-Test' => '1',
            'Cache-Control' => 'no-cache',
        ]);
        $response->setBody('hello');

        self::assertSame([
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Test' => '1',
            'Cache-Control' => 'no-cache',
        ], $response->getDispatchHeaders());
    }

    public function testHeadersReplaceExistingValueCaseInsensitively(): void
    {
        $headers = new Headers([
            'content-type' => 'application/json',
        ]);

        $headers->setHeader('Content-Type', 'text/plain');

        self::assertSame([
            'Content-Type' => 'text/plain',
        ], $headers->getHeaders());
    }
}
