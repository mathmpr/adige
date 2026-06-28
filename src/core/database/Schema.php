<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use Adige\core\database\dialects\mysql\MysqlSchema;
use Adige\core\database\dialects\sqlite\SqliteSchema;
use Adige\core\database\exceptions\UnsupportedDatabaseDriverException;
use Adige\core\database\schema\FileSchemaCacheStore;
use Adige\core\database\schema\MemorySchemaCacheStore;
use Adige\core\database\schema\SchemaCacheStore;
use PDO;

abstract class Schema extends BaseObject
{
    public const CACHE_MEMORY = 'memory';
    public const CACHE_FILE = 'file';

    public static array $schema = [];
    protected static string $path = './schema.json';
    protected static string $cacheMode = self::CACHE_FILE;
    protected static bool $cacheLoaded = false;
    protected static ?SchemaCacheStore $cacheStore = null;

    public function generateSchema(string $path = './schema.json'): void
    {
        static::useFileCache($path);
    }

    public static function configure(array $config): void
    {
        $cache = $config['cache'] ?? null;

        if ($cache instanceof SchemaCacheStore) {
            static::useCacheStore($cache, $config['mode'] ?? 'custom');
            return;
        }

        if (is_string($cache)) {
            match ($cache) {
                self::CACHE_MEMORY => static::useMemoryCache(),
                self::CACHE_FILE => static::useFileCache($config['path'] ?? static::$path),
                default => null,
            };
            return;
        }

        if (!is_array($cache)) {
            return;
        }

        if (($cache['store'] ?? null) instanceof SchemaCacheStore) {
            static::useCacheStore($cache['store'], $cache['mode'] ?? 'custom');
            return;
        }

        match ($cache['driver'] ?? null) {
            self::CACHE_MEMORY => static::useMemoryCache(),
            self::CACHE_FILE => static::useFileCache($cache['path'] ?? static::$path),
            default => null,
        };
    }

    public static function getSchema(string $tableName, PDO $db): array
    {
        return static::createDialectSchema($db)::resolveSchema($tableName, $db);
    }

    public static function pkName(string $tableName, PDO $db): ?string
    {
        foreach (static::getSchema($tableName, $db) as $field) {
            if ($field['primary']) {
                return $field['name'];
            }
        }
        return null;
    }

    public static function getFields(string $tableName, PDO $db): ?array
    {
        return array_column(static::getSchema($tableName, $db), 'name');
    }

    protected static function resolveSchema(string $tableName, PDO $db): array
    {
        static::bootstrapSchema();

        if (array_key_exists($tableName, static::$schema)) {
            return static::$schema[$tableName];
        }

        $schema = static::readTableSchema($tableName, $db);
        static::$schema[$tableName] = $schema;
        static::persist();

        return $schema;
    }

    protected static function bootstrapSchema(): void
    {
        (new static())->init();
        static::loadConfiguredCache();
    }

    abstract protected static function readTableSchema(string $tableName, PDO $db): array;

    public static function useMemoryCache(): void
    {
        static::$cacheMode = self::CACHE_MEMORY;
        static::$cacheStore = new MemorySchemaCacheStore();
        static::$cacheLoaded = false;
    }

    public static function useFileCache(string $path = './schema.json'): void
    {
        static::$cacheMode = self::CACHE_FILE;
        static::$path = $path;
        static::$cacheStore = new FileSchemaCacheStore($path);
        static::$cacheLoaded = false;
        static::loadConfiguredCache();
    }

    public static function useCacheStore(SchemaCacheStore $cacheStore, string $mode = 'custom'): void
    {
        static::$cacheMode = $mode;
        static::$cacheStore = $cacheStore;
        static::$cacheLoaded = false;
        static::loadConfiguredCache();
    }

    public static function getCacheStore(): SchemaCacheStore
    {
        if (static::$cacheStore === null) {
            static::$cacheStore = new FileSchemaCacheStore(static::$path);
        }

        return static::$cacheStore;
    }

    public static function getCacheMode(): string
    {
        return static::$cacheMode;
    }

    public static function getCachePath(): string
    {
        return static::$path;
    }

    public static function clearCache(?string $tableName = null): void
    {
        if ($tableName === null) {
            static::$schema = [];
            static::$cacheLoaded = false;
            return;
        }

        unset(static::$schema[$tableName]);
    }

    public static function refreshSchema(string $tableName, PDO $db, bool $persist = false): array
    {
        $dialect = static::createDialectSchema($db);
        $dialect::bootstrapSchema();

        $schema = $dialect::readTableSchema($tableName, $db);
        $dialect::$schema[$tableName] = $schema;

        if ($persist) {
            $dialect::persist();
        }

        return $schema;
    }

    public static function refreshAll(array $tableNames, PDO $db, bool $persist = false): array
    {
        $dialect = static::createDialectSchema($db);
        $schemas = [];

        foreach ($tableNames as $tableName) {
            $schemas[$tableName] = $dialect::refreshSchema($tableName, $db, false);
        }

        if ($persist) {
            $dialect::persist();
        }

        return $schemas;
    }

    public static function saveCache(): void
    {
        static::persist();
    }

    protected static function createDialectSchema(PDO $db): string
    {
        return match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            Connection::TYPE_MYSQL => MysqlSchema::class,
            Connection::TYPE_SQLITE => SqliteSchema::class,
            default => throw new UnsupportedDatabaseDriverException($db->getAttribute(PDO::ATTR_DRIVER_NAME)),
        };
    }

    protected static function persist(): void
    {
        static::getCacheStore()->save(static::$schema);
    }

    protected static function read(): void
    {
        static::$schema = static::getCacheStore()->load();
        static::$cacheLoaded = true;
    }

    protected static function loadConfiguredCache(): void
    {
        if (static::$cacheLoaded) {
            return;
        }

        static::read();
    }
}
