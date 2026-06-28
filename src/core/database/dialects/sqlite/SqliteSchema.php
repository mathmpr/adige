<?php

namespace Adige\core\database\dialects\sqlite;

use Adige\core\database\Schema;
use PDO;

class SqliteSchema extends Schema
{
    protected static function readTableSchema(string $tableName, PDO $db): array
    {
        $stmt = $db->query(sprintf('PRAGMA table_info(%s)', $tableName));
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $schema = [];

        foreach ($columns as $column) {
            $schema[] = [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => (int) $column['notnull'] === 0,
                'primary' => (int) $column['pk'] > 0,
                'default' => $column['dflt_value'],
                'auto_increment' => false,
            ];
        }

        return $schema;
    }
}
