<?php

namespace Adige\console\controllers;

use Adige\core\Adige;
use RuntimeException;

class InstallController extends BaseController
{
    /**
     * create a project-root launcher for the current installation
     * @return void
     */
    public function actionIndex(): void
    {
        $path = Adige::basePath() . 'adige';

        if (is_file($path)) {
            throw new RuntimeException("Launcher '$path' already exists.");
        }

        if (file_put_contents($path, $this->launcherTemplate()) === false) {
            throw new RuntimeException("Unable to create launcher '$path'.");
        }

        @chmod($path, 0755);

        echo "Created launcher at {$path}\n";
    }

    protected function launcherTemplate(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

use Composer\Autoload\ClassLoader;

$autoload = __DIR__ . '/vendor/autoload.php';
if (!class_exists(ClassLoader::class) && is_file($autoload)) {
    require_once $autoload;
}

use Adige\core\Adige;

Adige::run(null, __DIR__);
PHP;
    }
}
