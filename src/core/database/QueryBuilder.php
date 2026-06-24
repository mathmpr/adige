<?php

namespace Adige\core\database;

use Adige\core\BaseObject;

abstract class QueryBuilder extends BaseObject
{
    private array $commands = [];

    private array $params = [];

    private int $argCount = 0;

    private string $rawSql = '';

    abstract public function build(
        string $tableName,
        ?string $pkName,
        array $payload,
        array $schemaFields
    ): self;

    public function setCommand(string $name, mixed $value): self
    {
        $this->commands[$name] = $value;
        return $this;
    }

    public function appendCommandItem(string $name, mixed $value): self
    {
        $this->commands[$name] ??= [];
        $this->commands[$name][] = $value;
        return $this;
    }

    public function getCommand(string $name, mixed $default = null): mixed
    {
        return $this->commands[$name] ?? $default;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function begin(string $command): self
    {
        $this->commands['begin'] = $command;
        return $this;
    }

    public function getRawSql(): string
    {
        return $this->rawSql;
    }

    public function setRawSql(string $rawSql): self
    {
        $this->rawSql = $rawSql;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function resetBuildState(): void
    {
        $this->argCount = 0;
        $this->params = [];
        $this->rawSql = '';
    }

    protected function addQueryParam(mixed $value): string
    {
        $queryKey = '';

        if (is_array($value)) {
            $queryKey .= '(';
            foreach ($value as $item) {
                $key = "arg{$this->argCount}";
                $queryKey .= ":{$key}, ";
                $this->params[$key] = $item;
                $this->argCount++;
            }
            return rtrim($queryKey, ', ') . ')';
        }

        $queryKey .= "arg{$this->argCount}";
        $this->params[$queryKey] = $value;
        $this->argCount++;

        return ':' . $queryKey;
    }

    protected function parseField(string $field, string $tableName, ?string $pkName): string
    {
        return str_replace(
            [':tableName', ':pkName'],
            [$tableName, $pkName],
            $field
        );
    }

}
