<?php

namespace Adige\console\controllers;

use Adige\cli\Output;
use Adige\console\CommandCatalog;
use Adige\core\controller\BaseController as MainBaseController;

class BaseController extends MainBaseController
{
    public function actionIndex(): void
    {
        echo $this->explain();
    }

    protected function explain(): string
    {
        if ($this->isConsoleIndexController()) {
            return $this->explainAllControllers();
        }

        return $this->renderControllerDescription($this->getClassFullName());
    }

    protected function isConsoleIndexController(): bool
    {
        return $this->getClassShortName() === 'IndexController';
    }

    protected function explainAllControllers(): string
    {
        $controllers = $this->commandCatalog()->describeControllers();
        $lines = ['Available console commands:'];

        foreach ($controllers as $controller) {
            if ($this->shouldHideControllerFromIndex($controller['class'])) {
                continue;
            }

            $lines[] = '';
            $lines[] = rtrim($this->renderControllerDescription($controller['class']));
        }

        return implode("\n", $lines) . "\n";
    }

    protected function renderControllerDescription(string $controllerClass): string
    {
        $controller = $this->commandCatalog()->describeController($controllerClass);
        if (empty($controller['commands'])) {
            return '';
        }

        $controllerName = $controller['name'];
        $lines = [Output::bgGreen($controllerName)];

        foreach ($controller['commands'] as $action) {
            $description = $action['description'] !== '' ? $action['description'] : 'No description';
            $lines[] = "- ". Output::bgCyan("{$controllerName}/{$action['name']}") .": {$description}";

            foreach ($action['params'] as $parameter) {
                $required = $parameter['required'] ? 'required' : 'optional';
                $type = $parameter['type'] !== '' ? " <{$parameter['type']}>" : '';
                $default = $parameter['hasDefault']
                    ? ' default=' . $this->stringifyValue($parameter['default'])
                    : '';
                $paramDescription = $parameter['description'] !== '' ? $parameter['description'] : 'No description';
                $lines[] = "  --{$parameter['displayName']}{$type}: {$paramDescription} ({$required}{$default})";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    protected function shouldHideControllerFromIndex(string $controllerClass): bool
    {
        if ($controllerClass === IndexController::class) {
            return true;
        }

        return $this->getControllerActionCount($controllerClass) === 0;
    }

    protected function getControllerActionCount(string $controllerClass): int
    {
        return count($this->commandCatalog()->describeController($controllerClass)['commands']);
    }

    protected function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return '[]';
        }

        return (string) $value;
    }

    protected function commandCatalog(): CommandCatalog
    {
        return new CommandCatalog($this->router?->getControllerNamespaces() ?? []);
    }
}
