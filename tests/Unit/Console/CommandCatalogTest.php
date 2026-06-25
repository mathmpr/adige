<?php

namespace Tests\Unit\Console;

use Adige\console\CommandCatalog;
use PHPUnit\Framework\TestCase;

class CommandCatalogTest extends TestCase
{
    public function testDescribeControllersBuildsConsoleCommandsFromNamespaces(): void
    {
        $catalog = new CommandCatalog(['Adige\\console\\controllers']);

        $controllers = $catalog->describeControllers();
        $server = array_values(array_filter(
            $controllers,
            static fn(array $controller): bool => $controller['name'] === 'server'
        ));

        self::assertCount(1, $server);
        self::assertSame('start', $server[0]['commands'][0]['name']);
        self::assertSame('port', $server[0]['commands'][0]['params'][1]['name']);
    }

    public function testSuggestAcceptsSlashAndColonInput(): void
    {
        $catalog = new CommandCatalog(['Adige\\console\\controllers']);

        self::assertSame(['server/start'], $catalog->suggest('server/star'));
        self::assertSame(['server/start'], $catalog->suggest('server:start'));
    }
}
