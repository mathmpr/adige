<?php

namespace Adige\console;

use Adige\helpers\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class CommandCatalog
{
    /**
     * @param array<int, string> $controllerNamespaces
     */
    public function __construct(
        protected array $controllerNamespaces = []
    ) {
        $this->controllerNamespaces = array_values(array_filter(array_map(
            static fn(string $namespace) => trim($namespace, '\\'),
            $controllerNamespaces
        )));
    }

    /**
     * @return array<int, array{name:string, commands:array<int, array{name:string,description:string,params:array<int, array{name:string,displayName:string,type:string,description:string,required:bool,hasDefault:bool,default:mixed}>}>}>
     */
    public function describeControllers(): array
    {
        $controllers = [];

        foreach ($this->discoverControllerClasses() as $controllerClass) {
            $description = $this->describeController($controllerClass);
            if (empty($description['commands'])) {
                continue;
            }

            $controllers[] = $description;
        }

        usort($controllers, static fn(array $left, array $right) => strcmp($left['name'], $right['name']));

        return $controllers;
    }

    /**
     * @return array{name:string,class:string,commands:array<int, array{name:string,description:string,params:array<int, array{name:string,displayName:string,type:string,description:string,required:bool,hasDefault:bool,default:mixed}>}>}
     */
    public function describeController(string $controllerClass): array
    {
        $reflection = new ReflectionClass($controllerClass);
        $controllerName = $this->buildControllerCommandName($controllerClass);
        $commands = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$this->isActionMethod($method, $reflection->getName())) {
                continue;
            }

            $actionName = Str::kebab(lcfirst(substr($method->getName(), strlen('action'))));
            $documentation = $this->parseDocComment($method->getDocComment() ?: '');
            $commands[] = [
                'name' => $actionName,
                'description' => $documentation['description'],
                'params' => $this->describeParameters($method, $documentation['params']),
            ];
        }

        usort($commands, static fn(array $first, array $second) => strcmp($first['name'], $second['name']));

        return [
            'name' => $controllerName,
            'class' => $controllerClass,
            'commands' => $commands,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function commandPaths(): array
    {
        $commands = [];

        foreach ($this->describeControllers() as $controller) {
            foreach ($controller['commands'] as $command) {
                $commands[] = $controller['name'] . '/' . $command['name'];
            }
        }

        sort($commands);

        return array_values(array_unique($commands));
    }

    /**
     * @return array<int, string>
     */
    public function suggest(string $input, int $limit = 5, float $minimumScore = 70.0): array
    {
        $normalizedInput = $this->normalizeCommand($input);
        if ($normalizedInput === '') {
            return [];
        }

        $scored = [];
        foreach ($this->commandPaths() as $commandPath) {
            $score = $this->calculateSimilarityScore($normalizedInput, $commandPath);
            if ($score < $minimumScore) {
                continue;
            }

            $scored[$commandPath] = $score;
        }

        arsort($scored);

        return array_slice(array_keys($scored), 0, $limit);
    }

    protected function calculateSimilarityScore(string $input, string $commandPath): float
    {
        similar_text($input, $commandPath, $fullScore);

        $inputSegments = explode('/', $input);
        $commandSegments = explode('/', $commandPath);
        $segmentScore = 0.0;
        $segmentMatches = 0;

        foreach ($inputSegments as $index => $segment) {
            if (!isset($commandSegments[$index])) {
                continue;
            }

            similar_text($segment, $commandSegments[$index], $score);
            $segmentScore += $score;
            $segmentMatches++;
        }

        if ($segmentMatches === 0) {
            return $fullScore;
        }

        return max($fullScore, $segmentScore / $segmentMatches);
    }

    protected function normalizeCommand(string $input): string
    {
        return trim(str_replace(':', '/', trim($input)), '/');
    }

    /**
     * @return array<int, string>
     */
    protected function discoverControllerClasses(): array
    {
        $classes = array_merge(
            $this->discoverControllerClassesFromClassMap(),
            $this->discoverControllerClassesFromPsr4()
        );

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * @return array<int, string>
     */
    protected function discoverControllerClassesFromClassMap(): array
    {
        $classMap = ROOT . 'vendor/composer/autoload_classmap.php';
        if (!is_file($classMap)) {
            return [];
        }

        $classes = [];
        foreach ((include $classMap) as $class => $path) {
            if (!$this->isConsoleControllerClass($class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @return array<int, string>
     */
    protected function discoverControllerClassesFromPsr4(): array
    {
        $classes = [];
        $psr4 = $this->getPsr4Mappings();

        foreach ($this->controllerNamespaces as $controllerNamespace) {
            foreach ($psr4 as $prefix => $directories) {
                if (!str_starts_with($controllerNamespace, $prefix)) {
                    continue;
                }

                $relativeNamespace = trim(substr($controllerNamespace, strlen($prefix)), '\\');
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);

                foreach ($directories as $directory) {
                    $controllerDirectory = rtrim($directory, DIRECTORY_SEPARATOR)
                        . ($relativePath !== '' ? DIRECTORY_SEPARATOR . $relativePath : '');

                    if (!is_dir($controllerDirectory)) {
                        continue;
                    }

                    $classes = array_merge(
                        $classes,
                        $this->discoverControllerClassesInDirectory($controllerDirectory, $controllerNamespace)
                    );
                }
            }
        }

        return $classes;
    }

    /**
     * @return array<int, string>
     */
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

            if ($this->isConsoleControllerClass($class) && class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function isConsoleControllerClass(string $class): bool
    {
        foreach ($this->controllerNamespaces as $controllerNamespace) {
            if (!str_starts_with($class, $controllerNamespace . '\\') && $class !== $controllerNamespace) {
                continue;
            }

            if (!str_ends_with($class, 'Controller') || str_ends_with($class, '\\BaseController')) {
                return false;
            }

            if (!class_exists($class)) {
                return false;
            }

            $reflection = new ReflectionClass($class);
            return !$reflection->isAbstract();
        }

        return false;
    }

    protected function buildControllerCommandName(string $controllerClass): string
    {
        $reflection = new ReflectionClass($controllerClass);
        return Str::kebab(preg_replace('/Controller$/', '', $reflection->getShortName()));
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

    /**
     * @return array<string, array<int, string>>
     */
    protected function getPsr4Mappings(): array
    {
        $autoloadPsr4 = ROOT . 'vendor/composer/autoload_psr4.php';
        if (!is_file($autoloadPsr4)) {
            return [];
        }

        $mappings = include $autoloadPsr4;
        return is_array($mappings) ? $mappings : [];
    }
}
