<?php

namespace Tests\Unit\Database;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use Adige\core\database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
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
        self::assertSame($query, $query->andWhere(['OR', [':tableName.title' => 'Ada'], [':tableName.title' => 'Grace']]));
        self::assertSame($query, $query->orWhere([':tableName.id', 'NOT IN', [1, 2]]));
        self::assertSame($query, $query->innerJoin('comments', ':tableName.`:pkName` = comments.post_id'));
        self::assertSame($query, $query->leftJoin('comments', ':tableName.`:pkName` = comments.post_id'));
        self::assertSame($query, $query->rightJoin('comments', ':tableName.`:pkName` = comments.post_id'));
    }

    public function testSelectWhereJoinAndOrderBuildPredictableSqlAndParams(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id', 'title'])
            ->innerJoin('comments', ':tableName.`:pkName` = comments.post_id')
            ->where([
                'AND',
                [':tableName.user_id' => 10],
                [':tableName.title', 'NOT IN', ['Ada', 'Grace']],
            ])
            ->orderByAsc('title')
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id, posts.title FROM posts  INNER JOIN comments ON posts.`id` = comments.post_id ' .
            'WHERE (posts.user_id=:arg0 AND posts.title NOT IN (:arg1, :arg2)) ORDER BY title ASC',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'Ada',
            'arg2' => 'Grace',
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testNestedWhereGroupsCompileByArrayDepth(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([
                'AND',
                ['id' => 10],
                ['title', 'NOT IN', ['Ada', 'Grace']],
                ['OR',
                    ['deleted_at', '!=', null],
                    ['score', '>', 100],
                ],
            ])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE (posts.id=:arg0 AND posts.title NOT IN (:arg1, :arg2) ' .
            'AND (posts.deleted_at IS NOT NULL OR posts.score>:arg3))',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'Ada',
            'arg2' => 'Grace',
            'arg3' => 100,
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testDeeplyNestedWhereGroupsSupportMultipleLevelsAndOperators(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([
                'AND',
                ['id' => 10],
                ['OR',
                    ['deleted_at', '=', null],
                    ['AND',
                        ['status', 'IN', ['open', 'pending']],
                        ['OR',
                            ['score', '>=', 100],
                            ['score', '<', 0],
                        ],
                    ],
                ],
                ['title', 'NOT IN', ['Ada', 'Grace']],
            ])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE (posts.id=:arg0 AND (posts.deleted_at IS NULL OR ' .
            '(posts.status IN (:arg1, :arg2) AND (posts.score>=:arg3 OR posts.score<:arg4))) ' .
            'AND posts.title NOT IN (:arg5, :arg6))',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'open',
            'arg2' => 'pending',
            'arg3' => 100,
            'arg4' => 0,
            'arg5' => 'Ada',
            'arg6' => 'Grace',
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testRootWhereCallsComposeNewGroupsAcrossMethods(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where(['id' => 10])
            ->andWhere(['OR', ['title' => 'Ada'], ['title' => 'Grace']])
            ->orWhere(['status', '=', 'archived'])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE ((posts.id=:arg0 AND (posts.title=:arg1 OR posts.title=:arg2)) OR posts.status=:arg3)',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'Ada',
            'arg2' => 'Grace',
            'arg3' => 'archived',
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testRootCompositionKeepsNestedGroupsIsolatedAcrossCalls(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([
                'OR',
                ['status' => 'open'],
                ['AND',
                    ['score', '>', 50],
                    ['published_at', '!=', null],
                ],
            ])
            ->andWhere([
                'AND',
                ['title', 'LIKE', '%vip%'],
                ['OR',
                    ['deleted_at', '=', null],
                    ['deleted_at', '>', '2026-01-01 00:00:00'],
                ],
            ])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE ((posts.status=:arg0 OR (posts.score>:arg1 AND posts.published_at IS NOT NULL)) ' .
            'AND (posts.titleLIKE:arg2 AND (posts.deleted_at IS NULL OR posts.deleted_at>:arg3)))',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 'open',
            'arg1' => 50,
            'arg2' => '%vip%',
            'arg3' => '2026-01-01 00:00:00',
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testNullComparisonsBecomeIsNullAndIsNotNull(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([
                'OR',
                ['deleted_at', '=', null],
                ['published_at', '!=', null],
            ])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE (posts.deleted_at IS NULL OR posts.published_at IS NOT NULL)',
            $query->getRawSql()
        );
        self::assertSame([], $this->getQueryBuilder($query)->getParams());
    }

    public function testEqualityMapWithMultipleFieldsCompilesAsAndGroup(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->where([
                'id' => 10,
                'status' => 'open',
            ])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts   WHERE (posts.id=:arg0 AND posts.status=:arg1)',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'open',
        ], $this->getQueryBuilder($query)->getParams());
    }

    public function testWhereQualifiesSimpleFieldNamesWithRootTable(): void
    {
        $query = QueryBuilderPost::find($this->connection)
            ->select(['id'])
            ->innerJoin('comments', ':tableName.`:pkName` = comments.post_id')
            ->where(['id' => 10])
            ->andWhere(['title', '!=', 'Ada'])
            ->build($this->connection);

        self::assertSame(
            'SELECT  posts.id FROM posts  INNER JOIN comments ON posts.`id` = comments.post_id ' .
            'WHERE (posts.id=:arg0 AND posts.title!=:arg1)',
            $query->getRawSql()
        );
        self::assertSame([
            'arg0' => 10,
            'arg1' => 'Ada',
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
                        [
                            'Field' => 'status',
                            'Type' => 'varchar(255)',
                            'Null' => 'YES',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                        [
                            'Field' => 'deleted_at',
                            'Type' => 'datetime',
                            'Null' => 'YES',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                        [
                            'Field' => 'published_at',
                            'Type' => 'datetime',
                            'Null' => 'YES',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                        [
                            'Field' => 'score',
                            'Type' => 'int',
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
