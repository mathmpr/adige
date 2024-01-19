<?php

namespace Adige\cli;

use Adige\core\BaseObject;
use ReflectionMethod;

class Command extends BaseObject
{

    private string $command;
    private array $params;
    private bool $default;
    private string $class;
    private array $documentation = [];
    private ?ReflectionMethod $method = null;

    public function __construct(string $command, array $params = [], ?string $default = Console::NOT_DEFAULT_COMMAND)
    {
        $this->command = $command;
        $this->params = $params;
        $this->default = $default;
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @param string $command
     * @return Command
     */
    public function setCommand(string $command): Command
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array $params
     * @return Command
     */
    public function setParams(array $params): Command
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * @param bool $default
     * @return Command
     */
    public function setDefault(bool $default): Command
    {
        $this->default = $default;
        return $this;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @param string $class
     * @return Command
     */
    public function setClass(string $class): Command
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return array
     */
    public function getDocumentation(): array
    {
        return $this->documentation;
    }

    /**
     * @param array $documentation
     * @return Command
     */
    public function setDocumentation(array $documentation): Command
    {
        $this->documentation = $documentation;
        return $this;
    }

    /**
     * @return ReflectionMethod|null
     */
    public function getMethod(): ?ReflectionMethod
    {
        return $this->method;
    }

    /**
     * @param ReflectionMethod|null $method
     * @return Command
     */
    public function setMethod(?ReflectionMethod $method): Command
    {
        $this->method = $method;
        return $this;
    }

}
