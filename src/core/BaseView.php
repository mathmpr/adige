<?php

namespace Adige\core;

use Adige\core\events\Observable;
use RuntimeException;
use Throwable;

class BaseView extends BaseObject
{
    use Observable;

    public const EVENT_BEFORE_RENDER = 'beforeRender';
    public const EVENT_AFTER_RENDER = 'afterRender';
    public const EVENT_RENDER_ERROR = 'renderError';

    protected string $viewDirectory = '';

    /**
     * @var array<string, string>
     */
    protected array $aliases = [];

    protected string $extension = 'php';

    public function __construct(
        string $viewDirectory = '',
        array $aliases = [],
        string $extension = 'php',
    )
    {
        if ($viewDirectory !== '') {
            $this->setViewDirectory($viewDirectory);
        }

        foreach ($aliases as $alias => $directory) {
            $this->registerAlias($alias, $directory);
        }

        $this->extension = ltrim($extension, '.');
        if ($this->extension === '') {
            throw new RuntimeException('View extension cannot be empty');
        }

        parent::__construct();
    }

    public function render(string $view, array $params = []): string
    {
        $viewFile = $this->resolveViewFile($view);
        $this->trigger(self::EVENT_BEFORE_RENDER, $view, $params, $viewFile);

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            extract($params, EXTR_SKIP);
            require $viewFile;
            $content = (string) ob_get_clean();
            $this->trigger(self::EVENT_AFTER_RENDER, $view, $params, $viewFile, $content);
            return $content;
        } catch (Throwable $throwable) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            $this->trigger(self::EVENT_RENDER_ERROR, $view, $params, $viewFile, $throwable);
            throw $throwable;
        }
    }

    public function escape(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function setViewDirectory(string $viewDirectory): static
    {
        $this->viewDirectory = $this->normalizeDirectory($viewDirectory);
        return $this;
    }

    public function getViewDirectory(): string
    {
        return $this->viewDirectory;
    }

    public function registerAlias(string $alias, string $directory): static
    {
        $this->aliases[$this->normalizeAlias($alias)] = $this->normalizeDirectory($directory);
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    protected function resolveViewFile(string $view): string
    {
        [$baseDirectory, $logicalView] = $this->resolveBaseDirectoryAndView($view);
        $viewPath = $baseDirectory . DIRECTORY_SEPARATOR . $this->normalizeViewName($logicalView) . '.' . $this->extension;

        if (!is_file($viewPath)) {
            throw new RuntimeException("View file not found: $viewPath");
        }

        return $viewPath;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveBaseDirectoryAndView(string $view): array
    {
        if (!str_starts_with($view, '@')) {
            if ($this->viewDirectory === '') {
                throw new RuntimeException('View directory is not configured');
            }

            return [$this->viewDirectory, $view];
        }

        $separatorPosition = strpos($view, '/');
        if ($separatorPosition === false || $separatorPosition === 1 || $separatorPosition === strlen($view) - 1) {
            throw new RuntimeException("Invalid aliased view reference: $view");
        }

        $alias = substr($view, 0, $separatorPosition);
        $logicalView = substr($view, $separatorPosition + 1);
        $normalizedAlias = $this->normalizeAlias($alias);

        if (!isset($this->aliases[$normalizedAlias])) {
            throw new RuntimeException("View alias '$normalizedAlias' is not registered");
        }

        return [$this->aliases[$normalizedAlias], $logicalView];
    }

    protected function normalizeDirectory(string $directory): string
    {
        if (!is_dir($directory)) {
            throw new RuntimeException("View directory '$directory' does not exist or is not a directory");
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new RuntimeException("View directory '$directory' could not be resolved");
        }

        return rtrim($realDirectory, DIRECTORY_SEPARATOR);
    }

    protected function normalizeAlias(string $alias): string
    {
        if (!preg_match('/^@[A-Za-z0-9_-]+$/', $alias)) {
            throw new RuntimeException("Invalid view alias '$alias'");
        }

        return $alias;
    }

    protected function normalizeViewName(string $view): string
    {
        if ($view === '') {
            throw new RuntimeException('View name cannot be empty');
        }

        if (str_contains($view, "\0")) {
            throw new RuntimeException('View name cannot contain null bytes');
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $view) === 1 || str_starts_with($view, '/') || str_starts_with($view, '\\')) {
            throw new RuntimeException("Absolute view paths are not allowed: $view");
        }

        $normalized = str_replace('\\', '/', $view);
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1) {
            throw new RuntimeException("Path traversal is not allowed in view name: $view");
        }

        $normalized = str_replace('.', '/', $normalized);
        $normalized = trim($normalized, '/');
        if ($normalized === '') {
            throw new RuntimeException('View name cannot be empty');
        }

        return $normalized;
    }
}
