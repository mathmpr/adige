<?php

namespace Tests\Unit\Database;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use Adige\core\database\Schema;
use Adige\core\database\validators\ValidatorInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class ActiveRecordValidationTest extends TestCase
{
    private array $insertQueries = [];

    private array $uniqueQueries = [];

    private bool $uniqueExists = false;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::$connections = [];
        Schema::useMemoryCache();
        Schema::clearCache();
        $this->insertQueries = [];
        $this->uniqueQueries = [];
        $this->uniqueExists = false;
        $this->connection = $this->createConnectionMock();
    }

    protected function tearDown(): void
    {
        Connection::$connections = [];
        Schema::clearCache();
        parent::tearDown();
    }

    public function testSaveReturnsFalseAndCollectsErrorsWhenBuiltInValidationFails(): void
    {
        $model = new ValidationPost([
            'age' => 'abc',
            'email' => '',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'age' => ["Field 'age' must be an integer."],
            'email' => ['Email is mandatory.'],
        ], $model->getErrors());
        self::assertSame([], $this->insertQueries);
    }

    public function testSaveCanSkipValidation(): void
    {
        $model = new ValidationPost([
            'age' => 'abc',
            'email' => '',
        ], [], $this->connection);

        self::assertTrue($model->save($this->connection, true));
        self::assertCount(1, $this->insertQueries);
    }

    public function testUniqueValidationBlocksDuplicateInsert(): void
    {
        $this->uniqueExists = true;
        $model = new ValidationPost([
            'age' => 20,
            'email' => 'ada@example.com',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'email' => ["Field 'email' must be unique."],
        ], $model->getErrors());
        self::assertStringContainsString('FROM rules_posts', $this->uniqueQueries[0]['sql']);
        self::assertStringContainsString('rules_posts.email=:arg0', $this->uniqueQueries[0]['sql']);
        self::assertSame('ada@example.com', $this->uniqueQueries[0]['params']['arg0']);
    }

    public function testUniqueValidationIgnoresCurrentRowOnUpdate(): void
    {
        $this->uniqueExists = false;
        $model = new ValidationPost([
            'id' => 5,
            'age' => 20,
            'email' => 'ada@example.com',
        ], [], $this->connection);
        $model->hydrate([
            'id' => 5,
            'age' => 20,
            'email' => 'ada@example.com',
        ]);
        $model->age = 21;

        self::assertTrue($model->save($this->connection));
        self::assertStringContainsString('rules_posts.email=:arg0', $this->uniqueQueries[0]['sql']);
        self::assertStringContainsString('rules_posts.`id`!=:arg1', $this->uniqueQueries[0]['sql']);
        self::assertSame([
            'arg0' => 'ada@example.com',
            'arg1' => 5,
        ], $this->uniqueQueries[0]['params']);
    }

    public function testCustomValidatorClassIsSupported(): void
    {
        $model = new ValidationPostWithCustomRule([
            'title' => 'tiny',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title must be at least 10 characters.'],
        ], $model->getErrors());
    }

    public function testUniqueValidationSupportsCompositeTargetAttribute(): void
    {
        $this->uniqueExists = true;
        $model = new ValidationPostWithCompositeUniqueRule([
            'date' => '2026-06-27',
            'app' => 'billing',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'date' => ['The combination of Date and App has already been taken.'],
            'app' => ['The combination of Date and App has already been taken.'],
        ], $model->getErrors());
        self::assertStringContainsString('rules_posts.date=:arg0', $this->uniqueQueries[0]['sql']);
        self::assertStringContainsString('rules_posts.app=:arg1', $this->uniqueQueries[0]['sql']);
        self::assertSame([
            'arg0' => '2026-06-27',
            'arg1' => 'billing',
        ], $this->uniqueQueries[0]['params']);
    }

    public function testStringValidatorSupportsMaxAndRejectsNonStringValues(): void
    {
        $model = new ValidationPostWithStringRules([
            'title' => str_repeat('a', 6),
            'app' => 123,
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title must be at most 5 characters.'],
            'app' => ['App label must be a string.'],
        ], $model->getErrors());
    }

    public function testMaxLengthValidatorSupportsNamedAndPositionalParameters(): void
    {
        $model = new ValidationPostWithMaxLengthRules([
            'title' => 'abcdef',
            'app' => 'toolong',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title exceeds the allowed length.'],
            'app' => ["Field 'app' must contain at most 4 characters."],
        ], $model->getErrors());
    }

    public function testMaskValidatorSupportsRegexRules(): void
    {
        $model = new ValidationPostWithMaskRule([
            'app' => 'Billing-App',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'app' => ['App slug must contain only lowercase letters and underscores.'],
        ], $model->getErrors());
    }

    public function testEmailValidatorRejectsInvalidAddresses(): void
    {
        $model = new ValidationPostWithEmailRule([
            'email' => 'not-an-email',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'email' => ['Please provide a valid e-mail address.'],
        ], $model->getErrors());
    }

    public function testBooleanValidatorSupportsLooseAndStrictModes(): void
    {
        $model = new ValidationPostWithBooleanRules([
            'app' => 'maybe',
            'title' => '1',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'app' => ['App flag must be boolean-like.'],
            'title' => ['Strict boolean accepts only real booleans.'],
        ], $model->getErrors());
    }

    public function testNumberValidatorSupportsRangeChecks(): void
    {
        $model = new ValidationPostWithNumberRules([
            'age' => '1.5',
            'title' => '11',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'age' => ['Age must be at least 2.5.'],
            'title' => ["Field 'title' must be less than or equal to 10."],
        ], $model->getErrors());
    }

    public function testMinLengthValidatorSupportsNamedAndPositionalParameters(): void
    {
        $model = new ValidationPostWithMinLengthRules([
            'title' => 'abc',
            'app' => 'xy',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title is too short.'],
            'app' => ["Field 'app' must contain at least 3 characters."],
        ], $model->getErrors());
    }

    public function testInValidatorSupportsAllowedAndDisallowedRanges(): void
    {
        $model = new ValidationPostWithInRules([
            'app' => 'desktop',
            'title' => 'draft',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'app' => ['App must be one of the supported channels.'],
            'title' => ['Title status is blocked.'],
        ], $model->getErrors());
    }

    public function testCompareValidatorSupportsCompareAttributeAndScalarOperators(): void
    {
        $model = new ValidationPostWithCompareRules([
            'title' => 'secret',
            'app' => 'another-secret',
            'age' => 17,
            'id' => 18,
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title must match app.'],
            'age' => ['Age must be at least 18.'],
        ], $model->getErrors());
    }

    public function testDateValidatorSupportsCustomFormat(): void
    {
        $model = new ValidationPostWithDateRules([
            'date' => '27/06/2026',
            'title' => '2026-02-30',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title date is invalid.'],
        ], $model->getErrors());
    }

    public function testUrlValidatorRejectsInvalidUrls(): void
    {
        $model = new ValidationPostWithUrlRule([
            'title' => 'example.local/path',
        ], [], $this->connection);

        self::assertFalse($model->save($this->connection));
        self::assertSame([
            'title' => ['Title must contain a valid URL.'],
        ], $model->getErrors());
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
                    'DESC rules_posts' => $this->createStatement([
                        ['Field' => 'id', 'Type' => 'int', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null, 'Extra' => 'auto_increment'],
                        ['Field' => 'age', 'Type' => 'int', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
                        ['Field' => 'email', 'Type' => 'varchar(255)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
                        ['Field' => 'title', 'Type' => 'varchar(255)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
                        ['Field' => 'date', 'Type' => 'date', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
                        ['Field' => 'app', 'Type' => 'varchar(255)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''],
                    ]),
                    default => throw new \RuntimeException('Unexpected PDO query: ' . $sql),
                };
            });

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDb', 'select', 'insert', 'update'])
            ->getMock();

        $connection->method('getDb')->willReturn($pdo);
        $connection->method('select')->willReturnCallback(function (string $sql, array $params = []): array {
            if (str_contains($sql, 'FROM rules_posts')) {
                $this->uniqueQueries[] = [
                    'sql' => $sql,
                    'params' => $params,
                ];

                return $this->uniqueExists
                    ? [['id' => 99, 'email' => 'ada@example.com']]
                    : [];
            }

            throw new \RuntimeException('Unexpected select query: ' . $sql);
        });
        $connection->method('insert')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->insertQueries[] = [
                'sql' => $sql,
                'params' => $params,
            ];
            return 10;
        });
        $connection->method('update')->willReturn(1);

        return $connection;
    }

    private function createStatement(array $rows): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);
        return $statement;
    }
}

class ValidationPost extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['age'], 'integer'],
            [['email'], 'required', 'message' => 'Email is mandatory.'],
            [['email'], 'unique'],
        ];
    }
}

class ValidationPostWithCustomRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], MinimumLengthValidator::class, 10],
        ];
    }
}

class ValidationPostWithCompositeUniqueRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['date', 'app'], 'unique', 'targetAttribute' => ['date', 'app'], 'message' => 'The combination of Date and App has already been taken.'],
        ];
    }
}

class ValidationPostWithStringRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], 'string', 'max' => 5, 'message' => 'Title must be at most 5 characters.'],
            [['app'], 'string', 'message' => 'App label must be a string.'],
        ];
    }
}

class ValidationPostWithMaxLengthRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], 'maxLength', 'max' => 5, 'message' => 'Title exceeds the allowed length.'],
            [['app'], 'maxLength', 4],
        ];
    }
}

class ValidationPostWithMaskRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['app'], 'mask', 'pattern' => '/^[a-z_]+$/', 'message' => 'App slug must contain only lowercase letters and underscores.'],
        ];
    }
}

class ValidationPostWithEmailRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['email'], 'email', 'message' => 'Please provide a valid e-mail address.'],
        ];
    }
}

class ValidationPostWithBooleanRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['app'], 'boolean', 'message' => 'App flag must be boolean-like.'],
            [['title'], 'boolean', 'strict' => true, 'message' => 'Strict boolean accepts only real booleans.'],
        ];
    }
}

class ValidationPostWithNumberRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['age'], 'number', 'min' => 2.5, 'message' => 'Age must be at least 2.5.'],
            [['title'], 'number', 0, 10],
        ];
    }
}

class ValidationPostWithMinLengthRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], 'minLength', 'min' => 4, 'message' => 'Title is too short.'],
            [['app'], 'minLength', 3],
        ];
    }
}

class ValidationPostWithInRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['app'], 'in', 'range' => ['web', 'mobile'], 'message' => 'App must be one of the supported channels.'],
            [['title'], 'in', ['draft', 'archived'], 'not' => true, 'message' => 'Title status is blocked.'],
        ];
    }
}

class ValidationPostWithCompareRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], 'compare', 'compareAttribute' => 'app', 'message' => 'Title must match app.'],
            [['age'], 'compare', 'compareValue' => 18, 'operator' => '>=', 'message' => 'Age must be at least 18.'],
            [['id'], 'compare', 'compareValue' => 18, 'operator' => '>='],
        ];
    }
}

class ValidationPostWithDateRules extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['date'], 'date', 'format' => 'd/m/Y'],
            [['title'], 'date', 'message' => 'Title date is invalid.'],
        ];
    }
}

class ValidationPostWithUrlRule extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rules_posts';
    }

    public function rules(): array
    {
        return [
            [['title'], 'url', 'message' => 'Title must contain a valid URL.'],
        ];
    }
}

class MinimumLengthValidator implements ValidatorInterface
{
    public function validate(ActiveRecord $model, array $fields, array $params = [], ?Connection $connection = null): void
    {
        $minimum = (int) (($params['args'][0] ?? 0));

        foreach ($fields as $field) {
            $value = (string) ($model->{$field} ?? '');
            if (mb_strlen($value) < $minimum) {
                $model->addError($field, "Title must be at least {$minimum} characters.");
            }
        }
    }
}
