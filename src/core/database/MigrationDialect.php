<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use InvalidArgumentException;
use RuntimeException;

abstract class MigrationDialect extends BaseObject
{
    protected Connection $connection;

    public function __construct(
        Connection $connection,
    )
    {
        $this->connection = $connection;
        parent::__construct();
    }

    abstract public function tableExists(string $table): bool;
    abstract public function fieldExists(string $tableName, string $field): bool;
    abstract public function addColumn(string $table, MigrationField $field): void;
    abstract public function dropColumn(string $table, string $field): void;
    abstract public function createTable(string $table, array $fields, array $indexes = []): void;
    abstract public function dropTable(string $table): void;

    protected function assertValidIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid SQL identifier '$identifier'");
        }
    }

    protected function assertFieldTypeDefined(MigrationField $field): void
    {
        if ($field->getType() === null || trim($field->getType()) === '') {
            throw new RuntimeException("Field '{$field->getName()}' must define a type");
        }
    }

    protected function assertIndexDefinition(MigrationIndex $index): void
    {
        $columns = $index->getColumns();
        if ($columns === []) {
            throw new RuntimeException('Index definitions must contain at least one column');
        }

        foreach ($columns as $column) {
            $this->assertValidIdentifier($column);
        }

        if ($index->getName() !== null) {
            $this->assertValidIdentifier($index->getName());
        }
    }

    protected function quoteDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
