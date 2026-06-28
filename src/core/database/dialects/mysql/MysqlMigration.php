<?php

namespace Adige\core\database\dialects\mysql;

use Adige\core\database\MigrationDialect;
use Adige\core\database\MigrationField;
use Adige\core\database\MigrationIndex;

class MysqlMigration extends MigrationDialect
{
    public function tableExists(string $table): bool
    {
        $this->assertValidIdentifier($table);
        return $this->connection->query('SHOW TABLES LIKE ?', [$table])->rowCount() > 0;
    }

    public function fieldExists(string $tableName, string $field): bool
    {
        $this->assertValidIdentifier($tableName);
        $this->assertValidIdentifier($field);
        $result = $this->connection->query("SHOW COLUMNS FROM `$tableName` LIKE ?", [$field]);
        return $result->rowCount() > 0;
    }

    public function addColumn(string $table, MigrationField $field): void
    {
        $this->assertValidIdentifier($table);
        $this->connection->query(
            sprintf(
                'ALTER TABLE `%s` ADD COLUMN %s',
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
                'ALTER TABLE `%s` DROP COLUMN `%s`',
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
                'CREATE TABLE `%s` (%s)',
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
                'DROP TABLE `%s`',
                $table
            )
        );
    }

    private function compileFieldDefinition(MigrationField $field): string
    {
        $this->assertValidIdentifier($field->getName());
        $this->assertFieldTypeDefined($field);

        $parts = [
            sprintf('`%s`', $field->getName()),
            strtoupper($field->getType()),
        ];

        $parts[] = $field->isNullable() ? 'NULL' : 'NOT NULL';

        if ($field->hasDefault()) {
            $parts[] = 'DEFAULT ' . $this->quoteDefaultValue($field->getDefault());
        }

        if ($field->isAutoIncrement()) {
            $parts[] = 'AUTO_INCREMENT';
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
            static fn (string $column): string => sprintf('`%s`', $column),
            $index->getColumns()
        ));

        $this->connection->query(sprintf(
            'CREATE %sINDEX `%s` ON `%s` (%s)',
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
