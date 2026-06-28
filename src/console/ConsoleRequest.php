<?php

namespace Adige\console;

use Adige\core\BaseRequest;
use Adige\helpers\Str;

class ConsoleRequest extends BaseRequest
{
    private array $argv = [];

    private array $options = [];

    private int $argc = 0;

    public function init(): void
    {
        if (isset($_SERVER['argv'])) {
            $this->argv = $_SERVER['argv'];
            $this->argc = count($_SERVER['argv']);
        }
        $this->setMethod('CONSOLE');
        $this->setUri($this->argv[1] ?? '');
        $this->processOptions();
        parent::init();
    }

    public function setUri(string $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    function fixUri(): void
    {
        $this->getUriParts();
    }

    private function processOptions(): void
    {
        $options = array_filter($this->argv, function ($v) {
            return is_string($v) && str_starts_with($v, '--');
        });

        $this->options = [];

        foreach ($options as $option) {
            $option = str_replace('--', '', $option);
            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
            } else {
                $key = $option;
                $value = true;
            }

            $this->options[$key] = $value;
            $camelKey = Str::camel($key);
            $this->options[$camelKey] = $value;
        }
        $this->setInput($this->options);
    }

    public function getArgv(): array
    {
        return $this->argv;
    }

    public function getArgc(): int
    {
        return $this->argc;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
