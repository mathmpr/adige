<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use PDO;

class Schema extends BaseObject
{
    public static array $schema = [];
    private static string $path = './schema.json';

    public function generateSchema(string $path = './schema.json'): void
    {
        self::$path = $path;
        self::read();
    }

    public static function getSchema(string $tableName, PDO $db): array
    {
        (new Schema)->init();
        if (array_key_exists($tableName, self::$schema)) {
            return self::$schema[$tableName];
        }
        $stmt = $db->query('DESC ' . $tableName);
        $desc = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $better = [];
        foreach ($desc as $field) {
            $better[] = [
                'name'      => $field['Field'],
                'type'      => $field['Type'],
                'nullable'  => $field['Null'] === 'YES',
                'primary'   => $field['Key'] === 'PRI',
                'default'   => $field['Default'],
                'auto_increment' => $field['Extra'] === 'auto_increment',
            ];
        }
        self::$schema[$tableName] = $better;
        self::persist();
        return $better;
    }

    public static function pkName(string $tableName, PDO $db): ?string
    {
        $schema = Schema::getSchema($tableName, $db);
        foreach ($schema as $field) {
            if ($field['primary']) {
                return $field['name'];
            }
        }
        return null;
    }

    public static function getFields(string $tableName, PDO $db): ?array
    {
        return array_column(Schema::getSchema($tableName, $db), 'name');
    }

    private static function persist(): void
    {
        file_put_contents(self::$path, json_encode(self::$schema));
    }

    private static function read(): void
    {
        if (!file_exists(self::$path)) {
            file_put_contents(self::$path, json_encode([]));
        }
        self::$schema = json_decode(file_get_contents(self::$path), true);
    }

}