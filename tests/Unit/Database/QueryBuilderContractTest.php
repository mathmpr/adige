<?php

namespace Tests\Unit\Database;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use Adige\core\database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class QueryBuilderContractTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Connection::$connections = [];
        Schema::useMemoryCache();
        Schema::clearCache();

        $this->connection = $this->createConnectionMock();
    }

    protected function tearDown(): void
    {
        Connection::$connections = [];
        Schema::clearCache();

        parent::tearDown();
    }

    public function testPhase4QueryMethodsReturnTheSameInstance(): void
    {
        $query = QueryBuilderPost::find($this->connection);

        self::assertSame($query, $query->select(['id', 'title']));
        self::assertSame($query, $query->where([':tableName.user_id' => 10]));
        self::assertSame($query, $query->andWhere([':tableName.title' => 'Ada']));
        self::assertSame($query, $query->orWhere([':tableName.title' => 'Grace']));
        self::assertSame($query, $query->whereIn([':tableName.id' => [1, 2]]));
        self::assertSame($query, $query->innerJoin('comments', ':tableName.`:pkName` = comments.post_id'));
        self::assertSame($query, $query->leftJoin('comments', ':tableName.`:pkName` = comments.post_id'));
        self::assertSame($query, $query->rightJoin('comments', ':tableName.`:pkName` = comments.post_id'));
    }

    public function testSelectWhereJoinAndOrderBuildPredictableSqlAndParams(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id', 'title'])
            ->innerJoin('comments', ':tableName.`:pkName` = comments.post_id')
            ->where([':tableName.user_id' => 10])
            ->andWhere([':tableName.title' => 'Ada'])
            ->orWhereIn([':tableName.id' => [1, 2]])
            ->orderByAsc('title')
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id, posts.title FROM posts  INNER JOIN comments ON posts.`id` = comments.post_id ' .
            'WHERE posts.user_id=:arg0 AND (posts.title=:arg1 OR (posts.id IN (:arg2, :arg3))) ORDER BY title ASC',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'Ada',
            'arg2' => 1,
            'arg3' => 2,
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testWhereAndWhereOrWhereAcceptBothEqualityAndIndexedComparisonFormats(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([':tableName.user_id' => 10])
            ->andWhere([':tableName.id', '>', 1])
            ->orWhere([':tableName.id', '<', 99])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE posts.user_id=:arg0 AND (posts.id>:arg1 OR (posts.id<:arg2))',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 1,
            'arg2' => 99,
        ], $this->getQueryBuilder($query)->getParams());
    }

    private function createConnectionMock(): Connection
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn(Connection::TYPE_MYSQL);
        $pdo->method('query')
            ->willReturnCallback(function (string $sql): PDOStatement {
                return match ($sql) {
                    'DESC posts' => $this->createStatement([
                        [
                            'Field' => 'id',
                            'Type' => 'int',
                            'Null' => 'NO',
                            'Key' => 'PRI',
                            'Default' => null,
                            'Extra' => 'auto_increment',
                        ],
                        [
                            'Field' => 'user_id',
                            'Type' => 'int',
                            'Null' => 'NO',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                        [
                            'Field' => 'title',
                            'Type' => 'varchar(255)',
                            'Null' => 'YES',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                    ]),
                    default => throw new \RuntimeException('Unexpected schema query: ' . $sql),
                };
            });

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDb'])
            ->getMock();

        $connection->method('getDb')->willReturn($pdo);

        return $connection;
    }

    private function createStatement(array $rows): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        return $statement;
    }

    private function getQueryBuilder(ActiveRecord $model): \Adige\core\database\QueryBuilder
    {
        $reflection = new \ReflectionClass(ActiveRecord::class);
        $property = $reflection->getProperty('queryBuilder');
        $property->setAccessible(true);

        return $property->getValue($model);
    }
}

class QueryBuilderPost extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'posts';
    }
}
