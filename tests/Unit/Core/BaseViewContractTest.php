<?php

namespace Tests\Unit\Core;

use Adige\core\BaseView;
use Adige\core\events\Event;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BaseViewContractTest extends TestCase
{
    private string $baseViewsPath;

    private string $sharedViewsPath;

    private string $otherViewsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $fixturesRoot = ROOT . 'tests/Fixtures/views';
        $this->baseViewsPath = $fixturesRoot . '/base';
        $this->sharedViewsPath = $fixturesRoot . '/shared';
        $this->otherViewsPath = $fixturesRoot . '/other';
    }

    protected function tearDown(): void
    {
        Event::clear();
        parent::tearDown();
    }

    public function testRenderUsesInstanceViewDirectoryAndEscapesParams(): void
    {
        $view = new BaseView($this->baseViewsPath);

        $content = $view->render('index', ['name' => '<Admin>']);

        self::assertSame('Hello &lt;Admin&gt;!', $content);
    }

    public function testNestedRenderSupportsExplicitAliases(): void
    {
        $view = new BaseView($this->baseViewsPath, [
            '@shared' => $this->sharedViewsPath,
        ]);

        $content = $view->render('nested', ['value' => 'team']);

        self::assertSame('Nested(Shared team)', $content);
    }

    public function testSequentialRendersDoNotLeakResolvedDirectories(): void
    {
        $view = new BaseView($this->baseViewsPath, [
            '@shared' => $this->sharedViewsPath,
        ]);

        self::assertSame('Shared alpha', $view->render('@shared/partial', ['value' => 'alpha']));
        self::assertSame('Hello beta!', $view->render('index', ['name' => 'beta']));
    }

    public function testDistinctInstancesKeepIndependentDirectories(): void
    {
        $first = new BaseView($this->baseViewsPath);
        $second = new BaseView($this->otherViewsPath);

        self::assertSame('Hello one!', $first->render('index', ['name' => 'one']));
        self::assertSame('Other directory', $second->render('hello'));
    }

    public function testRejectsPathTraversalAndAbsolutePaths(): void
    {
        $view = new BaseView($this->baseViewsPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal is not allowed');

        $view->render('../secret');
    }

    public function testRejectsUnknownAlias(): void
    {
        $view = new BaseView($this->baseViewsPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View alias '@shared' is not registered");

        $view->render('@shared/partial');
    }

    public function testEmitsBeforeAndAfterRenderEvents(): void
    {
        $view = new BaseView($this->baseViewsPath);
        $events = [];

        $view->on(BaseView::EVENT_BEFORE_RENDER, function (BaseView $view, string $name, array $params, string $viewFile) use (&$events): void {
            $events[] = ['before', $name, $params['name'], basename($viewFile)];
        });
        $view->on(BaseView::EVENT_AFTER_RENDER, function (BaseView $view, string $name, array $params, string $viewFile, string $content) use (&$events): void {
            $events[] = ['after', $name, $params['name'], basename($viewFile), $content];
        });

        $content = $view->render('index', ['name' => 'events']);

        self::assertSame('Hello events!', $content);
        self::assertSame([
            ['before', 'index', 'events', 'index.php'],
            ['after', 'index', 'events', 'index.php', 'Hello events!'],
        ], $events);
    }

    public function testCleansOutputBufferAndEmitsErrorEventOnFailure(): void
    {
        $view = new BaseView($this->baseViewsPath);
        $events = [];
        $initialLevel = ob_get_level();

        $view->on(BaseView::EVENT_RENDER_ERROR, function (
            BaseView $view,
            string $name,
            array $params,
            string $viewFile,
            \Throwable $throwable
        ) use (&$events): void {
            $events[] = [$name, basename($viewFile), $throwable->getMessage()];
        });

        try {
            $view->render('throws');
            self::fail('Expected view render to throw');
        } catch (RuntimeException $exception) {
            self::assertSame('view exploded', $exception->getMessage());
        }

        self::assertSame($initialLevel, ob_get_level());
        self::assertSame([
            ['throws', 'throws.php', 'view exploded'],
        ], $events);
    }
}
