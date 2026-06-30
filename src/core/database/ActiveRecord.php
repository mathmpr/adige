<?php

namespace Adige\core\database;

use Adige\core\Adige;
use Adige\core\BaseObject;
use Adige\core\BaseException;
use Adige\core\events\Observable;
use Adige\core\database\validators\IntegerValidator;
use Adige\core\database\validators\EmailValidator;
use Adige\core\database\validators\BooleanValidator;
use Adige\core\database\validators\CompareValidator;
use Adige\core\database\validators\DateValidator;
use Adige\core\database\validators\InValidator;
use Adige\core\database\validators\MaskValidator;
use Adige\core\database\validators\MaxLengthValidator;
use Adige\core\database\validators\MinLengthValidator;
use Adige\core\database\validators\NumberValidator;
use Adige\core\database\validators\RequiredValidator;
use Adige\core\database\validators\StringValidator;
use Adige\core\database\validators\UniqueValidator;
use Adige\core\database\validators\UrlValidator;
use Adige\core\database\validators\ValidatorInterface;
use Adige\helpers\Str;
use Adige\core\collection\Collection;
use Adige\core\database\dialects\mysql\MysqlQueryBuilder;
use Adige\core\database\dialects\sqlite\SqliteQueryBuilder;
use Adige\core\database\exceptions\DefaultConnectionNotDefinedException;
use Adige\core\database\exceptions\UnsupportedDatabaseDriverException;
use ReflectionClass;
use Throwable;

/**
 * @property string $queryDistinct
 * @property string $querySelect
 * @property string $queryAlias
 * @property string $queryJoin
 * @property string $queryWhere
 * @property string $queryOrder
 * @property string $queryUpdate
 * @property string $queryInsert
 */
abstract class ActiveRecord extends BaseObject
{
    use Observable;

    const SAVE_IS_INSERT = 'insert';
    const SAVE_IS_UPDATE = 'update';
    const EVENT_BEFORE_INSERT = 'beforeInsert';
    const EVENT_AFTER_INSERT = 'afterInsert';
    const EVENT_BEFORE_UPDATE = 'beforeUpdate';
    const EVENT_AFTER_UPDATE = 'afterUpdate';
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    const EVENT_AFTER_DELETE = 'afterDelete';
    const EVENT_BEFORE_LOAD = 'beforeLoad';
    const EVENT_AFTER_LOAD = 'afterLoad';
    const EVENT_BEFORE_HYDRATE = 'beforeHydrate';
    const EVENT_AFTER_HYDRATE = 'afterHydrate';

    private static array $serializationStack = [];

    abstract public static function tableName(): string;

    private array $attributes = [];

    private array $extraAttributes = [];

    private array $relations = [];

    private array $oldAttributes = [];

    private array $with = [];

    private array $joinWith = [];

    private array $withSelects = [];

    private ?array $joinWithPlan = null;

    private array $errors = [];

    private array $options;

    private ?string $tableName = null;

    private ?string $pkName = null;

    private bool $as_array = false;

    private ?QueryBuilder $queryBuilder = null;

    private ?Connection $runtimeConnection = null;

    private ?array $schemaFields = null;

    /**
     * @param array $props
     * @param array $options
     * @param Connection|null $connection
     * @throws BaseException
     */
    public function __construct(array $props = [], array $options = [], ?Connection $connection = null)
    {
        $this->options = $options;
        $this->init();
        $this->setRuntimeConnection($connection);
        try {
            $class = new ReflectionClass($this);
            $this->tableName = $class->getMethod('tableName')->invoke(null);
        } catch (Throwable $exception) {
            throw new BaseException('Caller no implements ActiveRecord', 0, $exception);
        }
        $this->load($props);
        parent::__construct();
    }

    public function rules(): array
    {
        return [];
    }

    public function beforeSave(string $saveType): void
    {
    }

    public function afterSave(string $saveType): void
    {
    }

    public function load(array $props = []): void
    {
        $this->trigger(self::EVENT_BEFORE_LOAD);

        $this->assignLoadedAttributes($props);

        $this->trigger(self::EVENT_AFTER_LOAD);
    }

    public function hydrate(array $props = []): void
    {
        $this->trigger(self::EVENT_BEFORE_HYDRATE);
        $this->assignLoadedAttributes($props);

        if (!$this->canResolveSchemaMetadata()) {
            $this->oldAttributes = $this->attributes;
            $this->trigger(self::EVENT_AFTER_HYDRATE);
            return;
        }

        $this->oldAttributes = [];

        foreach ($this->attributes as $name => $value) {
            if ($this->isSchemaField($name)) {
                $this->oldAttributes[$name] = $value;
            }
        }

        $this->trigger(self::EVENT_AFTER_HYDRATE);
    }

    /**
     * @param string $name
     * @return mixed|null
     * @throws BaseException
     */
    public function __get($name)
    {
        if ($name === 'rawSql') {
            return $this->getRawSql();
        }

        if (method_exists($this, 'get' . Str::camel($name, '_'))) {
            return $this->{'get' . Str::camel($name, '_')}();
        }

        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        if (array_key_exists($name, $this->extraAttributes)) {
            return $this->extraAttributes[$name];
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if ($this->isRelationMethod($name)) {
            return $this->resolveRelation($name);
        }

        return null;
    }

    public function __set($name, $value): void
    {
        if ($this->isSchemaField($name) || array_key_exists($name, $this->attributes)) {
            $this->attributes[$name] = $value;
            return;
        }

        if ($this->isRelationMethod($name) && $this->isRelationValue($value)) {
            $this->setRelationValue($name, $value);
            return;
        }

        $this->extraAttributes[$name] = $value;
    }

    /**
     * @return bool
     * @throws BaseException
     */
    public function save(?Connection $connection = null, bool $skipValidation = false): bool
    {
        $connection = $this->resolveConnection($connection);

        if (!$skipValidation && !$this->validate($connection)) {
            return false;
        }

        if ($this->isNewRecord()) {
            $this->beforeSave(self::SAVE_IS_INSERT);
            $this->trigger(self::EVENT_BEFORE_INSERT);
            $pkName = $this->getPkNameValue($connection);
            $this->beginInsert($connection)
                ->build($connection);
            $result = $connection->insert($this->getRawSql(), $this->getQueryBuilder()->getParams());
            if ($pkName !== null) {
                $this->{$pkName} = $result;
            }
            $this->syncPersistedState();
            $this->trigger(self::EVENT_AFTER_INSERT);
            $this->afterSave(self::SAVE_IS_INSERT);
            return true;
        }

        $pkName = $this->getPkNameValue($connection);
        if (!$this->hasDirtyPersistedAttributes()) {
            return true;
        }

        $this->beforeSave(self::SAVE_IS_UPDATE);
        $this->trigger(self::EVENT_BEFORE_UPDATE);
        $id = $pkName !== null && array_key_exists($pkName, $this->oldAttributes)
            ? $this->oldAttributes[$pkName]
            : ($pkName !== null ? $this->{$pkName} : null);
        $this->beginUpdate($connection)
            ->update($this->attributes)
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->build($connection);
        $connection->update($this->getRawSql(), $this->getQueryBuilder()->getParams());
        $this->syncPersistedState();
        $this->trigger(self::EVENT_AFTER_UPDATE);
        $this->afterSave(self::SAVE_IS_UPDATE);
        return true;
    }

    public function validate(?Connection $connection = null): bool
    {
        $this->clearErrors();

        foreach ($this->rules() as $rule) {
            $this->applyValidationRule($rule, $connection);
        }

        return !$this->hasErrors();
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return bool|null
     * @throws BaseException
     */
    public function remove(?Connection $connection = null): bool
    {
        $connection = $this->resolveConnection($connection);
        $this->trigger(self::EVENT_BEFORE_DELETE);
        $pkName = $this->getPkNameValue($connection);
        $this->beginDelete($connection)
            ->where([
                ':tableName.`:pkName`' => $pkName !== null ? $this->{$pkName} : null
            ])
            ->build($connection);
        $connection->delete($this->getRawSql(), $this->getQueryBuilder()->getParams());
        $this->trigger(self::EVENT_AFTER_DELETE);
        return true;
    }

    public function update(array $fields): self
    {
        $this->getQueryBuilder()->setCommand('update', $fields);
        if ($this->allEmpty()) {
            $this->load($fields);
        }
        return $this;
    }

    public function select(array $fields): self
    {
        $this->getQueryBuilder()->setCommand('select', $fields);
        return $this;
    }

    private function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->getQueryBuilder()->appendCommandItem('join', [
            'type' => $type,
            'join' => [$table, $on]
        ]);
        return $this;
    }

    public function innerJoin(string $table, string $on): self
    {
        return $this->join($table, $on);
    }

    public function leftJoin(string $table, string $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    public function rightJoin(string $table, string $on): self
    {
        return $this->join($table, $on, 'RIGHT');
    }

    public function where(array $cond): self
    {
        $this->getQueryBuilder()->setWhereCondition($cond);
        return $this;
    }

    public function andWhere(array $cond): self
    {
        $this->getQueryBuilder()->appendWhereCondition('AND', $cond);
        return $this;
    }

    public function orWhere(array $cond): self
    {
        $this->getQueryBuilder()->appendWhereCondition('OR', $cond);
        return $this;
    }

    public function orderByDesc(string $cols): self
    {
        $this->getQueryBuilder()->setCommand('orderBy', [trim($cols), 'DESC']);
        return $this;
    }

    public function orderByAsc(string $cols): self
    {
        $this->getQueryBuilder()->setCommand('orderBy', [trim($cols), 'ASC']);
        return $this;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public function one(?Connection $connection = null): ?self
    {
        $connection = $this->resolveConnection($connection);
        $this->build($connection);
        $result = $connection->select($this->getRawSql(), $this->getQueryBuilder()->getParams());
        if ($this->hasJoinWithRelations()) {
            $objects = $this->hydrateJoinedRows($result, $connection);
            return $objects[0] ?? null;
        }
        if ($this->as_array) {
            if (empty($result)) {
                return null;
            }
            $object = new static([], [], $connection);
            $object->hydrate($result[0]);
            $this->populateRelation($object, $connection);
            return $object;
        }
        if (!empty($result)) {
            $object = new static([], [], $connection);
            $object->hydrate($result[0]);
            $this->populateRelation($object, $connection);
            return $object;
        }
        return null;
    }

    /**
     * @return Collection|array|null
     * @throws BaseException
     */
    public function all(?Connection $connection = null): Collection|array|null
    {
        $connection = $this->resolveConnection($connection);
        $this->build($connection);
        $result = $connection->select($this->getRawSql(), $this->getQueryBuilder()->getParams());
        if ($this->hasJoinWithRelations()) {
            $objects = $this->hydrateJoinedRows($result, $connection);
            if ($this->as_array) {
                return array_map(
                    static fn(ActiveRecord $model): array => $model->toArray(),
                    $objects
                );
            }

            return Collection::factory($objects);
        }
        if ($this->as_array) {
            return $result;
        }
        $return = Collection::factory();
        if (!empty($result)) {
            $objects = [];
            foreach ($result as $item) {
                $object = new static([], [], $connection);
                $object->hydrate($item);
                $objects[] = $object;
            }
            $this->populateRelations($objects, $connection);
            foreach ($objects as $object) {
                $return[] = $object;
            }
            return $return;
        }
        return $return;
    }

    /**
     * @param array|string $relations
     * @return self
     */
    public function with(array|string $relations): self
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        foreach ($relations as $relation) {
            if (!is_string($relation)) {
                continue;
            }

            $relation = trim($relation);
            if ($relation === '') {
                continue;
            }

            if (!in_array($relation, $this->with, true)) {
                $this->with[] = $relation;
            }
        }

        return $this;
    }

    public function joinWith(array|string $relations): self
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        foreach ($relations as $relation) {
            if (!is_string($relation)) {
                continue;
            }

            $relation = trim($relation);
            if ($relation === '') {
                continue;
            }

            if (!in_array($relation, $this->joinWith, true)) {
                $this->joinWith[] = $relation;
            }
        }

        return $this;
    }

    /**
     * @param ActiveRecord $object
     * @return void
     */
    private function populateRelation(ActiveRecord $object, ?Connection $connection = null): void
    {
        $this->populateRelations([$object], $connection);
    }

    private function populateRelations(array $objects, ?Connection $connection = null): void
    {
        if (empty($objects) || empty($this->with)) {
            return;
        }

        $this->populateRelationTree($objects, $this->buildWithTree($this->with), $connection);
    }

    public function isHasOne(): bool
    {
        return $this->options['one'] ?? false;
    }

    /**
     * @return $this
     */
    public function build(?Connection $connection = null): self
    {
        $connection = $this->resolveConnection($connection);
        $schemaFields = $this->getSchemaFields($connection);
        $pkName = $this->getPkNameValue($connection);
        $this->extractRelationSelects();
        $this->getQueryBuilder($connection)->setCommand('joinedSelect', []);
        $this->getQueryBuilder($connection)->setCommand('relationJoin', []);
        $this->getQueryBuilder($connection)->setCommand('fieldAliasMap', []);
        $this->joinWithPlan = null;

        if ($this->hasJoinWithRelations()) {
            $this->applyJoinWithPlan($connection);
        }

        $this->getQueryBuilder($connection)->build(
            $this->tableName,
            $pkName,
            $this->resolveBuildPayload(),
            $schemaFields
        );
        return $this;
    }

    /**
     * @return bool|null
     * @throws BaseException
     */
    public function execute(?Connection $connection = null): ?bool
    {
        $connection = $this->resolveConnection($connection);
        $this->build($connection);
        $connection->query($this->getRawSql(), $this->getQueryBuilder()->getParams());
        return true;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function find(?Connection $connection = null): self
    {
        return (new static([], [], $connection))->beginSelect($connection);
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasMany(?Connection $connection = null): self
    {
        return (new static([], [
            'many' => true
        ], $connection))->beginSelect($connection);
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasOne(?Connection $connection = null): self
    {
        return (new static([], [
            'one' => true
        ], $connection))->beginSelect($connection);
    }

    /**
     * @param $id
     * @return static|null
     * @throws BaseException
     */
    public static function findById($id, ?Connection $connection = null): ?self
    {
        return static::find($connection)
            ->select(['*'])
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->one($connection);
    }

    /**
     * @param $cond
     * @return static|null
     * @throws BaseException
     */
    public static function findAll($cond, ?Connection $connection = null): ?self
    {
        if (is_scalar($cond)) {
            return static::findById($cond, $connection);
        }
        if (is_array($cond)) {
            return static::find($connection)
                ->select(['*'])
                ->where($cond)
                ->one($connection);
        }
        return null;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function put(?Connection $connection = null): self
    {
        return (new static([], [], $connection))->beginUpdate($connection);
    }

    /**
     * @param $cond
     * @param array $fields
     * @param Connection|null $connection
     * @return static
     * @throws BaseException
     */
    public static function putAll($cond, array $fields, ?Connection $connection = null): self
    {
        if (is_scalar($cond)) {
            return static::putById($cond, $fields, $connection);
        }

        $model = (new static([], [], $connection));
        $model->beginUpdate()
            ->update($fields)
            ->where($cond)
            ->execute($connection);
        $model->load($fields);
        return $model;
    }

    /**
     * @param $id
     * @param array $fields
     * @param Connection|null $connection
     * @return static
     * @throws BaseException
     */
    public static function putById($id, array $fields, ?Connection $connection = null): self
    {
        $model = (new static([], [], $connection));
        $payload = $fields;
        $pkName = $model->getPkNameValue($connection);
        if ($pkName !== null) {
            $payload[$pkName] = $id;
        }
        $model->beginUpdate($connection)
            ->update($fields)
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->execute($connection);
        $model->hydrate($payload);
        return $model;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function create(array $fields, ?Connection $connection = null): self
    {
        $model = (new static($fields, [], $connection));
        $model->save($connection);
        return $model;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function delete(?Connection $connection = null): self
    {
        return (new static([], [], $connection))->beginDelete($connection);
    }

    /**
     * @param $cond
     * @param Connection|null $connection
     * @return bool
     * @throws BaseException
     */
    public static function deleteAll($cond, ?Connection $connection = null): bool
    {
        if (is_scalar($cond)) {
            return static::deleteById($cond, $connection);
        }
        if (is_array($cond)) {
            return static::delete($connection)
                ->where($cond)
                ->execute($connection);
        }
        return false;
    }

    /**
     * @param $id
     * @param Connection|null $connection
     * @return bool
     * @throws BaseException
     */
    public static function deleteById($id, ?Connection $connection = null): bool
    {
        return static::delete($connection)
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->execute($connection);
    }

    public function beginSelect(?Connection $connection = null): self
    {
        $this->getQueryBuilder($connection)->begin('SELECT');
        return $this;
    }

    public function beginUpdate(?Connection $connection = null): self
    {
        $this->getQueryBuilder($connection)->begin('UPDATE');
        return $this;
    }

    public function beginDelete(?Connection $connection = null): self
    {
        $this->getQueryBuilder($connection)->begin('DELETE FROM');
        return $this;
    }

    public function beginInsert(?Connection $connection = null): self
    {
        $this->getQueryBuilder($connection)->begin('INSERT INTO');
        return $this;
    }

    public function getQueryInsert(): string
    {
        return $this->getQueryBuilder()->buildInsert(
            $this->resolvePersistedAttributes(),
            $this->getSchemaFields()
        );
    }

    public function getQueryUpdate(): string
    {
        return $this->getQueryBuilder()->buildUpdate($this->getDirtyPersistedAttributes());
    }

    /**
     * @return string
     * @throws BaseException
     */
    public function getQuerySelect(): string
    {
        $this->extractRelationSelects();
        return $this->getQueryBuilder()->buildSelect($this->tableName);
    }

    /**
     * @throws BaseException
     */
    public function getQueryWhere(): string
    {
        return $this->getQueryBuilder()->buildWhere($this->tableName, $this->getPkNameValue());
    }

    public function getQueryJoin(): string
    {
        return $this->getQueryBuilder()->buildJoin($this->tableName, $this->getPkNameValue());
    }

    public function getQueryOrder(): string
    {
        return $this->getQueryBuilder()->buildOrder();
    }

    public function getQueryAlias(): string
    {
        return $this->getQueryBuilder()->getQueryAlias();
    }

    public function getQueryDistinct(): string
    {
        return $this->getQueryBuilder()->getQueryDistinct();
    }

    protected function hasManyRelation(
        string  $relatedModelClass,
        string  $foreignKey,
        ?string $localKey = null
    ): RelationDefinition
    {
        return new RelationDefinition(
            RelationDefinition::TYPE_MANY,
            $relatedModelClass,
            $localKey ?? $this->getPkNameValue() ?? 'id',
            $foreignKey,
            $this->runtimeConnection
        );
    }

    protected function hasOneRelation(
        string  $relatedModelClass,
        string  $foreignKey,
        ?string $localKey = null
    ): RelationDefinition
    {
        return new RelationDefinition(
            RelationDefinition::TYPE_ONE,
            $relatedModelClass,
            $localKey ?? $this->getPkNameValue() ?? 'id',
            $foreignKey,
            $this->runtimeConnection
        );
    }

    public function getRawSql(): string
    {
        return $this->queryBuilder?->getRawSql() ?? '';
    }

    protected function createQueryBuilder(?Connection $connection = null): QueryBuilder
    {
        return match ($this->resolveConnection($connection)->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            Connection::TYPE_MYSQL => new MysqlQueryBuilder(),
            Connection::TYPE_SQLITE => new SqliteQueryBuilder(),
            default => throw new UnsupportedDatabaseDriverException(
                $this->resolveConnection($connection)->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME)
            ),
        };
    }

    public function setRuntimeConnection(?Connection $connection = null): self
    {
        if ($connection !== null) {
            $this->runtimeConnection = $connection;
            $this->schemaFields = null;
            $this->pkName = null;
            $this->queryBuilder = null;
        }
        return $this;
    }

    protected function getRuntimeConnection(): ?Connection
    {
        return $this->runtimeConnection;
    }

    private function getQueryBuilder(?Connection $connection = null): QueryBuilder
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->createQueryBuilder($connection);
        }

        return $this->queryBuilder;
    }

    private function getPkNameValue(?Connection $connection = null): ?string
    {
        if ($this->pkName === null && $this->tableName !== null) {
            $this->pkName = Schema::pkName($this->tableName, $this->resolveConnection($connection)->getDb());
        }

        return $this->pkName;
    }

    private function resolveConnection(?Connection $connection = null): Connection
    {
        return $connection
            ?? $this->runtimeConnection
            ?? $this->resolveAppConnection()
            ?? Connection::getDefaultConnection();
    }

    private function canResolveSchemaMetadata(?Connection $connection = null): bool
    {
        if ($connection !== null || $this->runtimeConnection !== null || $this->resolveAppConnection() !== null) {
            return true;
        }

        try {
            Connection::getDefaultConnection();
            return true;
        } catch (DefaultConnectionNotDefinedException) {
            return false;
        }
    }

    private function allEmpty(): bool
    {
        return empty($this->attributes)
            && empty($this->oldAttributes);
    }

    private function resolveAppConnection(): ?Connection
    {
        if (Adige::$app === null) {
            return null;
        }

        $connection = Adige::$app->{Adige::DB_HANDLER} ?? null;

        return $connection instanceof Connection ? $connection : null;
    }

    private function assignLoadedAttributes(array $props = []): void
    {
        if (!$this->canResolveSchemaMetadata()) {
            foreach ($props as $prop => $value) {
                $this->attributes[$prop] = $value;
            }

            return;
        }

        foreach ($props as $prop => $value) {
            if ($this->isSchemaField($prop)) {
                $this->attributes[$prop] = $value;
            }
        }
    }

    private function extractRelationSelects(): void
    {
        $this->withSelects = [];
        $selects = $this->getQueryBuilder()->getCommand('select');
        if (!is_array($selects)) {
            return;
        }

        $paths = $this->getWithPaths($this->buildWithTree($this->with));
        usort($paths, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($selects as $key => $select) {
            if (!is_string($select)) {
                continue;
            }

            foreach ($paths as $path) {
                $prefix = $path . '.';
                if (!str_starts_with($select, $prefix)) {
                    continue;
                }

                $field = substr($select, strlen($prefix));
                if ($field === '' || str_contains($field, '.')) {
                    continue;
                }

                $this->withSelects[$path][] = $field;
                unset($selects[$key]);
                break;
            }
        }

        $this->getQueryBuilder()->setCommand('select', array_values($selects));
    }

    public function getAttributes(): array
    {
        return array_merge($this->attributes, $this->extraAttributes, $this->relations);
    }

    public function fields(): array
    {
        return [
            ...$this->getSchemaFields(),
            ...array_keys($this->relations),
        ];
    }

    public function toArray(): array
    {
        $serializationKey = spl_object_id($this);
        if (isset(self::$serializationStack[$serializationKey])) {
            return [];
        }

        self::$serializationStack[$serializationKey] = true;
        $attrs = [];

        try {
            foreach ($this->fields() as $key => $field) {
                [$fieldName, $resolver] = $this->normalizeFieldDefinition($key, $field);
                $attrs[$fieldName] = $this->normalizeArrayValue($this->resolveFieldValue($resolver));
            }
        } finally {
            unset(self::$serializationStack[$serializationKey]);
        }

        return $attrs;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function getPkName(): ?string
    {
        return $this->pkName;
    }

    public function asArray(): self
    {
        $this->as_array = true;
        return $this;
    }

    public function asCollection(): self
    {
        $this->as_array = false;
        return $this;
    }

    public function getOldAttributes(): array
    {
        return $this->oldAttributes;
    }

    public function getChangedAttributes(): array
    {
        return $this->getDirtyPersistedAttributes();
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function isDirty(): bool
    {
        return $this->hasDirtyPersistedAttributes();
    }

    private function setRelationValue(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
        unset($this->extraAttributes[$name]);
    }

    private function resolveRelation(string $name): mixed
    {
        $call = $this->{$name}();

        if ($call instanceof RelationDefinition) {
            $relation = $this->materializeDefinedRelation($call, $name);
            $this->setRelationValue($name, $relation);
            return $relation;
        }

        if ($call instanceof ActiveRecord) {
            $call->setRuntimeConnection($this->runtimeConnection);
            $all = $call->all();
            $relation = ($call->isHasOne() && count($all) > 0) ? $all[0] : $all;
            $this->setRelationValue($name, $relation);
            return $relation;
        }

        if ($call instanceof Collection) {
            $this->setRelationValue($name, $call);
            return $call;
        }

        return null;
    }

    private function isRelationMethod(string $name): bool
    {
        if (!method_exists($this, $name)) {
            return false;
        }

        $method = new \ReflectionMethod($this, $name);
        if (!$method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        return !in_array($method->getDeclaringClass()->getName(), [self::class, BaseObject::class], true);
    }

    private function isRelationValue(mixed $value): bool
    {
        return $value instanceof ActiveRecord
            || $value instanceof Collection
            || is_array($value)
            || $value === null;
    }

    private function hasDirtyPersistedAttributes(): bool
    {
        if ($this->isNewRecord()) {
            return !empty($this->attributes);
        }

        return !empty($this->getDirtyPersistedAttributes());
    }

    private function syncPersistedState(): void
    {
        $this->oldAttributes = [];

        foreach ($this->attributes as $name => $value) {
            if ($this->isSchemaField($name)) {
                $this->oldAttributes[$name] = $value;
            }
        }
    }

    private function getSchemaFields(?Connection $connection = null): array
    {
        if ($this->tableName === null) {
            return [];
        }

        if ($this->schemaFields === null) {
            $this->schemaFields = Schema::getFields($this->tableName, $this->resolveConnection($connection)->getDb()) ?? [];
            $this->normalizeStateAgainstSchema();
        }

        return $this->schemaFields;
    }

    private function normalizeStateAgainstSchema(): void
    {
        if ($this->schemaFields === null) {
            return;
        }

        foreach ($this->attributes as $name => $value) {
            if (in_array($name, $this->schemaFields, true)) {
                continue;
            }

            unset($this->attributes[$name]);
            $this->extraAttributes[$name] ??= $value;
        }

        foreach ($this->oldAttributes as $name => $value) {
            if (in_array($name, $this->schemaFields, true)) {
                continue;
            }

            unset($this->oldAttributes[$name]);
            $this->extraAttributes[$name] ??= $value;
        }
    }

    private function isSchemaField(string $name): bool
    {
        return in_array($name, $this->getSchemaFields(), true);
    }

    private function normalizeArrayValue(mixed $value): mixed
    {
        if ($value instanceof ActiveRecord) {
            if (isset(self::$serializationStack[spl_object_id($value)])) {
                return null;
            }
            return $value->toArray();
        }

        if ($value instanceof Collection) {
            return $value->toArray();
        }

        return $value;
    }

    private function normalizeFieldDefinition(int|string $key, mixed $field): array
    {
        if (is_int($key)) {
            if (!is_string($field)) {
                throw new BaseException('Numeric fields() entries must contain a string field name');
            }

            return [$field, $field];
        }

        if (is_callable($field)) {
            return [$key, $field];
        }

        if (!is_string($field)) {
            throw new BaseException("Field '{$key}' must resolve from a string name or callable");
        }

        return [$key, $field];
    }

    private function resolveFieldValue(string|callable $resolver): mixed
    {
        if (is_callable($resolver)) {
            return $resolver($this);
        }

        if (array_key_exists($resolver, $this->attributes)) {
            return $this->attributes[$resolver];
        }

        if (array_key_exists($resolver, $this->relations)) {
            return $this->relations[$resolver];
        }

        return null;
    }

    private function isNewRecord(): bool
    {
        return !empty($this->attributes) && empty($this->oldAttributes);
    }

    private function resolvePersistedAttributes(): array
    {
        $fields = array_flip($this->getSchemaFields());

        return array_intersect_key($this->attributes, $fields);
    }

    private function resolveBuildPayload(): array
    {
        return match ($this->getQueryBuilder()->getCommand('begin')) {
            'INSERT INTO' => $this->resolvePersistedAttributes(),
            'UPDATE' => $this->getDirtyPersistedAttributes(),
            default => [],
        };
    }

    private function applyValidationRule(mixed $rule, ?Connection $connection = null): void
    {
        if (!is_array($rule) || !isset($rule[0], $rule[1])) {
            throw new BaseException('Each validation rule must define fields and a validator.');
        }

        $fields = is_array($rule[0]) ? $rule[0] : [$rule[0]];
        $fields = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => is_string($field) && trim($field) !== ''
        ));

        if ($fields === []) {
            throw new BaseException('Validation rules must target at least one field.');
        }

        $validator = $this->resolveValidator($rule[1]);
        $validator->validate($this, $fields, $this->normalizeRuleParams($rule), $connection);
    }

    private function resolveValidator(mixed $validator): ValidatorInterface
    {
        if ($validator instanceof ValidatorInterface) {
            return $validator;
        }

        if (is_string($validator)) {
            $builtIn = $this->createBuiltInValidator($validator);
            if ($builtIn !== null) {
                return $builtIn;
            }

            if (class_exists($validator)) {
                $instance = new $validator();
                if ($instance instanceof ValidatorInterface) {
                    return $instance;
                }
            }
        }

        throw new BaseException('Unsupported validator definition: ' . get_debug_type($validator));
    }

    private function createBuiltInValidator(string $name): ?ValidatorInterface
    {
        return match (strtolower(trim($name))) {
            'required' => new RequiredValidator(),
            'boolean', 'bool' => new BooleanValidator(),
            'integer', 'int' => new IntegerValidator(),
            'number', 'float', 'double', 'decimal' => new NumberValidator(),
            'string' => new StringValidator(),
            'minlength', 'min_length' => new MinLengthValidator(),
            'maxlength', 'max_length' => new MaxLengthValidator(),
            'in', 'range' => new InValidator(),
            'compare' => new CompareValidator(),
            'date', 'datetime' => new DateValidator(),
            'url' => new UrlValidator(),
            'mask', 'match', 'regex', 'regexp' => new MaskValidator(),
            'email' => new EmailValidator(),
            'unique' => new UniqueValidator(),
            default => null,
        };
    }

    private function normalizeRuleParams(array $rule): array
    {
        $params = [];
        $args = [];

        foreach ($rule as $key => $value) {
            if ($key === 0 || $key === 1) {
                continue;
            }

            if (is_int($key)) {
                $args[] = $value;
                continue;
            }

            $params[$key] = $value;
        }

        if ($args !== []) {
            $params['args'] = $args;
        }

        return $params;
    }

    private function eagerLoadRelation(
        array              $objects,
        string             $relationName,
        RelationDefinition $definition,
        ?Connection        $connection = null,
        ?string            $relationPath = null
    ): void
    {
        $relationPath ??= $relationName;

        if (isset($this->withSelects[$relationPath])) {
            $definition->select($this->withSelects[$relationPath]);
        }

        $definition->applyRuntimeConnection($connection ?? $this->runtimeConnection);
        $localKey = $definition->getLocalKey();
        $keys = [];

        foreach ($objects as $object) {
            $value = $object->{$localKey};
            if ($value !== null && $value !== '') {
                $keys[] = $value;
            }
        }

        $keys = array_values(array_unique($keys, SORT_REGULAR));
        if (empty($keys)) {
            foreach ($objects as $object) {
                $object->setRelationValue(
                    $relationName,
                    $definition->isHasOne() ? null : Collection::factory()
                );
            }
            return;
        }

        $related = $definition->createQuery($keys, $connection ?? $this->runtimeConnection)
            ->all($connection ?? $this->runtimeConnection);
        $grouped = [];

        foreach ($related ?? [] as $item) {
            $grouped[$item->{$definition->getForeignKey()}][] = $item;
        }

        foreach ($objects as $object) {
            $value = $object->{$localKey};
            $items = $grouped[$value] ?? [];
            $object->setRelationValue(
                $relationName,
                $definition->isHasOne() ? ($items[0] ?? null) : Collection::factory($items)
            );
        }
    }

    private function materializeDefinedRelation(
        RelationDefinition $definition,
        string             $relationName,
        ?string            $relationPath = null
    ): mixed
    {
        $relationPath ??= $relationName;

        if (isset($this->withSelects[$relationPath])) {
            $definition->select($this->withSelects[$relationPath]);
        }

        $definition->applyRuntimeConnection($this->runtimeConnection);
        $localValue = $this->{$definition->getLocalKey()};
        if ($localValue === null || $localValue === '') {
            return $definition->isHasOne() ? null : Collection::factory();
        }

        $all = $definition->createQuery([$localValue], $this->runtimeConnection)->all($this->runtimeConnection);

        return $definition->isHasOne()
            ? (($all instanceof Collection && count($all) > 0) ? $all[0] : null)
            : $all;
    }

    private function getDirtyPersistedAttributes(): array
    {
        $dirty = [];

        foreach ($this->attributes as $name => $value) {
            if (!$this->isSchemaField($name)) {
                continue;
            }

            if (!array_key_exists($name, $this->oldAttributes) || $this->oldAttributes[$name] !== $value) {
                $dirty[$name] = $value;
            }
        }

        return $dirty;
    }

    private function hasJoinWithRelations(): bool
    {
        return !empty($this->joinWith);
    }

    private function applyJoinWithPlan(Connection $connection): void
    {
        $this->joinWithPlan = $this->buildJoinWithPlan($connection);
        $this->getQueryBuilder()->setCommand('joinedSelect', $this->joinWithPlan['selects']);
        $this->getQueryBuilder()->setCommand('relationJoin', $this->joinWithPlan['joins']);
        $this->getQueryBuilder()->setCommand('fieldAliasMap', $this->joinWithPlan['fieldAliasMap']);
    }

    private function buildJoinWithPlan(Connection $connection): array
    {
        $rootNode = [
            'path' => '',
            'relationName' => null,
            'parentPath' => null,
            'tableName' => $this->tableName,
            'alias' => $this->tableName,
            'pkName' => $this->pkName,
            'modelClass' => static::class,
            'fields' => $this->getSchemaFields(),
            'fieldAliases' => $this->buildJoinFieldAliases('', $this->getSchemaFields()),
        ];

        $nodes = [];
        $this->appendJoinWithNodes(
            $nodes,
            $this->buildWithTree($this->joinWith),
            $this,
            $rootNode,
            $connection
        );

        return [
            'root' => $rootNode,
            'nodes' => $nodes,
            'selects' => $this->buildJoinSelects($rootNode, $nodes),
            'joins' => $this->buildJoinCommands($nodes),
            'fieldAliasMap' => $this->buildJoinFieldReferenceMap($nodes),
        ];
    }

    private function buildJoinFieldReferenceMap(array $nodes): array
    {
        $map = [];
        $tableCounts = [];

        foreach ($nodes as $node) {
            $map[$node['path']] = $node['alias'];
            $tableCounts[$node['tableName']] = ($tableCounts[$node['tableName']] ?? 0) + 1;
        }

        foreach ($nodes as $node) {
            if (($tableCounts[$node['tableName']] ?? 0) === 1) {
                $map[$node['tableName']] = $node['alias'];
            }
        }

        return $map;
    }

    private function appendJoinWithNodes(
        array        &$nodes,
        array        $tree,
        ActiveRecord $parentModel,
        array        $parentNode,
        Connection   $connection,
        string       $prefix = ''
    ): void
    {
        foreach ($tree as $relationName => $children) {
            $path = $prefix === '' ? $relationName : $prefix . '.' . $relationName;

            if (!$parentModel->isRelationMethod($relationName)) {
                throw new BaseException("joinWith relation '{$path}' is not defined");
            }

            $definition = $parentModel->{$relationName}();
            if (!$definition instanceof RelationDefinition) {
                throw new BaseException("joinWith only supports RelationDefinition relations for '{$path}'");
            }

            $relatedModelClass = $definition->getRelatedModelClass();
            /** @var ActiveRecord $relatedModel */
            $relatedModel = new $relatedModelClass([], [], $connection);
            $alias = 'jr__' . str_replace('.', '__', $path);
            $fields = $relatedModel->getSchemaFields();

            $node = [
                'path' => $path,
                'relationName' => $relationName,
                'parentPath' => $parentNode['path'],
                'tableName' => $relatedModel->getTableName(),
                'alias' => $alias,
                'pkName' => $relatedModel->getPkName(),
                'modelClass' => $relatedModelClass,
                'fields' => $fields,
                'fieldAliases' => $this->buildJoinFieldAliases($path, $fields),
                'joinType' => $definition->getJoinType(),
                'joinTable' => $relatedModel->getTableName() . ' AS ' . $alias,
                'joinCondition' => $this->buildJoinCondition($definition, $parentNode, $alias),
                'definition' => $definition,
            ];

            $nodes[] = $node;

            if (!empty($children)) {
                $this->appendJoinWithNodes($nodes, $children, $relatedModel, $node, $connection, $path);
            }
        }
    }

    private function buildJoinFieldAliases(string $path, array $fields): array
    {
        $aliases = [];

        foreach ($fields as $field) {
            $aliases[$field] = $this->buildJoinFieldAlias($path, $field);
        }

        return $aliases;
    }

    private function buildJoinFieldAlias(string $path, string $field): string
    {
        $pathKey = $path === '' ? 'root' : str_replace('.', '__', $path);
        return '__adg__' . $pathKey . '__' . $field;
    }

    private function buildJoinCondition(RelationDefinition $definition, array $parentNode, string $alias): string
    {
        $parentReference = $parentNode['path'] === ''
            ? $parentNode['tableName']
            : $parentNode['alias'];

        return sprintf(
            '%s.%s = %s.%s',
            $alias,
            $definition->getForeignKey(),
            $parentReference,
            $definition->getLocalKey()
        );
    }

    private function buildJoinSelects(array $rootNode, array $nodes): array
    {
        $selects = $this->buildNodeJoinSelects($rootNode);

        foreach ($nodes as $node) {
            foreach ($this->buildNodeJoinSelects($node) as $select) {
                $selects[] = $select;
            }
        }

        return $selects;
    }

    private function buildNodeJoinSelects(array $node): array
    {
        $reference = $node['path'] === ''
            ? $node['tableName']
            : $node['alias'];
        $selects = [];

        foreach ($node['fieldAliases'] as $field => $alias) {
            $selects[] = sprintf('%s.%s AS %s', $reference, $field, $alias);
        }

        return $selects;
    }

    private function buildJoinCommands(array $nodes): array
    {
        return array_map(
            static fn(array $node): array => [
                'type' => $node['joinType'],
                'join' => [$node['joinTable'], $node['joinCondition']],
            ],
            $nodes
        );
    }

    private function hydrateJoinedRows(array $rows, Connection $connection): array
    {
        if (empty($rows)) {
            return [];
        }

        $plan = $this->joinWithPlan ?? $this->buildJoinWithPlan($connection);
        $roots = [];
        $nodeIndex = [];

        foreach ($rows as $row) {
            $rootData = $this->extractJoinedNodeData($row, $plan['root']);
            if (!$this->rowHasJoinedNodeData($rootData)) {
                continue;
            }

            $rootKey = $this->buildJoinedIdentityKey($plan['root'], $rootData);
            if (!isset($roots[$rootKey])) {
                $root = new static([], [], $connection);
                $root->hydrate($rootData);
                $roots[$rootKey] = $root;
            } else {
                $root = $roots[$rootKey];
            }

            $instances = ['' => $root];
            foreach ($plan['nodes'] as $node) {
                $parent = $instances[$node['parentPath']] ?? null;
                if (!$parent instanceof ActiveRecord) {
                    continue;
                }

                $data = $this->extractJoinedNodeData($row, $node);
                if (!$this->rowHasJoinedNodeData($data)) {
                    $this->initializeJoinedRelation($parent, $node);
                    $instances[$node['path']] = null;
                    continue;
                }

                $cacheKey = spl_object_id($parent) . '::' . $this->buildJoinedIdentityKey($node, $data);
                if (!isset($nodeIndex[$node['path']][$cacheKey])) {
                    $child = $this->createJoinedModel($node, $data, $connection);
                    $this->attachJoinedModel($parent, $node, $child);
                    $nodeIndex[$node['path']][$cacheKey] = $child;
                }

                $instances[$node['path']] = $nodeIndex[$node['path']][$cacheKey];
            }
        }

        return array_values($roots);
    }

    private function extractJoinedNodeData(array $row, array $node): array
    {
        $data = [];

        foreach ($node['fieldAliases'] as $field => $alias) {
            $data[$field] = $row[$alias] ?? null;
        }

        return $data;
    }

    private function rowHasJoinedNodeData(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    private function buildJoinedIdentityKey(array $node, array $data): string
    {
        $pkName = $node['pkName'] ?? null;
        if ($pkName !== null && array_key_exists($pkName, $data) && $data[$pkName] !== null) {
            return (string)$data[$pkName];
        }

        return serialize($data);
    }

    private function createJoinedModel(array $node, array $data, Connection $connection): ActiveRecord
    {
        $modelClass = $node['modelClass'];
        /** @var ActiveRecord $model */
        $model = new $modelClass([], [], $connection);
        $model->hydrate($data);

        return $model;
    }

    private function initializeJoinedRelation(ActiveRecord $parent, array $node): void
    {
        if (array_key_exists($node['relationName'], $parent->getRelations())) {
            return;
        }

        $parent->setRelationValue(
            $node['relationName'],
            $node['definition']->isHasOne() ? null : Collection::factory()
        );
    }

    private function attachJoinedModel(ActiveRecord $parent, array $node, ActiveRecord $child): void
    {
        if ($node['definition']->isHasOne()) {
            $parent->setRelationValue($node['relationName'], $child);
            return;
        }

        $relations = $parent->getRelations();
        $collection = $relations[$node['relationName']] ?? null;
        if (!$collection instanceof Collection) {
            $collection = Collection::factory();
            $parent->setRelationValue($node['relationName'], $collection);
        }

        $collection[] = $child;
    }

    private function buildWithTree(array $relations): array
    {
        $tree = [];

        foreach ($relations as $relation) {
            if (!is_string($relation) || $relation === '') {
                continue;
            }

            $cursor = &$tree;
            foreach (explode('.', $relation) as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }

                if (!array_key_exists($segment, $cursor)) {
                    $cursor[$segment] = [];
                }

                $cursor = &$cursor[$segment];
            }

            unset($cursor);
        }

        return $tree;
    }

    private function getWithPaths(array $tree, string $prefix = ''): array
    {
        $paths = [];

        foreach ($tree as $relation => $children) {
            $path = $prefix === '' ? $relation : $prefix . '.' . $relation;
            $paths[] = $path;

            foreach ($this->getWithPaths($children, $path) as $childPath) {
                $paths[] = $childPath;
            }
        }

        return $paths;
    }

    private function populateRelationTree(
        array       $objects,
        array       $tree,
        ?Connection $connection = null,
        string      $prefix = ''
    ): void
    {
        if (empty($objects) || empty($tree)) {
            return;
        }

        foreach ($tree as $relationName => $children) {
            if (!$objects[0]->isRelationMethod($relationName)) {
                continue;
            }

            $relationPath = $prefix === '' ? $relationName : $prefix . '.' . $relationName;
            $relation = $objects[0]->{$relationName}();

            if ($relation instanceof RelationDefinition) {
                $this->eagerLoadRelation($objects, $relationName, $relation, $connection, $relationPath);
            } else {
                $this->populateLegacyRelation($objects, $relationName, $connection, $relationPath);
            }

            if (empty($children)) {
                continue;
            }

            $relatedObjects = $this->collectRelatedObjects($objects, $relationName);
            $this->populateRelationTree($relatedObjects, $children, $connection, $relationPath);
        }
    }

    private function populateLegacyRelation(
        array       $objects,
        string      $relationName,
        ?Connection $connection = null,
        string      $relationPath = ''
    ): void
    {
        foreach ($objects as $object) {
            $call = $object->{$relationName}();

            if ($call instanceof ActiveRecord && isset($this->withSelects[$relationPath])) {
                $call->select($this->withSelects[$relationPath]);
                $all = $call->all($connection);
                $object->setRelationValue(
                    $relationName,
                    ($call->isHasOne() && count($all) > 0) ? $all[0] : $all
                );
                continue;
            }

            $object->{$relationName};
        }
    }

    private function collectRelatedObjects(array $objects, string $relationName): array
    {
        $relatedObjects = [];

        foreach ($objects as $object) {
            $relation = $object->getRelations()[$relationName] ?? null;

            if ($relation instanceof ActiveRecord) {
                $relatedObjects[] = $relation;
                continue;
            }

            if ($relation instanceof Collection) {
                foreach ($relation as $item) {
                    if ($item instanceof ActiveRecord) {
                        $relatedObjects[] = $item;
                    }
                }
                continue;
            }

            if (!is_array($relation)) {
                continue;
            }

            foreach ($relation as $item) {
                if ($item instanceof ActiveRecord) {
                    $relatedObjects[] = $item;
                }
            }
        }

        return $relatedObjects;
    }

}
