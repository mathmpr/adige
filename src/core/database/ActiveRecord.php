<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use Adige\core\BaseException;
use Adige\helpers\Str;
use Adige\core\collection\Collection;
use Adige\core\database\dialects\mysql\MysqlQueryBuilder;
use Adige\core\database\dialects\sqlite\SqliteQueryBuilder;
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
    private static array $serializationStack = [];

    abstract public static function tableName(): string;

    private array $attributes = [];

    private array $extraAttributes = [];

    private array $relations = [];

    private array $oldAttributes = [];

    private array $with = [];

    private array $withSelects = [];

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
        $this->pkName = Schema::pkName($this->tableName, $this->resolveConnection()->getDb());
        $this->queryBuilder = $this->createQueryBuilder();
        $this->load($props);
        parent::__construct();
    }

    public function load(array $props = []): void
    {
        foreach ($props as $prop => $value) {
            if ($this->isSchemaField($prop)) {
                $this->attributes[$prop] = $value;
            }
        }
    }

    public function hydrate(array $props = []): void
    {
        $this->load($props);
        $this->oldAttributes = [];

        foreach ($this->attributes as $name => $value) {
            if ($this->isSchemaField($name)) {
                $this->oldAttributes[$name] = $value;
            }
        }
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
    public function save(?Connection $connection = null): bool
    {
        $connection = $this->resolveConnection($connection);

        if ($this->isNewRecord()) {
            $this->beginInsert()
                ->build($connection);
            $result = $connection->insert($this->getRawSql(), $this->queryBuilder->getParams());
            $this->{$this->pkName} = $result;
            $this->syncPersistedState();
            return true;
        }

        if (!$this->hasDirtyPersistedAttributes()) {
            return true;
        }

        $id = array_key_exists($this->pkName, $this->oldAttributes)
            ? $this->oldAttributes[$this->pkName]
            : $this->{$this->pkName};
        $this->beginUpdate()
            ->update($this->attributes)
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->build($connection);
        $connection->update($this->getRawSql(), $this->queryBuilder->getParams());
        $this->syncPersistedState();
        return true;
    }

    /**
     * @return bool|null
     * @throws BaseException
     */
    public function remove(?Connection $connection = null): bool
    {
        $connection = $this->resolveConnection($connection);
        $this->beginDelete()
            ->where([
                ':tableName.`:pkName`' => $this->{$this->pkName}
            ])
            ->build($connection);
        $connection->delete($this->getRawSql(), $this->queryBuilder->getParams());
        return true;
    }

    public function update(array $fields): self
    {
        $this->queryBuilder->setCommand('update', $fields);
        if ($this->allEmpty()) {
            $this->load($fields);
        }
        return $this;
    }

    public function select(array $fields): self
    {
        $this->queryBuilder->setCommand('select', $fields);
        return $this;
    }

    private function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->queryBuilder->appendCommandItem('join', [
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

    public function where(array $cond, bool $in = false): self
    {
        $this->queryBuilder->appendCommandItem('where', [
            'command' => 'initial',
            'cond' => $cond,
            'in' => $in
        ]);
        return $this;
    }

    public function andWhere(array $cond, bool $in = false): self
    {
        $this->queryBuilder->appendCommandItem('where', [
            'command' => 'AND',
            'cond' => $cond,
            'in' => $in
        ]);
        return $this;
    }

    public function orWhere(array $cond, bool $in = false): self
    {
        $this->queryBuilder->appendCommandItem('where', [
            'command' => 'OR',
            'cond' => $cond,
            'in' => $in
        ]);
        return $this;
    }

    public function whereIn(array $cond): self
    {
        return $this->where($cond, true);
    }

    public function andWhereIn(array $cond): self
    {
        return $this->andWhere($cond, true);
    }

    public function orWhereIn(array $cond): self
    {
        return $this->orWhere($cond, true);
    }

    public function orderByDesc(string $cols): self
    {
        $this->queryBuilder->setCommand('orderBy', [trim($cols), 'DESC']);
        return $this;
    }

    public function orderByAsc(string $cols): self
    {
        $this->queryBuilder->setCommand('orderBy', [trim($cols), 'ASC']);
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
        $result = $connection->select($this->getRawSql(), $this->queryBuilder->getParams());
        if ($this->as_array) {
            if (empty($result)) {
                return null;
            }
            $object = new static([], [], $connection);
            $object->hydrate($result[0]);
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
        $result = $connection->select($this->getRawSql(), $this->queryBuilder->getParams());
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
        if (is_string($relations)) {
            $this->with[] = $relations;
        }
        if (is_array($relations)) {
            $this->with = $relations;
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

        $class = new ReflectionClass($objects[0]);
        $methods = [];
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $methods[] = $method->getName();
            }
        }
        foreach ($this->with as $relation) {
            if (in_array($relation, $methods)) {
                $definition = $objects[0]->{$relation}();
                if ($definition instanceof RelationDefinition) {
                    $this->eagerLoadRelation($objects, $relation, $definition, $connection);
                    continue;
                }

                foreach ($objects as $object) {
                    if (isset($this->withSelects[$relation])) {
                        $call = $object->{$relation}()
                            ->select($this->withSelects[$relation]);
                        $all = $call->all($connection);
                        $object->setRelationValue($relation, ($call->isHasOne() && count($all) > 0) ? $all[0] : $all);
                    } else {
                        $object->{$relation};
                    }
                }
            }
        }
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
        $this->extractRelationSelects();
        $this->queryBuilder->build(
            $this->tableName,
            $this->pkName,
            $this->resolveBuildPayload(),
            Schema::getFields($this->tableName, $connection->getDb())
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
        $connection->query($this->getRawSql(), $this->queryBuilder->getParams());
        return true;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function find(?Connection $connection = null): self
    {
        return (new static([], [], $connection))->beginSelect();
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasMany(?Connection $connection = null): self
    {
        return (new static([], [
            'many' => true
        ], $connection))->beginSelect();
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasOne(?Connection $connection = null): self
    {
        return (new static([], [
            'one' => true
        ], $connection))->beginSelect();
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
        return (new static([], [], $connection))->beginUpdate();
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
        $payload[$model->pkName] = $id;
        $model->beginUpdate()
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
        return (new static([], [], $connection))->beginDelete();
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

    public function beginSelect(): self
    {
        $this->queryBuilder->begin('SELECT');
        return $this;
    }

    public function beginUpdate(): self
    {
        $this->queryBuilder->begin('UPDATE');
        return $this;
    }

    public function beginDelete(): self
    {
        $this->queryBuilder->begin('DELETE FROM');
        return $this;
    }

    public function beginInsert(): self
    {
        $this->queryBuilder->begin('INSERT INTO');
        return $this;
    }

    public function getQueryInsert(): string
    {
        return $this->queryBuilder->buildInsert(
            $this->attributes,
            Schema::getFields($this->tableName, $this->resolveConnection()->getDb())
        );
    }

    public function getQueryUpdate(): string
    {
        return $this->queryBuilder->buildUpdate($this->getDirtyPersistedAttributes());
    }

    /**
     * @return string
     * @throws BaseException
     */
    public function getQuerySelect(): string
    {
        $this->extractRelationSelects();
        return $this->queryBuilder->buildSelect($this->tableName);
    }

    /**
     * @throws BaseException
     */
    public function getQueryWhere(): string
    {
        return $this->queryBuilder->buildWhere($this->tableName, $this->pkName);
    }

    public function getQueryJoin(): string
    {
        return $this->queryBuilder->buildJoin($this->tableName, $this->pkName);
    }

    public function getQueryOrder(): string
    {
        return $this->queryBuilder->buildOrder();
    }

    public function getQueryAlias(): string
    {
        return $this->queryBuilder->getQueryAlias();
    }

    public function getQueryDistinct(): string
    {
        return $this->queryBuilder->getQueryDistinct();
    }

    protected function hasManyRelation(
        string $relatedModelClass,
        string $foreignKey,
        ?string $localKey = null
    ): RelationDefinition {
        return new RelationDefinition(
            RelationDefinition::TYPE_MANY,
            $relatedModelClass,
            $localKey ?? $this->pkName ?? 'id',
            $foreignKey,
            $this->runtimeConnection
        );
    }

    protected function hasOneRelation(
        string $relatedModelClass,
        string $foreignKey,
        ?string $localKey = null
    ): RelationDefinition {
        return new RelationDefinition(
            RelationDefinition::TYPE_ONE,
            $relatedModelClass,
            $localKey ?? $this->pkName ?? 'id',
            $foreignKey,
            $this->runtimeConnection
        );
    }

    public function getRawSql(): string
    {
        return $this->queryBuilder?->getRawSql() ?? '';
    }

    protected function createQueryBuilder(): QueryBuilder
    {
        return match ($this->resolveConnection()->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            Connection::TYPE_MYSQL => new MysqlQueryBuilder(),
            Connection::TYPE_SQLITE => new SqliteQueryBuilder(),
            default => throw new UnsupportedDatabaseDriverException(
                $this->resolveConnection()->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME)
            ),
        };
    }

    public function setRuntimeConnection(?Connection $connection = null): self
    {
        if ($connection !== null) {
            $this->runtimeConnection = $connection;
            $this->schemaFields = null;
            if ($this->tableName !== null) {
                $this->pkName = Schema::pkName($this->tableName, $connection->getDb());
            }
        }
        return $this;
    }

    protected function getRuntimeConnection(): ?Connection
    {
        return $this->runtimeConnection;
    }

    private function resolveConnection(?Connection $connection = null): Connection
    {
        return $connection ?? $this->runtimeConnection ?? Connection::getDefaultConnection();
    }

    private function allEmpty(): bool
    {
        return empty($this->attributes)
            && empty($this->oldAttributes);
    }

    private function extractRelationSelects(): void
    {
        $selects = $this->queryBuilder->getCommand('select');
        if (!is_array($selects)) {
            return;
        }

        foreach ($this->with as $with) {
            foreach ($selects as $key => $select) {
                if (str_starts_with($select, "{$with}.")) {
                    $this->withSelects[$with][] = str_replace("{$with}.", "", $select);
                    unset($selects[$key]);
                }
            }
        }

        $this->queryBuilder->setCommand('select', array_values($selects));
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

    private function getSchemaFields(): array
    {
        if ($this->tableName === null) {
            return [];
        }

        if ($this->schemaFields === null) {
            $this->schemaFields = Schema::getFields($this->tableName, $this->resolveConnection()->getDb()) ?? [];
        }

        return $this->schemaFields;
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

    private function resolveBuildPayload(): array
    {
        return match ($this->queryBuilder->getCommand('begin')) {
            'INSERT INTO' => $this->attributes,
            'UPDATE' => $this->getDirtyPersistedAttributes(),
            default => [],
        };
    }

    private function eagerLoadRelation(
        array $objects,
        string $relationName,
        RelationDefinition $definition,
        ?Connection $connection = null
    ): void {
        if (isset($this->withSelects[$relationName])) {
            $definition->select($this->withSelects[$relationName]);
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

    private function materializeDefinedRelation(RelationDefinition $definition, string $relationName): mixed
    {
        if (isset($this->withSelects[$relationName])) {
            $definition->select($this->withSelects[$relationName]);
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

}
