<?php

namespace Adige\console\controllers;

use Adige\cli\Output;
use Adige\core\controller\BaseController as MainBaseController;
use Adige\helpers\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

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
        $controllers = $this->discoverControllerClasses();
        $lines = ['Available console commands:'];

        foreach ($controllers as $controllerClass) {
            if ($this->shouldHideControllerFromIndex($controllerClass)) {
                continue;
            }

            $lines[] = '';
            $lines[] = rtrim($this->renderControllerDescription($controllerClass));
        }

        return implode("\n", $lines) . "\n";
    }

    protected function renderControllerDescription(string $controllerClass): string
    {
        $reflection = new ReflectionClass($controllerClass);
        $controllerName = Str::kebab(preg_replace('/Controller$/', '', $reflection->getShortName()));
        $actions = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$this->isActionMethod($method, $reflection->getName())) {
                continue;
            }

            $actionName = Str::kebab(lcfirst(substr($method->getName(), strlen('action'))));
            $documentation = $this->parseDocComment($method->getDocComment() ?: '');
            $actions[] = [
                'name' => $actionName,
                'description' => $documentation['description'],
                'params' => $this->describeParameters($method, $documentation['params']),
            ];
        }

        usort($actions, fn(array $first, array $second) => strcmp($first['name'], $second['name']));

        if (empty($actions)) {
            return '';
        }

        $lines = [Output::bgGreen($controllerName)];

        foreach ($actions as $action) {
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
        $reflection = new ReflectionClass($controllerClass);
        $count = 0;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->isActionMethod($method, $reflection->getName())) {
                $count++;
            }
        }

        return $count;
    }

    protected function discoverControllerClasses(): array
    {
        $classes = [];

        foreach ($this->router?->getControllerNamespaces() ?? [] as $controllerNamespace) {
            $directory = $this->resolveNamespaceDirectory($controllerNamespace);
            if ($directory === null || !is_dir($directory)) {
                continue;
            }

            $classes = array_merge($classes, $this->discoverControllerClassesInDirectory($directory, $controllerNamespace));
        }

        $classes = array_values(array_unique($classes));
        sort($classes);
        return $classes;
    }

    protected function discoverControllerClassesInDirectory(string $directory, string $baseNamespace): array
    {
        $classes = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            if (!str_ends_with($filename, 'Controller.php') || $filename === 'BaseController.php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen(rtrim($directory, DIRECTORY_SEPARATOR)) + 1);
            $relativeClass = str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $relativePath
            );
            $class = trim($baseNamespace, '\\') . '\\' . $relativeClass;

            if (class_exists($class)) {
                $reflection = new ReflectionClass($class);
                if (!$reflection->isAbstract()) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    protected function resolveNamespaceDirectory(string $namespace): ?string
    {
        foreach ($this->getPsr4Mappings() as $prefix => $directory) {
            if (!str_starts_with($namespace, $prefix)) {
                continue;
            }

            $suffix = trim(substr($namespace, strlen($prefix)), '\\');
            $suffixPath = str_replace('\\', DIRECTORY_SEPARATOR, $suffix);
            return rtrim($directory, DIRECTORY_SEPARATOR)
                . (!empty($suffixPath) ? DIRECTORY_SEPARATOR . $suffixPath : '');
        }

        return null;
    }

    protected function getPsr4Mappings(): array
    {
        $composerJson = ROOT . 'composer.json';
        if (!is_file($composerJson)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($composerJson), true);
        if (!is_array($decoded)) {
            return [];
        }

        $mappings = [];
        foreach (($decoded['autoload']['psr-4'] ?? []) as $namespace => $directory) {
            $mappings[trim($namespace, '\\')] = ROOT . trim($directory, '/\\');
        }

        return $mappings;
    }

    protected function isActionMethod(ReflectionMethod $method, string $controllerClass): bool
    {
        return $method->class === $controllerClass
            && str_starts_with($method->getName(), 'action')
            && $method->getName() !== 'actionIndex';
    }

    protected function parseDocComment(string $docComment): array
    {
        $result = [
            'description' => '',
            'params' => [],
        ];

        if ($docComment === '') {
            return $result;
        }

        $description = [];

        foreach (explode("\n", $docComment) as $line) {
            $line = trim(trim($line), "/* \t\n\r\0\x0B");
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '@param ')) {
                $parts = preg_split('/\s+/', $line, 4) ?: [];
                $parameterType = $parts[1] ?? '';
                $parameterName = ltrim($parts[2] ?? '', '$');
                $result['params'][$parameterName] = [
                    'type' => $parameterType,
                    'description' => $parts[3] ?? '',
                ];
                continue;
            }

            if (str_starts_with($line, '@')) {
                continue;
            }

            $description[] = $line;
        }

        $result['description'] = implode(' ', $description);
        return $result;
    }

    protected function describeParameters(ReflectionMethod $method, array $docParams): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $docParam = $docParams[$parameterName] ?? [];
            $parameters[] = [
                'name' => $parameterName,
                'displayName' => Str::kebab($parameterName),
                'type' => $this->getParameterType($parameter, $docParam['type'] ?? ''),
                'description' => $docParam['description'] ?? '',
                'required' => !$parameter->isOptional() && !$parameter->isDefaultValueAvailable(),
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $parameters;
    }

    protected function getParameterType(ReflectionParameter $parameter, string $docType = ''): string
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if ($type->allowsNull() && $name !== 'null') {
                return $name . '|null';
            }
            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                static fn(ReflectionNamedType $namedType) => $namedType->getName(),
                $type->getTypes()
            ));
        }

        return $docType;
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
}
