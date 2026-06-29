<?php

namespace Tests\Unit\Core;

use Adige\console\ConsoleResponse;
use Adige\console\controllers\MigrateController;
use Adige\core\Adige;
use Adige\core\App;
use Adige\core\http\http\WebResponse;
use Adige\core\routing\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestRequest;

class PackageConsumerAppContractTest extends TestCase
{
    private string $consumerAppPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consumerAppPath = ROOT . 'tests/Fixtures/consumer-app/';
        Adige::setBasePath($this->consumerAppPath);
        Adige::$app = null;
    }

    protected function tearDown(): void
    {
        Adige::$app = null;
        Adige::setBasePath(ROOT);
        parent::tearDown();
    }

    public function testConsumerBootstrapConfiguresViewHandler(): void
    {
        $app = new App();
        Adige::$app = $app;

        self::assertSame(
            'Hello Ada &lt;Admin&gt; from consumer app!',
            trim($app->view->render('hello', ['name' => 'Ada <Admin>']))
        );
    }

    public function testConsumerBootstrapConfiguresRouterAutodiscoveryForConsumerControllers(): void
    {
        $app = new App();
        $app->request = new TestRequest('/consumer', 'GET');
        $app->response = new WebResponse();
        Adige::$app = $app;

        self::assertSame(['fixture' => 'consumer-index'], $app->router->run());
    }

    public function testConsumerBootstrapConfiguresConsoleNamespacesAlongsideConsumerNamespaces(): void
    {
        $app = new App();
        $app->request = new TestRequest('index', 'CONSOLE');
        $app->response = new ConsoleResponse();
        Adige::$app = $app;

        self::assertSame([
            'Adige\\console\\controllers',
            'Tests\\Fixtures\\consumerapp\\controllers',
        ], $app->router->getControllerNamespaces());
    }

    public function testConsumerBootstrapConfiguresMigrationPath(): void
    {
        $app = new App();
        $app->request = new TestRequest('migrate/index', 'CONSOLE');
        $app->response = new ConsoleResponse();
        Adige::$app = $app;

        $controller = new class($app->router) extends MigrateController {
            public function exposedMigrationPath(): string
            {
                return $this->migrationPath();
            }

            public function exposedAvailableMigrationFiles(): array
            {
                return $this->availableMigrationFiles();
            }
        };

        self::assertSame(
            $this->consumerAppPath . 'migrations',
            $controller->exposedMigrationPath()
        );
        self::assertArrayHasKey(
            '2026_06_29_000000_fixture_consumer_table',
            $controller->exposedAvailableMigrationFiles()
        );
    }
}
