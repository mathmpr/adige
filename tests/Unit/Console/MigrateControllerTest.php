<?php

namespace Tests\Unit\Console;

use Adige\console\ConsoleResponse;
use Adige\console\controllers\MigrateController;
use Adige\core\Adige;
use Adige\core\database\Migration;
use Adige\core\routing\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestApp;
use Tests\Support\TestRequest;

class MigrateControllerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/adige-migrations-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        Adige::$app = null;
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testCreateUsesConfiguredPathAndNamespace(): void
    {
        $controller = $this->createController([
            'path' => $this->tempDir,
        ]);

        $message = $controller->actionCreate('Create Posts Table');

        $files = glob($this->tempDir . '/*.php') ?: [];
        self::assertCount(1, $files);
        self::assertStringContainsString('Created migration ', $message);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString('return new class extends Migration', $contents);
        self::assertStringNotContainsString('namespace ', $contents);
    }

    public function testUpAppliesPendingMigrationsInOrder(): void
    {
        $controller = new class($this->createRouter()) extends MigrateController {
            public array $available = [];
            public array $applied = [];
            public array $executed = [];
            public array $marked = [];
            public int $nextBatch = 2;

            protected function availableMigrationFiles(): array
            {
                return $this->available;
            }

            protected function appliedMigrationNames(): array
            {
                return $this->applied;
            }

            protected function loadMigrationInstance(string $migrationName, string $filePath): Migration
            {
                return new class($this, $migrationName) extends Migration {
                    public function __construct(private object $owner, private string $migrationName)
                    {
                        parent::__construct();
                    }

                    public function up(): void
                    {
                    }

                    public function down(): void
                    {
                    }

                    public function executeUp(): static
                    {
                        $this->owner->executed[] = $this->migrationName;
                        return $this;
                    }
                };
            }

            protected function markMigrationApplied(string $migrationName, int $batch): void
            {
                $this->marked[] = [$migrationName, $batch];
            }

            protected function nextBatchNumber(): int
            {
                return $this->nextBatch;
            }
        };

        $controller->available = [
            '2026_01_01_000001_initial' => '/tmp/one.php',
            '2026_01_02_000002_add_posts' => '/tmp/two.php',
            '2026_01_03_000003_add_comments' => '/tmp/three.php',
        ];
        $controller->applied = ['2026_01_01_000001_initial'];

        $output = $controller->actionUp();

        self::assertSame([
            '2026_01_02_000002_add_posts',
            '2026_01_03_000003_add_comments',
        ], $controller->executed);
        self::assertSame([
            ['2026_01_02_000002_add_posts', 2],
            ['2026_01_03_000003_add_comments', 2],
        ], $controller->marked);
        self::assertStringContainsString('Applied 2026_01_02_000002_add_posts (batch 2)', $output);
        self::assertStringContainsString('Applied 2026_01_03_000003_add_comments (batch 2)', $output);
    }

    public function testDownRevertsLatestBatches(): void
    {
        $controller = new class($this->createRouter()) extends MigrateController {
            public array $available = [];
            public array $batches = [];
            public array $reverted = [];
            public array $removed = [];

            protected function availableMigrationFiles(): array
            {
                return $this->available;
            }

            protected function latestAppliedBatches(int $steps): array
            {
                return array_slice(array_keys($this->batches), 0, $steps);
            }

            protected function loadMigrationInstance(string $migrationName, string $filePath): Migration
            {
                return new class($this, $migrationName) extends Migration {
                    public function __construct(private object $owner, private string $migrationName)
                    {
                        parent::__construct();
                    }

                    public function up(): void
                    {
                    }

                    public function down(): void
                    {
                    }

                    public function executeDown(): static
                    {
                        $this->owner->reverted[] = $this->migrationName;
                        return $this;
                    }
                };
            }

            protected function removeMigrationRecord(string $migrationName): void
            {
                $this->removed[] = $migrationName;
            }

            protected function appliedMigrationsForBatch(int $batch): array
            {
                return $this->batches[$batch] ?? [];
            }
        };

        $controller->available = [
            '2026_01_01_000001_initial' => '/tmp/one.php',
            '2026_01_02_000002_add_posts' => '/tmp/two.php',
            '2026_01_03_000003_add_comments' => '/tmp/three.php',
        ];
        $controller->batches = [
            3 => ['2026_01_03_000003_add_comments'],
            2 => ['2026_01_02_000002_add_posts'],
            1 => ['2026_01_01_000001_initial'],
        ];

        $output = $controller->actionDown(2);

        self::assertSame([
            '2026_01_03_000003_add_comments',
            '2026_01_02_000002_add_posts',
        ], $controller->reverted);
        self::assertSame($controller->reverted, $controller->removed);
        self::assertStringContainsString('Reverted 2026_01_03_000003_add_comments (batch 3)', $output);
        self::assertStringContainsString('Reverted 2026_01_02_000002_add_posts (batch 2)', $output);
    }

    private function createController(array $migrationsConfig = []): MigrateController
    {
        $app = new TestApp();
        $app->migrations = $migrationsConfig;
        Adige::$app = $app;

        return new MigrateController($this->createRouter());
    }

    private function createRouter(): Router
    {
        $request = new TestRequest('migrate/index', 'CONSOLE');
        return new Router($request, new ConsoleResponse(), false);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

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
