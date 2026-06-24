<?php

namespace Adige\core\database\dialects\mysql;

use Adige\core\database\Schema;
use PDO;

class MysqlSchema extends Schema
{
    protected static function readTableSchema(string $tableName, PDO $db): array
    {
        $stmt = $db->query('DESC ' . $tableName);
        $desc = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $schema = [];

        foreach ($desc as $field) {
            $schema[] = [
                'name' => $field['Field'],
                'type' => $field['Type'],
                'nullable' => $field['Null'] === 'YES',
                'primary' => $field['Key'] === 'PRI',
                'default' => $field['Default'],
                'auto_increment' => $field['Extra'] === 'auto_increment',
            ];
        }

        return $schema;
    }
}
