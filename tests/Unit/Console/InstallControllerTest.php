<?php

namespace Tests\Unit\Console;

use Adige\console\ConsoleResponse;
use Adige\console\controllers\InstallController;
use Adige\core\Adige;
use Adige\core\routing\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestRequest;

class InstallControllerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/adige-install-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        Adige::setBasePath($this->tempDir);
    }

    protected function tearDown(): void
    {
        Adige::setBasePath(getcwd() ?: dirname(__DIR__, 3));
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testInstallCreatesProjectRootLauncherWithProjectVendorPath(): void
    {
        $controller = new InstallController($this->createRouter());

        ob_start();
        $controller->actionIndex();
        $output = ob_get_clean();
        $launcherPath = $this->tempDir . DIRECTORY_SEPARATOR . 'adige';

        self::assertSame("Created launcher at {$launcherPath}\n", $output);
        self::assertFileExists($launcherPath);

        $contents = file_get_contents($launcherPath);
        self::assertIsString($contents);
        self::assertStringContainsString("__DIR__ . '/vendor/autoload.php'", $contents);
        self::assertStringContainsString('Adige::run(null, __DIR__);', $contents);
        self::assertStringNotContainsString("../vendor/autoload.php", $contents);
        self::assertStringNotContainsString('_composer_autoload_path', $contents);
    }

    public function testInstallFailsWhenLauncherAlreadyExists(): void
    {
        $launcherPath = $this->tempDir . DIRECTORY_SEPARATOR . 'adige';
        file_put_contents($launcherPath, 'existing');

        $controller = new InstallController($this->createRouter());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Launcher '{$launcherPath}' already exists.");

        $controller->actionIndex();
    }

    private function createRouter(): Router
    {
        $request = new TestRequest('install/index', 'CONSOLE');
        return new Router($request, new ConsoleResponse(), false);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
