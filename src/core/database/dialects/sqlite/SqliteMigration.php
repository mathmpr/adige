<?php

namespace Adige\core\database\dialects\sqlite;

use Adige\core\database\MigrationDialect;
use Adige\core\database\MigrationField;
use Adige\core\database\MigrationIndex;

class SqliteMigration extends MigrationDialect
{
    public function tableExists(string $table): bool
    {
        $this->assertValidIdentifier($table);
        $result = $this->connection->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
            [$table]
        );
        return $result->rowCount() > 0;
    }

    public function fieldExists(string $tableName, string $field): bool
    {
        $this->assertValidIdentifier($tableName);
        $this->assertValidIdentifier($field);
        $result = $this->connection->query(
            "SELECT 1 FROM pragma_table_info('$tableName') WHERE name = ?",
            [$field]
        );
        return $result->rowCount() > 0;
    }

    public function addColumn(string $table, MigrationField $field): void
    {
        $this->assertValidIdentifier($table);
        $this->connection->query(
            sprintf(
                'ALTER TABLE "%s" ADD COLUMN %s',
                $table,
                $this->compileFieldDefinition($field)
            )
        );
    }

    public function dropColumn(string $table, string $field): void
    {
        $this->assertValidIdentifier($table);
        $this->assertValidIdentifier($field);
        $this->connection->query(
            sprintf(
                'ALTER TABLE "%s" DROP COLUMN "%s"',
                $table,
                $field
            )
        );
    }

    public function createTable(string $table, array $fields, array $indexes = []): void
    {
        $this->assertValidIdentifier($table);

        $definitions = array_map(fn (MigrationField $field) => $this->compileFieldDefinition($field), $fields);
        $this->connection->query(
            sprintf(
                'CREATE TABLE "%s" (%s)',
                $table,
                implode(', ', $definitions)
            )
        );

        foreach ($indexes as $index) {
            $this->createIndex($table, $index);
        }
    }

    public function dropTable(string $table): void
    {
        $this->assertValidIdentifier($table);
        $this->connection->query(
            sprintf(
                'DROP TABLE "%s"',
                $table
            )
        );
    }

    private function compileFieldDefinition(MigrationField $field): string
    {
        $this->assertValidIdentifier($field->getName());
        $this->assertFieldTypeDefined($field);

        $type = strtoupper($field->getType());
        $parts = [
            sprintf('"%s"', $field->getName()),
            $type,
        ];

        if ($field->isAutoIncrement()) {
            if ($type !== 'INTEGER') {
                throw new \RuntimeException("SQLite auto increment field '{$field->getName()}' must use INTEGER type");
            }

            $parts[] = 'PRIMARY KEY';
            $parts[] = 'AUTOINCREMENT';
            return implode(' ', $parts);
        }

        if (!$field->isNullable()) {
            $parts[] = 'NOT NULL';
        }

        if ($field->hasDefault()) {
            $parts[] = 'DEFAULT ' . $this->quoteDefaultValue($field->getDefault());
        }

        if ($field->isUnique()) {
            $parts[] = 'UNIQUE';
        }

        if ($field->isPrimary()) {
            $parts[] = 'PRIMARY KEY';
        }

        return implode(' ', $parts);
    }

    private function createIndex(string $table, MigrationIndex $index): void
    {
        $this->assertIndexDefinition($index);

        $name = $index->getName() ?? $this->buildIndexName($table, $index->getColumns(), $index->isUnique());
        $columns = implode(', ', array_map(
            static fn (string $column): string => sprintf('"%s"', $column),
            $index->getColumns()
        ));

        $this->connection->query(sprintf(
            'CREATE %sINDEX "%s" ON "%s" (%s)',
            $index->isUnique() ? 'UNIQUE ' : '',
            $name,
            $table,
            $columns
        ));
    }

    private function buildIndexName(string $table, array $columns, bool $unique): string
    {
        return sprintf(
            '%s_%s_%s',
            $table,
            implode('_', $columns),
            $unique ? 'uniq' : 'idx'
        );
    }
}
