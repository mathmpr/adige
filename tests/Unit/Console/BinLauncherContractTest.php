<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

class BinLauncherContractTest extends TestCase
{
    public function testPackageBinLauncherUsesComposerProvidedAutoloadPathWhenAvailable(): void
    {
        $contents = file_get_contents(ROOT . 'bin/adige');

        self::assertIsString($contents);
        self::assertStringContainsString("\$GLOBALS['_composer_autoload_path']", $contents);
        self::assertStringContainsString("__DIR__ . '/../vendor/autoload.php'", $contents);
        self::assertStringContainsString('Adige::run(null, getcwd());', $contents);
    }
}
