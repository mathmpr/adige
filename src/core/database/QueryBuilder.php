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

    public function setWhereCondition(array $condition): self
    {
        $this->commands['where'] = $condition;
        return $this;
    }

    public function appendWhereCondition(string $operator, array $condition): self
    {
        $normalizedOperator = strtoupper(trim($operator));
        $existing = $this->commands['where'] ?? null;

        if ($existing === null) {
            $this->commands['where'] = $condition;
            return $this;
        }

        $this->commands['where'] = [
            $normalizedOperator,
            $existing,
            $condition,
        ];

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
        $parsed = str_replace(
            [':tableName', ':pkName'],
            [$tableName, $pkName],
            $field
        );

        $parsed = $this->resolveFieldAliasReference($parsed);

        return $this->qualifyField($parsed, $tableName);
    }

    protected function resolveFieldAliasReference(string $field): string
    {
        $aliasMap = $this->getCommand('fieldAliasMap', []);
        if (!is_array($aliasMap) || empty($aliasMap)) {
            return $field;
        }

        $trimmed = trim($field);
        if ($trimmed === '') {
            return $field;
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\.(.+)$/', $trimmed, $matches) !== 1) {
            return $field;
        }

        $reference = $matches[1];
        $suffix = $matches[2];

        if (!array_key_exists($reference, $aliasMap)) {
            return $field;
        }

        return $aliasMap[$reference] . '.' . $suffix;
    }

    protected function qualifyField(string $field, string $tableName): string
    {
        $trimmed = trim($field);

        if (
            $trimmed === ''
            || str_contains($trimmed, '.')
            || str_contains($trimmed, '(')
            || str_contains($trimmed, ' ')
            || str_contains($trimmed, ':')
        ) {
            return $field;
        }

        if (str_starts_with($trimmed, '`') && str_ends_with($trimmed, '`')) {
            return $tableName . '.' . $trimmed;
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed) === 1) {
            return $tableName . '.' . $trimmed;
        }

        return $field;
    }

}
