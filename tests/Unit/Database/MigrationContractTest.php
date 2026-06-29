<?php

namespace Tests\Unit\Database;

use Adige\core\database\Connection;
use Adige\core\database\Migration;
use Adige\core\database\MigrationField;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class MigrationContractTest extends TestCase
{
    public function testMigrationFieldSupportsFluentColumnDefinition(): void
    {
        $field = (new MigrationField('id'))
            ->integer()
            ->autoIncrement()
            ->unique()
            ->default(1);

        self::assertSame('id', $field->getName());
        self::assertSame('INTEGER', $field->getType());
        self::assertTrue($field->isPrimary());
        self::assertTrue($field->isAutoIncrement());
        self::assertFalse($field->isNullable());
        self::assertTrue($field->isUnique());
        self::assertTrue($field->hasDefault());
        self::assertSame(1, $field->getDefault());
    }

    public function testCreateTableBuildsSqliteCreateTableStatement(): void
    {
        $queries = [];
        $migration = new class($this->createConnectionDouble(Connection::TYPE_SQLITE, $queries)) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $migration->createTable('posts', [
            $migration->field('id')->integer()->autoIncrement(),
            $migration->field('title')->string(120)->notNull(),
            $migration->field('published')->boolean()->default(false),
            $migration->index('title'),
            $migration->index(['title', 'published'], 'posts_title_published_unique')->unique(),
        ]);

        self::assertSame([
            ["SELECT name FROM sqlite_master WHERE type='table' AND name = ?", ['posts']],
            ['CREATE TABLE "posts" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" VARCHAR(120) NOT NULL, "published" BOOLEAN DEFAULT 0)', []],
            ['CREATE INDEX "posts_title_idx" ON "posts" ("title")', []],
            ['CREATE UNIQUE INDEX "posts_title_published_unique" ON "posts" ("title", "published")', []],
        ], $queries);
    }

    public function testMigrationIndexSupportsFluentDefinition(): void
    {
        $migration = new class extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $index = $migration->index(['title', 'published'])
            ->name('posts_title_published_idx')
            ->unique();

        self::assertSame('posts_title_published_idx', $index->getName());
        self::assertSame(['title', 'published'], $index->getColumns());
        self::assertTrue($index->isUnique());
    }

    public function testAddColumnBuildsMysqlAlterTableStatement(): void
    {
        $queries = [];
        $migration = new class($this->createConnectionDouble(Connection::TYPE_MYSQL, $queries, [
            'SHOW TABLES LIKE ?' => 1,
            'SHOW COLUMNS FROM `posts` LIKE ?' => 0,
        ])) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $migration->addColumn(
            'posts',
            $migration->field('slug')->string(64)->notNull()->unique()->default('draft')
        );

        self::assertSame([
            ['SHOW TABLES LIKE ?', ['posts']],
            ['SHOW COLUMNS FROM `posts` LIKE ?', ['slug']],
            ["ALTER TABLE `posts` ADD COLUMN `slug` VARCHAR(64) NOT NULL DEFAULT 'draft' UNIQUE", []],
        ], $queries);
    }

    public function testDropFieldBuildsMysqlAlterTableDropColumnStatement(): void
    {
        $queries = [];
        $migration = new class($this->createConnectionDouble(Connection::TYPE_MYSQL, $queries, [
            'SHOW TABLES LIKE ?' => 1,
            'SHOW COLUMNS FROM `posts` LIKE ?' => 1,
        ])) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $migration->dropField('posts', 'slug');

        self::assertSame([
            ['SHOW TABLES LIKE ?', ['posts']],
            ['SHOW COLUMNS FROM `posts` LIKE ?', ['slug']],
            ['ALTER TABLE `posts` DROP COLUMN `slug`', []],
        ], $queries);
    }

    public function testDropTableBuildsSqliteDropTableStatement(): void
    {
        $queries = [];
        $migration = new class($this->createConnectionDouble(Connection::TYPE_SQLITE, $queries, [
            "SELECT name FROM sqlite_master WHERE type='table' AND name = ?" => 1,
        ])) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $migration->dropTable('posts');

        self::assertSame([
            ["SELECT name FROM sqlite_master WHERE type='table' AND name = ?", ['posts']],
            ['DROP TABLE "posts"', []],
        ], $queries);
    }

    public function testCreateTableThrowsWhenNoFieldsAreProvided(): void
    {
        $queries = [];
        $migration = new class($this->createConnectionDouble(Connection::TYPE_SQLITE, $queries)) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'posts' must define at least one field.");

        $migration->createTable('posts');
    }

    public function testExecuteUpCommitsTransaction(): void
    {
        $events = [];
        $queries = [];
        $migration = new class($this->createTransactionalConnectionDouble($events, $queries)) extends Migration {
            public array $calls = [];

            public function up(): void
            {
                $this->calls[] = 'up';
            }

            public function down(): void
            {
                $this->calls[] = 'down';
            }
        };

        $migration->executeUp();

        self::assertSame([
            ["SELECT name FROM sqlite_master WHERE type='table' AND name = ?", ['migrations']],
            ['CREATE TABLE "migrations" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(255) NOT NULL UNIQUE, "batch" INTEGER NOT NULL DEFAULT 0, "created_at" TIMESTAMP)', []],
        ], $queries);
        self::assertSame(['begin', 'commit'], $events);
        self::assertSame(['up'], $migration->calls);
    }

    public function testExecuteDownRollsBackTransactionOnFailure(): void
    {
        $events = [];
        $queries = [];
        $migration = new class($this->createTransactionalConnectionDouble($events, $queries)) extends Migration {
            public function up(): void
            {
            }

            public function down(): void
            {
                throw new RuntimeException('down failed');
            }
        };

        try {
            $migration->executeDown();
            self::fail('Expected executeDown() to throw');
        } catch (RuntimeException $exception) {
            self::assertSame('down failed', $exception->getMessage());
        }

        self::assertSame([
            ["SELECT name FROM sqlite_master WHERE type='table' AND name = ?", ['migrations']],
            ['CREATE TABLE "migrations" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "name" VARCHAR(255) NOT NULL UNIQUE, "batch" INTEGER NOT NULL DEFAULT 0, "created_at" TIMESTAMP)', []],
        ], $queries);
        self::assertSame(['begin', 'rollback'], $events);
    }

    public function testConstructorDoesNotBootstrapMigrationsTable(): void
    {
        $queries = [];

        new class($this->createConnectionDouble(Connection::TYPE_SQLITE, $queries)) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        self::assertSame([], $queries);
    }

    public function testConnectionMayBeInjectedAfterConstruction(): void
    {
        $queries = [];
        $migration = new class extends Migration {
            public function up(): void {}
            public function down(): void {}
        };

        self::assertNull($migration->getConnection());

        $migration->setConnection($this->createConnectionDouble(Connection::TYPE_SQLITE, $queries));
        $migration->createTable('posts', [
            $migration->field('id')->integer()->autoIncrement(),
        ]);

        self::assertInstanceOf(Connection::class, $migration->getConnection());
        self::assertSame([
            ["SELECT name FROM sqlite_master WHERE type='table' AND name = ?", ['posts']],
            ['CREATE TABLE "posts" ("id" INTEGER PRIMARY KEY AUTOINCREMENT)', []],
        ], $queries);
    }

    private function createConnectionDouble(string $driver, array &$queries, array $rowCounts = []): Connection
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn($driver);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDb', 'query'])
            ->getMock();

        $connection->method('getDb')->willReturn($pdo);
        $connection->method('query')
            ->willReturnCallback(function (string $sql, array $args = []) use (&$queries, $rowCounts): PDOStatement {
                $queries[] = [$sql, $args];
                return $this->createStatementWithRowCount($rowCounts[$sql] ?? 0);
            });

        return $connection;
    }

    private function createTransactionalConnectionDouble(array &$events, array &$queries, array $rowCounts = []): Connection
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn(Connection::TYPE_SQLITE);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDb', 'query', 'beginTransaction', 'commitTransaction', 'rollBackTransaction'])
            ->getMock();

        $connection->method('getDb')->willReturn($pdo);
        $connection->method('query')
            ->willReturnCallback(function (string $sql, array $args = []) use (&$queries, $rowCounts): PDOStatement {
                $queries[] = [$sql, $args];
                return $this->createStatementWithRowCount($rowCounts[$sql] ?? 0);
            });
        $connection->method('beginTransaction')->willReturnCallback(function () use (&$events, $connection): Connection {
            $events[] = 'begin';
            return $connection;
        });
        $connection->method('commitTransaction')->willReturnCallback(function () use (&$events, $connection): Connection {
            $events[] = 'commit';
            return $connection;
        });
        $connection->method('rollBackTransaction')->willReturnCallback(function () use (&$events, $connection): Connection {
            $events[] = 'rollback';
            return $connection;
        });

        return $connection;
    }

    private function createStatementWithRowCount(int $rowCount): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('rowCount')->willReturn($rowCount);
        return $statement;
    }
}
