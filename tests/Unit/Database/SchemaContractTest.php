<?php

namespace Tests\Unit\Database;

use Adige\core\database\Connection;
use Adige\core\database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SchemaContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::useMemoryCache();
        Schema::clearCache();
    }

    protected function tearDown(): void
    {
        Schema::clearCache();
        parent::tearDown();
    }

    public function testSchemaReadingReturnsFieldsAndPrimaryKeyFromMysqlDescription(): void
    {
        $pdo = $this->createMysqlSchemaPdo();

        self::assertSame([
            [
                'name' => 'id',
                'type' => 'int',
                'nullable' => false,
                'primary' => true,
                'default' => null,
                'auto_increment' => true,
            ],
            [
                'name' => 'title',
                'type' => 'varchar(255)',
                'nullable' => true,
                'primary' => false,
                'default' => null,
                'auto_increment' => false,
            ],
        ], Schema::getSchema('posts', $pdo));
        self::assertSame('id', Schema::pkName('posts', $pdo));
        self::assertSame(['id', 'title'], Schema::getFields('posts', $pdo));
    }

    public function testRefreshSchemaReplacesCachedMetadata(): void
    {
        $initial = $this->createMysqlSchemaPdo([
            [
                'Field' => 'id',
                'Type' => 'int',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ],
        ]);
        $refreshed = $this->createMysqlSchemaPdo([
            [
                'Field' => 'id',
                'Type' => 'int',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ],
            [
                'Field' => 'title',
                'Type' => 'varchar(255)',
                'Null' => 'YES',
                'Key' => '',
                'Default' => null,
                'Extra' => '',
            ],
        ]);

        self::assertSame(['id'], Schema::getFields('posts', $initial));
        self::assertSame([
            [
                'name' => 'id',
                'type' => 'int',
                'nullable' => false,
                'primary' => true,
                'default' => null,
                'auto_increment' => true,
            ],
            [
                'name' => 'title',
                'type' => 'varchar(255)',
                'nullable' => true,
                'primary' => false,
                'default' => null,
                'auto_increment' => false,
            ],
        ], Schema::refreshSchema('posts', $refreshed));
        self::assertSame(['id', 'title'], Schema::getFields('posts', $refreshed));
    }

    private function createMysqlSchemaPdo(?array $rows = null): PDO
    {
        $rows ??= [
            [
                'Field' => 'id',
                'Type' => 'int',
                'Null' => 'NO',
                'Key' => 'PRI',
                'Default' => null,
                'Extra' => 'auto_increment',
            ],
            [
                'Field' => 'title',
                'Type' => 'varchar(255)',
                'Null' => 'YES',
                'Key' => '',
                'Default' => null,
                'Extra' => '',
            ],
        ];

        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn(Connection::TYPE_MYSQL);
        $pdo->method('query')
            ->with('DESC posts')
            ->willReturn($statement);

        return $pdo;
    }
}
