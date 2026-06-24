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
        $selects = $this->getCommand('select');
        if ($selects === null) {
            return '*';
        }

        foreach ($selects as &$select) {
            $select = $tableName . '.' . $select;
        }

        return rtrim(join(', ', $selects), ', ');
    }

    /**
     * @throws BaseException
     */
    public function buildWhere(string $tableName, ?string $pkName): string
    {
        $openGroups = 0;
        $whereString = '';
        $commands = $this->getCommand('where', []);

        foreach ($commands as $where) {
            $cond = $where['cond'];
            $field = array_keys($cond)[0];
            $value = $cond[$field];
            $conditionSymbol = '=';

            if (!is_string($field) && !$where['in']) {
                $field = $cond[0];
                $conditionSymbol = $cond[1];
                $value = $cond[2];
            }

            if ($where['in']) {
                $conditionSymbol = ' IN ';
            }

            $field = $this->parseField($field, $tableName, $pkName);

            switch ($where['command']) {
                case 'initial':
                    $whereString .= "WHERE {$field}{$conditionSymbol}" . $this->addQueryParam($value);
                    break;
                case 'AND':
                    $whereString .= ($openGroups > 0 ? ')' : '') .
                        " AND ({$field}{$conditionSymbol}" . $this->addQueryParam($value);
                    $openGroups = 1;
                    break;
                case 'OR':
                    $whereString .= " OR ({$field}{$conditionSymbol}" . $this->addQueryParam($value);
                    $openGroups++;
                    break;
                default:
                    throw new BaseException("No recognized WHERE command {$where['command']}");
            }
        }

        if ($openGroups > 0) {
            return $whereString . str_repeat(')', $openGroups);
        }

        return $whereString;
    }

    public function buildJoin(string $tableName, ?string $pkName): string
    {
        $joinString = '';
        foreach ($this->getCommand('join', []) as $join) {
            $joinString .= "{$join['type']} JOIN {$join['join'][0]} ON " .
                $this->parseField($join['join'][1], $tableName, $pkName);
        }

        return $joinString;
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
