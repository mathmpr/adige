<?php

namespace Adige\core\database\dialects\mysql;

use Adige\core\BaseException;
use Adige\core\database\QueryBuilder;

class MysqlQueryBuilder extends QueryBuilder
{
    public function build(
        string $tableName,
        ?string $pkName,
        array $payload,
        array $schemaFields
    ): self {
        $this->resetBuildState();

        if ($this->getCommand('begin') === null) {
            return $this;
        }

        switch ($this->getCommand('begin')) {
            case 'INSERT INTO':
                $this->setRawSql(trim(sprintf(
                    '%s %s %s',
                    $this->getCommand('begin'),
                    $tableName,
                    $this->buildInsert($payload, $schemaFields)
                )));
                break;
            case 'SELECT':
                $this->setRawSql(trim(sprintf(
                    '%s %s %s FROM %s %s %s %s %s',
                    $this->getCommand('begin'),
                    $this->getQueryDistinct(),
                    $this->buildSelect($tableName),
                    $tableName,
                    $this->getQueryAlias(),
                    $this->buildJoin($tableName, $pkName),
                    $this->buildWhere($tableName, $pkName),
                    $this->buildOrder()
                )));
                break;
            case 'UPDATE':
                $this->setRawSql(trim(sprintf(
                    '%s %s %s SET %s %s',
                    $this->getCommand('begin'),
                    $tableName,
                    $this->getQueryAlias(),
                    $this->buildUpdate($payload),
                    $this->buildWhere($tableName, $pkName)
                )));
                break;
            case 'DELETE FROM':
                $this->setRawSql(trim(sprintf(
                    '%s %s %s %s',
                    $this->getCommand('begin'),
                    $tableName,
                    $this->getQueryAlias(),
                    $this->buildWhere($tableName, $pkName)
                )));
                break;
        }

        return $this;
    }

    public function getQueryDistinct(): string
    {
        return $this->getCommand('distinct', '');
    }

    public function getQueryAlias(): string
    {
        $alias = $this->getCommand('alias');
        return $alias !== null
            ? ' AS ' . $alias
            : '';
    }

    public function buildInsert(array $attributes, array $schemaFields): string
    {
        $fields = '(';
        $args = '(';

        foreach ($attributes as $attribute => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (in_array($attribute, $schemaFields, true)) {
                $fields .= "`{$attribute}`, ";
                $args .= $this->addQueryParam($value) . ', ';
            }
        }

        $fields = rtrim($fields, ', ') . ')';
        $args = rtrim($args, ', ') . ')';

        if ($args !== '()') {
            return "{$fields} VALUES{$args}";
        }

        return '';
    }

    public function buildUpdate(array $fields): string
    {
        $update = '';
        foreach ($fields as $field => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $update .= "{$field}=" . $this->addQueryParam($value) . ', ';
        }

        return rtrim($update, ', ');
    }

    public function buildSelect(string $tableName): string
    {
        $joinedSelects = $this->getCommand('joinedSelect');
        if (is_array($joinedSelects) && !empty($joinedSelects)) {
            return rtrim(join(', ', $joinedSelects), ', ');
        }

        $selects = $this->getCommand('select');
        if ($selects === null) {
            return '*';
        }

        foreach ($selects as &$select) {
            if (!is_string($select)) {
                continue;
            }

            if (
                $select === '*'
                || str_contains($select, '.')
                || stripos($select, ' as ') !== false
                || str_contains($select, '(')
            ) {
                continue;
            }

            $select = $tableName . '.' . $select;
        }

        return rtrim(join(', ', $selects), ', ');
    }

    /**
     * @throws BaseException
     */
    public function buildWhere(string $tableName, ?string $pkName): string
    {
        $condition = $this->getCommand('where');
        if ($condition === null) {
            return '';
        }

        $compiled = $this->compileWhereCondition($condition, $tableName, $pkName);
        if ($compiled === '') {
            return '';
        }

        return 'WHERE ' . $compiled;
    }

    /**
     * @throws BaseException
     */
    private function compileWhereCondition(mixed $condition, string $tableName, ?string $pkName): string
    {
        if (!is_array($condition) || $condition === []) {
            throw new BaseException('WHERE condition must be a non-empty array');
        }

        if ($this->isLogicalGroup($condition)) {
            $operator = strtoupper(trim((string) $condition[0]));
            $children = array_slice($condition, 1);

            if (count($children) === 0) {
                throw new BaseException("Logical group '{$operator}' must contain at least one condition");
            }

            $compiledChildren = array_map(
                fn (mixed $child): string => $this->compileWhereCondition($child, $tableName, $pkName),
                $children
            );

            return '(' . implode(" {$operator} ", $compiledChildren) . ')';
        }

        if ($this->isComparisonCondition($condition)) {
            return $this->compileComparisonCondition($condition[0], $condition[1], $condition[2], $tableName, $pkName);
        }

        if ($this->isEqualityMap($condition)) {
            $parts = [];

            foreach ($condition as $field => $value) {
                $parts[] = $this->compileComparisonCondition($field, '=', $value, $tableName, $pkName);
            }

            if (count($parts) === 1) {
                return $parts[0];
            }

            return '(' . implode(' AND ', $parts) . ')';
        }

        throw new BaseException('Unsupported WHERE condition structure');
    }

    private function isLogicalGroup(array $condition): bool
    {
        return isset($condition[0])
            && is_string($condition[0])
            && in_array(strtoupper(trim($condition[0])), ['AND', 'OR'], true);
    }

    private function isComparisonCondition(array $condition): bool
    {
        return array_is_list($condition)
            && count($condition) === 3
            && is_string($condition[0])
            && is_string($condition[1]);
    }

    private function isEqualityMap(array $condition): bool
    {
        return !array_is_list($condition);
    }

    /**
     * @throws BaseException
     */
    private function compileComparisonCondition(
        string $field,
        string $operator,
        mixed $value,
        string $tableName,
        ?string $pkName
    ): string {
        $normalizedField = $this->parseField($field, $tableName, $pkName);
        $normalizedOperator = strtoupper(trim($operator));

        return match ($normalizedOperator) {
            '=', '==' => $value === null
                ? "{$normalizedField} IS NULL"
                : "{$normalizedField}=" . $this->addQueryParam($value),
            '!=', '<>' => $value === null
                ? "{$normalizedField} IS NOT NULL"
                : "{$normalizedField}!=".$this->addQueryParam($value),
            '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE' =>
                "{$normalizedField}{$normalizedOperator}" . $this->addQueryParam($value),
            'IN', 'NOT IN' => $this->compileInCondition($normalizedField, $normalizedOperator, $value),
            default => throw new BaseException("Unsupported WHERE operator {$operator}"),
        };
    }

    /**
     * @throws BaseException
     */
    private function compileInCondition(string $field, string $operator, mixed $value): string
    {
        if (!is_array($value)) {
            throw new BaseException("WHERE operator {$operator} requires an array value");
        }

        if ($value === []) {
            return $operator === 'IN'
                ? '0=1'
                : '1=1';
        }

        return "{$field} {$operator} " . $this->addQueryParam($value);
    }

    public function buildJoin(string $tableName, ?string $pkName): string
    {
        $joins = [
            ...$this->getCommand('join', []),
            ...$this->getCommand('relationJoin', []),
        ];

        $parts = [];
        foreach ($joins as $join) {
            $parts[] = "{$join['type']} JOIN {$join['join'][0]} ON " .
                $this->parseField($join['join'][1], $tableName, $pkName);
        }

        return join(' ', $parts);
    }

    public function buildOrder(): string
    {
        $orderBy = $this->getCommand('orderBy');
        if ($orderBy === null) {
            return '';
        }

        return "ORDER BY {$orderBy[0]} {$orderBy[1]}";
    }
}
