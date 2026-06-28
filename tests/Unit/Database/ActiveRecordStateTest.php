<?php

namespace Tests\Unit\Database;

use Adige\core\collection\Collection;
use Adige\core\BaseException;
use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use Adige\core\database\RelationDefinition;
use Adige\core\database\Schema;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class ActiveRecordStateTest extends TestCase
{
    private Connection $connection;

    private array $selectQueries = [];

    private array $insertQueries = [];

    private array $updateQueries = [];

    private array $deleteQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        Connection::$connections = [];
        Schema::useMemoryCache();
        Schema::clearCache();
        $this->selectQueries = [];
        $this->insertQueries = [];
        $this->updateQueries = [];
        $this->deleteQueries = [];

        $this->connection = $this->createConnectionMock();
    }

    protected function tearDown(): void
    {
        Connection::$connections = [];
        Schema::clearCache();

        parent::tearDown();
    }

    public function testHydratedRecordsKeepLoadedStateSeparateFromNewAndChangedAttributes(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        self::assertSame(['id' => 1, 'user_id' => 10, 'title' => 'Ada'], $post->getOldAttributes());
        self::assertSame([], $post->getChangedAttributes());
        self::assertFalse($post->isDirty());
    }

    public function testNewRecordsTrackNewAttributesUntilTheyArePersisted(): void
    {
        $post = new OrmStatePost(['user_id' => 22, 'title' => 'Grace'], [], $this->connection);

        self::assertSame([], $post->getOldAttributes());
        self::assertSame(['user_id' => 22, 'title' => 'Grace'], $post->getChangedAttributes());
        self::assertTrue($post->isDirty());

        self::assertTrue($post->save($this->connection));
        self::assertFalse($post->isDirty());
        self::assertSame($post->getAttributes()['id'], $post->getOldAttributes()['id']);
        self::assertArrayHasKey('id', $post->getOldAttributes());
        self::assertSame('Grace', $post->getOldAttributes()['title']);
    }

    public function testConstructionCanDeferSchemaResolutionUntilSaveTime(): void
    {
        $post = new OrmStatePost([
            'user_id' => 22,
            'title' => 'Grace',
            'label' => 'draft',
        ]);

        self::assertSame('Grace', $post->title);
        self::assertSame('draft', $post->label);
        self::assertSame([], $post->getOldAttributes());

        self::assertTrue($post->save($this->connection));
        self::assertCount(1, $this->insertQueries);
        self::assertStringContainsString('INSERT INTO posts', $this->insertQueries[0]['sql']);
        self::assertSame([
            'arg0' => 22,
            'arg1' => 'Grace',
        ], $this->insertQueries[0]['params']);
        self::assertSame('draft', $post->label);
        self::assertSame([
            'user_id' => 22,
            'title' => 'Grace',
            'id' => 2,
        ], $post->getOldAttributes());
    }

    public function testChangingLoadedAttributesMarksOnlyChangedFieldsAndCanRevertToCleanState(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        $post->title = 'Grace';

        self::assertSame(['title' => 'Grace'], $post->getChangedAttributes());
        self::assertTrue($post->isDirty());

        $post->title = 'Ada';

        self::assertSame([], $post->getChangedAttributes());
        self::assertFalse($post->isDirty());
    }

    public function testSaveUpdatePersistsOnlyDirtyFieldsAndRefreshesOldAttributes(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);
        $post->title = 'Grace';

        self::assertTrue($post->save($this->connection));
        self::assertCount(1, $this->updateQueries);
        self::assertStringContainsString('UPDATE posts', $this->updateQueries[0]['sql']);
        self::assertStringContainsString('SET title=:arg0', $this->updateQueries[0]['sql']);
        self::assertSame([
            'arg0' => 'Grace',
            'arg1' => 1,
        ], $this->updateQueries[0]['params']);
        self::assertSame([
            'id' => 1,
            'user_id' => 10,
            'title' => 'Grace',
        ], $post->getOldAttributes());
        self::assertFalse($post->isDirty());
    }

    public function testRemoveBuildsDeleteAgainstPrimaryKey(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        self::assertTrue($post->remove($this->connection));
        self::assertCount(1, $this->deleteQueries);
        self::assertStringContainsString('DELETE FROM posts', $this->deleteQueries[0]['sql']);
        self::assertSame([
            'arg0' => 1,
        ], $this->deleteQueries[0]['params']);
    }

    public function testLazyLoadedRelationsStayOutOfPersistedDirtyTrackingAndSerializeToArray(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        $comments = $post->comments;

        self::assertInstanceOf(Collection::class, $comments);
        self::assertCount(1, $comments);
        self::assertArrayHasKey('comments', $post->getRelations());
        self::assertSame([], $post->getChangedAttributes());
        self::assertFalse($post->isDirty());
        self::assertSame('first', $post->toArray()['comments'][0]['body']);
        self::assertFalse($post->isDirty());
    }

    public function testEagerLoadedRelationsAlsoStayOutOfPersistedDirtyTracking(): void
    {
        $post = OrmStatePost::find($this->connection)
            ->select(['*'])
            ->with(['comments'])
            ->where([
                ':tableName.`:pkName`' => 1
            ])
            ->one($this->connection);

        self::assertNotNull($post);
        self::assertArrayHasKey('comments', $post->getRelations());
        self::assertSame(['id' => 1, 'user_id' => 10, 'title' => 'Ada'], $post->getOldAttributes());
        self::assertSame([], $post->getChangedAttributes());
        self::assertFalse($post->isDirty());
        self::assertSame('first', $post->toArray()['comments'][0]['body']);
    }

    public function testToArrayUsesDefaultFieldsWithoutLazyLoadingRelationsOrExtras(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);
        $post->label = 'visible only on object state';

        self::assertSame([
            'id' => 1,
            'user_id' => 10,
            'title' => 'Ada',
        ], $post->toArray());
    }

    public function testToArraySupportsFieldsAliasesStringResolversAndCallables(): void
    {
        $post = new OrmStatePostWithCustomFields([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);
        $post->comments;

        self::assertSame([
            'id' => 1,
            'headline' => 'Ada',
            'comments' => [
                [
                    'id' => 1,
                    'post_id' => 1,
                    'body' => 'first',
                ],
            ],
            'comment_count' => 1,
        ], $post->toArray());
    }

    public function testToArrayThrowsWhenFieldsUsesCallableWithNumericKey(): void
    {
        $post = new OrmStatePostWithInvalidFields([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        $this->expectException(BaseException::class);
        $this->expectExceptionMessage('Numeric fields() entries must contain a string field name');

        $post->toArray();
    }

    public function testWithUsesSingleRelationQueryForCollectionEagerLoad(): void
    {
        $posts = OrmStatePost::find($this->connection)
            ->select(['*'])
            ->with(['comments'])
            ->all($this->connection);

        self::assertCount(2, $posts);
        self::assertCount(1, $posts[0]->comments);
        self::assertCount(1, $posts[1]->comments);
        self::assertCount(1, array_filter(
            $this->selectQueries,
            static fn (string $sql): bool => str_contains($sql, 'FROM comments')
        ));
        self::assertTrue((bool) array_filter(
            $this->selectQueries,
            static fn (string $sql): bool => str_contains($sql, 'FROM comments') && str_contains($sql, ' IN ')
        ));
    }

    public function testWithSupportsNestedRelationPathsForCollectionEagerLoad(): void
    {
        $posts = OrmStatePost::find($this->connection)
            ->select(['*'])
            ->with(['comments.post'])
            ->all($this->connection);

        self::assertCount(2, $posts);
        self::assertCount(1, $posts[0]->comments);
        self::assertArrayHasKey('post', $posts[0]->comments[0]->getRelations());
        self::assertSame(1, $posts[0]->comments[0]->post->id);
        self::assertSame('Ada', $posts[0]->comments[0]->post->title);
        self::assertArrayHasKey('post', $posts[1]->comments[0]->getRelations());
        self::assertSame(2, $posts[1]->comments[0]->post->id);
        self::assertSame('Grace', $posts[1]->comments[0]->post->title);
        self::assertCount(1, array_filter(
            $this->selectQueries,
            static fn (string $sql): bool => str_contains($sql, 'FROM comments')
        ));
        self::assertCount(2, array_filter(
            $this->selectQueries,
            static fn (string $sql): bool => str_contains($sql, 'FROM posts')
        ));
        self::assertTrue((bool) array_filter(
            $this->selectQueries,
            static fn (string $sql): bool => str_contains($sql, 'FROM posts') && str_contains($sql, ' IN ')
        ));
    }

    public function testAsArrayOneStillPopulatesEagerLoadedRelations(): void
    {
        $post = OrmStatePost::find($this->connection)
            ->select(['*'])
            ->with(['comments.post'])
            ->where([
                ':tableName.`:pkName`' => 1
            ])
            ->asArray()
            ->one($this->connection);

        self::assertNotNull($post);
        self::assertArrayHasKey('comments', $post->getRelations());
        self::assertCount(1, $post->comments);
        self::assertArrayHasKey('post', $post->comments[0]->getRelations());
        self::assertSame(1, $post->comments[0]->post->id);
    }

    public function testJoinWithHydratesHasManyRelationsFromSingleQuery(): void
    {
        $posts = OrmStatePost::find($this->connection)
            ->joinWith(['comments'])
            ->all($this->connection);

        self::assertCount(2, $posts);
        self::assertCount(1, $this->selectQueries);
        self::assertCount(1, $posts[0]->comments);
        self::assertSame('first', $posts[0]->comments[0]->body);
        self::assertCount(1, $posts[1]->comments);
        self::assertSame('second', $posts[1]->comments[0]->body);
    }

    public function testJoinWithSupportsNestedRelationsFromSingleQuery(): void
    {
        $posts = OrmStatePost::find($this->connection)
            ->joinWith(['comments.post'])
            ->all($this->connection);

        self::assertCount(2, $posts);
        self::assertCount(1, $this->selectQueries);
        self::assertCount(1, $posts[0]->comments);
        self::assertSame('Ada', $posts[0]->comments[0]->post->title);
        self::assertCount(1, $posts[1]->comments);
        self::assertSame('Grace', $posts[1]->comments[0]->post->title);
    }

    public function testJoinWithWhereCanReferenceJoinedTableName(): void
    {
        $post = OrmStatePost::find($this->connection)
            ->joinWith(['comments'])
            ->where([
                ':tableName.`:pkName`' => 1
            ])
            ->andWhere(['comments.id' => 1])
            ->one($this->connection);

        self::assertNotNull($post);
        self::assertCount(1, $this->selectQueries);
        self::assertStringContainsString('jr__comments.id=:arg1', $this->selectQueries[0]);
    }

    public function testJoinWithWhereCanReferenceNestedRelationPath(): void
    {
        $post = OrmStatePost::find($this->connection)
            ->joinWith(['comments.post'])
            ->where([
                ':tableName.`:pkName`' => 1
            ])
            ->andWhere(['comments.post.id' => 1])
            ->one($this->connection);

        self::assertNotNull($post);
        self::assertCount(1, $this->selectQueries);
        self::assertStringContainsString('jr__comments__post.id=:arg1', $this->selectQueries[0]);
    }

    public function testToArrayBreaksCyclesByReturningNullOnRepeatedActiveRecord(): void
    {
        $post = new OrmStatePost([], [], $this->connection);
        $post->hydrate(['id' => 1, 'user_id' => 10, 'title' => 'Ada']);

        $comment = new OrmStateComment([], [], $this->connection);
        $comment->hydrate(['id' => 1, 'post_id' => 1, 'body' => 'first']);

        $post->comments = Collection::factory([$comment]);
        $comment->post = $post;

        self::assertSame([
            'id' => 1,
            'user_id' => 10,
            'title' => 'Ada',
            'comments' => [
                [
                    'id' => 1,
                    'post_id' => 1,
                    'body' => 'first',
                    'post' => null,
                ],
            ],
        ], $post->toArray());
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
                    'DESC comments' => $this->createStatement([
                        [
                            'Field' => 'id',
                            'Type' => 'int',
                            'Null' => 'NO',
                            'Key' => 'PRI',
                            'Default' => null,
                            'Extra' => 'auto_increment',
                        ],
                        [
                            'Field' => 'post_id',
                            'Type' => 'int',
                            'Null' => 'NO',
                            'Key' => '',
                            'Default' => null,
                            'Extra' => '',
                        ],
                        [
                            'Field' => 'body',
                            'Type' => 'text',
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
            ->onlyMethods(['getDb', 'select', 'insert', 'update', 'delete'])
            ->getMock();

        $connection->method('getDb')->willReturn($pdo);
        $connection->method('select')->willReturnCallback(function (string $sql): array {
            $this->selectQueries[] = $sql;

            if (str_contains($sql, 'LEFT JOIN comments AS jr__comments')) {
                $rows = [];

                if (str_contains($sql, 'WHERE posts.`id`')) {
                    $rows[] = [
                        '__adg__root__id' => 1,
                        '__adg__root__user_id' => 10,
                        '__adg__root__title' => 'Ada',
                        '__adg__comments__id' => 1,
                        '__adg__comments__post_id' => 1,
                        '__adg__comments__body' => 'first',
                    ];

                    if (str_contains($sql, 'LEFT JOIN posts AS jr__comments__post')) {
                        $rows[0]['__adg__comments__post__id'] = 1;
                        $rows[0]['__adg__comments__post__user_id'] = 10;
                        $rows[0]['__adg__comments__post__title'] = 'Ada';
                    }

                    return $rows;
                }

                $rows[] = [
                    '__adg__root__id' => 1,
                    '__adg__root__user_id' => 10,
                    '__adg__root__title' => 'Ada',
                    '__adg__comments__id' => 1,
                    '__adg__comments__post_id' => 1,
                    '__adg__comments__body' => 'first',
                ];
                $rows[] = [
                    '__adg__root__id' => 2,
                    '__adg__root__user_id' => 11,
                    '__adg__root__title' => 'Grace',
                    '__adg__comments__id' => 2,
                    '__adg__comments__post_id' => 2,
                    '__adg__comments__body' => 'second',
                ];

                if (str_contains($sql, 'LEFT JOIN posts AS jr__comments__post')) {
                    $rows[0]['__adg__comments__post__id'] = 1;
                    $rows[0]['__adg__comments__post__user_id'] = 10;
                    $rows[0]['__adg__comments__post__title'] = 'Ada';
                    $rows[1]['__adg__comments__post__id'] = 2;
                    $rows[1]['__adg__comments__post__user_id'] = 11;
                    $rows[1]['__adg__comments__post__title'] = 'Grace';
                }

                return $rows;
            }

            if (str_contains($sql, 'FROM posts')) {
                if (str_contains($sql, 'WHERE posts.`id`')) {
                    return [[
                        'id' => 1,
                        'user_id' => 10,
                        'title' => 'Ada',
                    ]];
                }

                return [[
                    'id' => 1,
                    'user_id' => 10,
                    'title' => 'Ada',
                ], [
                    'id' => 2,
                    'user_id' => 11,
                    'title' => 'Grace',
                ]];
            }

            if (str_contains($sql, 'FROM comments')) {
                if (str_contains($sql, ' IN ')) {
                    return [[
                        'id' => 1,
                        'post_id' => 1,
                        'body' => 'first',
                    ], [
                        'id' => 2,
                        'post_id' => 2,
                        'body' => 'second',
                    ]];
                }

                return [[
                    'id' => 1,
                    'post_id' => 1,
                    'body' => 'first',
                ]];
            }

            throw new \RuntimeException('Unexpected select query: ' . $sql);
        });
        $connection->method('insert')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->insertQueries[] = [
                'sql' => $sql,
                'params' => $params,
            ];
            return 2;
        });
        $connection->method('update')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->updateQueries[] = [
                'sql' => $sql,
                'params' => $params,
            ];
            return 1;
        });
        $connection->method('delete')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->deleteQueries[] = [
                'sql' => $sql,
                'params' => $params,
            ];
            return 1;
        });

        return $connection;
    }

    private function createStatement(array $rows): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);

        return $statement;
    }
}

class OrmStatePost extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'posts';
    }

    public function comments(): RelationDefinition
    {
        return $this->hasManyRelation(OrmStateComment::class, 'post_id')
            ->select(['*']);
    }
}

class OrmStateComment extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'comments';
    }

    public function post(): RelationDefinition
    {
        return $this->hasOneRelation(OrmStatePost::class, 'id', 'post_id')
            ->select(['*']);
    }
}

class OrmStatePostWithCustomFields extends OrmStatePost
{
    public function fields(): array
    {
        return [
            'id',
            'headline' => 'title',
            'comments',
            'comment_count' => static fn (ActiveRecord $model): int => count($model->getRelations()['comments'] ?? []),
        ];
    }
}

class OrmStatePostWithInvalidFields extends OrmStatePost
{
    public function fields(): array
    {
        return [
            static fn (ActiveRecord $model): ?int => $model->id,
        ];
    }
}
