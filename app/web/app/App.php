<?php

namespace app\web\app;

use Adige\console\ConsoleRequest;
use Adige\core\App as BaseApp;
use Adige\core\http\http\WebRequest;
use Adige\core\http\http\WebResponse;
use Adige\core\routing\Router;

/**
 * @property ConsoleRequest|WebRequest $request
 * @property WebResponse|null $response
 * @property Router $router
 */
class App extends BaseApp
{
    protected function defaultControllerNamespaces(bool $isConsoleApp): array
    {
        return array_merge(parent::defaultControllerNamespaces($isConsoleApp), $isConsoleApp
            ? [
                'app\\console\\controllers',
            ]
            : [
                'app\\web\\controllers',
            ]
        );
    }
}
