<?php

namespace Tests\Unit\Core;

use Adige\core\http\http\WebRequest;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class WebRequestContractTest extends TestCase
{
    public function testFixUriRemovesScriptDirectoryAndScriptName(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/public/index.php';

        $request = new class extends WebRequest {
            public function init(): void
            {
            }
        };

        $request->setUri('/public/index.php/admin/login');
        $request->fixUri();

        self::assertSame('/admin/login', $request->getUri());
    }

    public function testAcceptsJsonIsCaseInsensitive(): void
    {
        $request = new class extends WebRequest {
            public function init(): void
            {
            }
        };

        $request->setHeaders([
            'accept' => 'text/html, Application/Json',
        ]);

        self::assertTrue($request->acceptsJson());
    }

    public function testRequestExposesHeadersQueryBodyAndFilesWithoutNotices(): void
    {
        $request = new class extends WebRequest {
            public function init(): void
            {
            }
        };

        $this->setPrivateProperty($request, 'overHttps', false);
        $request->setHost('example.test');
        $request->setUri('/search');
        $request->setGet(['q' => 'adige']);
        $request->setPost(['page' => '1']);
        $request->setBody('raw-body');
        $request->setFiles(['avatar' => ['name' => 'a.png']]);
        $request->setHeaders(['X-Test' => '1']);
        $request->defineUrl();

        self::assertSame('http://example.test/search?q=adige', $request->getUrl());
        self::assertSame('1', $request->getHeaders()?->getHeader('x-test'));
        self::assertSame('adige', $request->get('q'));
        self::assertSame('1', $request->post('page'));
        self::assertSame('raw-body', $request->getBody());
        self::assertSame(['name' => 'a.png'], $request->files('avatar'));
        self::assertNull($request->files('missing'));
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(WebRequest::class, $property);
        $reflection->setValue($object, $value);
    }
}
