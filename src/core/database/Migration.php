<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use Adige\core\database\dialects\mysql\MysqlMigration;
use Adige\core\database\dialects\sqlite\SqliteMigration;
use PDO;
use Throwable;

abstract class Migration extends BaseObject
{
    public const MIGRATIONS_TABLE = 'migrations';

    protected ?Connection $connection = null;

    protected ?MigrationDialect $dialect = null;

    abstract public function up(): void;

    abstract public function down(): void;

    public function __construct(
        ?Connection $connection = null,
    )
    {
        if ($connection !== null) {
            $this->setConnection($connection);
        }
        parent::__construct();
    }

    public function addColumn(string $table, MigrationField $field): self
    {
        if (!$this->dialect()->tableExists($table)) {
            throw new \RuntimeException("Table '$table' does not exist.");
        }

        if ($this->dialect()->fieldExists($table, $field->getName())) {
            throw new \RuntimeException("Field '{$field->getName()}' already exists on table '$table'.");
        }

        $this->dialect()->addColumn($table, $field);
        return $this;
    }

    public function createTable(string $table, array $fields = []): self
    {
        if ($this->dialect()->tableExists($table)) {
            throw new \RuntimeException("Table '$table' already exists.");
        }

        if ($fields === []) {
            throw new \RuntimeException("Table '$table' must define at least one field.");
        }

        $indexes = [];

        foreach ($fields as $field) {
            if ($field instanceof MigrationIndex) {
                $indexes[] = $field;
                continue;
            }

            if (!$field instanceof MigrationField) {
                throw new \InvalidArgumentException('createTable() expects MigrationField or MigrationIndex instances.');
            }
        }

        $columnFields = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => $field instanceof MigrationField
        ));

        $this->dialect()->createTable($table, $columnFields, $indexes);
        return $this;
    }

    public function dropField(string $table, string $field): self
    {
        if (!$this->dialect()->tableExists($table)) {
            throw new \RuntimeException("Table '$table' does not exist.");
        }

        if (!$this->dialect()->fieldExists($table, $field)) {
            throw new \RuntimeException("Field '$field' does not exist on table '$table'.");
        }

        $this->dialect()->dropColumn($table, $field);
        return $this;
    }

    public function dropTable(string $table): self
    {
        if (!$this->dialect()->tableExists($table)) {
            throw new \RuntimeException("Table '$table' does not exist.");
        }

        $this->dialect()->dropTable($table);
        return $this;
    }

    public function field(string $name): MigrationField
    {
        return new MigrationField($name);
    }

    public function index(array|string $columns, ?string $name = null): MigrationIndex
    {
        return new MigrationIndex($columns, $name);
    }

    public function executeUp(): self
    {
        return $this->runInTransaction(fn () => $this->up());
    }

    public function executeDown(): self
    {
        return $this->runInTransaction(fn () => $this->down());
    }

    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;
        $this->dialect = self::createDialectForConnection($connection);
        return $this;
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    private function dialect(): MigrationDialect
    {
        if ($this->dialect === null || $this->connection === null) {
            throw new \RuntimeException('Migration connection is not configured.');
        }

        return $this->dialect;
    }

    private function runInTransaction(callable $callback): self
    {
        $this->ensureMigrationsTable();
        $this->connection()?->beginTransaction();

        try {
            $callback();
            $this->connection()?->commitTransaction();
        } catch (Throwable $throwable) {
            $this->connection()?->rollBackTransaction();
            throw $throwable;
        }

        return $this;
    }

    private function ensureMigrationsTable(): void
    {
        $connection = $this->connection();
        self::ensureMetadataTableFor($connection);
        if ($connection->getDb()?->inTransaction()) {
            $connection->commitTransaction();
        }
    }

    private function connection(): Connection
    {
        if ($this->connection === null) {
            throw new \RuntimeException('Migration connection is not configured.');
        }

        return $this->connection;
    }

    public static function ensureMetadataTableFor(Connection $connection): void
    {
        $dialect = self::createDialectForConnection($connection);
        if (!$dialect->tableExists(self::MIGRATIONS_TABLE)) {
            $dialect->createTable(self::MIGRATIONS_TABLE, [
                (new MigrationField('id'))->integer()->autoIncrement(),
                (new MigrationField('name'))->string(255)->notNull()->unique(),
                (new MigrationField('batch'))->integer()->notNull()->default(0),
                (new MigrationField('created_at'))->timestamp()->nullable(),
            ]);
            return;
        }

        if (!$dialect->fieldExists(self::MIGRATIONS_TABLE, 'batch')) {
            $dialect->addColumn(
                self::MIGRATIONS_TABLE,
                (new MigrationField('batch'))->integer()->notNull()->default(0)
            );
        }
    }

    public static function createDialectForConnection(Connection $connection): MigrationDialect
    {
        $driver = $connection->getDb()->getAttribute(PDO::ATTR_DRIVER_NAME);
        return match ($driver) {
            'mysql' => new MysqlMigration($connection),
            'sqlite' => new SqliteMigration($connection),
            default => throw new \RuntimeException("Unsupported database driver: $driver"),
        };
    }
}
