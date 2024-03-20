<?php

namespace Adige\core\database;

use Adige\core\BaseObject;
use Adige\core\BaseException;
use Adige\helpers\Str;
use Adige\core\collection\Collection;
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
    abstract public static function tableName(): string;

    private array $attributes = [];

    private array $changes = [];

    private array $news = [];

    private array $with = [];

    protected array $hidden = [];

    private array $withSelects = [];

    private array $options;

    private array $needsToArray = [];

    private ?string $tableName = null;

    private ?string $pkName = null;

    private array $queryCommands = [];

    private array $queryParams = [];

    private int $argCount = 0;

    public string $rawSql = '';

    private ?Connection $connection = null;

    private bool $as_array = false;

    /**
     * @param array $props
     * @param array $options
     * @throws BaseException
     */
    public function __construct(array $props = [], array $options = [])
    {
        $this->options = $options;
        $this->init();
        if (!$this->connection) {
            $this->connection = Connection::getDefaultConnection();
        }
        try {
            $class = new ReflectionClass($this);
            $this->tableName = $class->getMethod('tableName')->invoke(null);
        } catch (Throwable $exception) {
            throw new BaseException('Caller no implements ActiveRecord', 0, $exception);
        }
        $this->pkName = Schema::pkName($this->tableName, $this->connection->getDb());
        $this->load($props);
        parent::__construct();
    }

    public function load(array $props = []): void
    {
        $fields = Schema::getFields($this->tableName, $this->connection->getDb());
        foreach ($props as $prop => $value) {
            if (in_array($prop, $fields, true)) {
                $this->{$prop} = $value;
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
        if (method_exists($this, 'get' . Str::camel($name, '_'))) {
            return $this->{'get' . Str::camel($name, '_')}();
        }
        if (method_exists($this, $name) && !isset($this->attributes[$name])) {
            $call = $this->{$name}();
            if ($call instanceof ActiveRecord) {
                $all = $call->all();
                $this->needsToArray[] = $name;
                $this->attributes[$name] = (($call->isHasOne() && count($all) > 0) ? $all[0] : $all);
            }
            if ($call instanceof Collection) {
                $this->attributes[$name] = $call;
            }
        }
        return $this->attributes[$name] ?? null;
    }

    public function __set($name, $value): void
    {
        if (isset($this->attributes[$name])) {
            $this->changes[$name] = $this->attributes[$name];
        }
        if (!isset($this->attributes[$name])) {
            $this->news[$name] = $value;
        }
        $this->attributes[$name] = $value;
    }

    /**
     * @return bool
     * @throws BaseException
     */
    public function save(): bool
    {
        if ($this->{$this->pkName}) {
            $id = array_key_exists($this->pkName, $this->changes)
                ? $this->changes[$this->pkName]
                : $this->{$this->pkName};
            $this->beginUpdate()
                ->update($this->attributes)
                ->where([
                    ':tableName.`:pkName`' => $id
                ])
                ->build();
            $result = $this->connection->update($this->rawSql, $this->queryParams);
            if ($exception = $this->connection->getLastException()) {
                throw new BaseException($exception['message'], 0);
            }
            $this->news = [];
            $this->changes = [];
            return $result;
        } else {
            $this->beginInsert()
                ->build();
            $result = $this->connection->insert($this->rawSql, $this->queryParams);
            if ($exception = $this->connection->getLastException()) {
                throw new BaseException($exception['message'], 0);
            }
            $this->{$this->pkName} = $result;
            $this->news = [];
            $this->changes = [];
            return true;
        }
    }

    /**
     * @return bool|null
     * @throws BaseException
     */
    public function remove(): bool
    {
        $this->beginDelete()
            ->where([
                ':tableName.`:pkName`' => $this->{$this->pkName}
            ])
            ->build();
        $result = $this->connection->query($this->rawSql, $this->queryParams);
        if (!$result) {
            throw new BaseException(
                $this->connection->getLastException(
                )['message'] ?? 'Query ' . $this->rawSql . ' have one or more errors'
                , 0
            );
        }
        return true;
    }

    public function update(array $fields): self
    {
        $this->queryCommands['update'] = $fields;
        if ($this->allEmpty()) {
            $this->load($fields);
        }
        return $this;
    }

    public function select(array $fields): self
    {
        $this->queryCommands['select'] = $fields;
        return $this;
    }

    private function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->queryCommands['join'][] = [
            'type' => $type,
            'join' => [$table, $on]
        ];
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
        $this->queryCommands['where'][] = [
            'command' => 'initial',
            'cond' => $cond,
            'in' => $in
        ];
        return $this;
    }

    public function andWhere(array $cond, bool $in = false): self
    {
        $this->queryCommands['where'][] = [
            'command' => 'AND',
            'cond' => $cond,
            'in' => $in
        ];
        return $this;
    }

    public function orWhere(array $cond, bool $in = false): self
    {
        $this->queryCommands['where'][] = [
            'command' => 'OR',
            'cond' => $cond,
            'in' => $in
        ];
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
        $this->queryCommands['orderBy'] = [trim($cols), 'DESC'];
        return $this;
    }

    public function orderByAsc(string $cols): self
    {
        $this->queryCommands['orderBy'] = [trim($cols), 'ASC'];
        return $this;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public function one(): ?self
    {
        $this->build();
        $result = $this->connection->select($this->rawSql, $this->queryParams);
        if ($this->as_array) {
            return !empty($result) ? new static($result[0]) : null;
        }
        if ($exception = $this->connection->getLastException()) {
            throw new BaseException($exception['message'], 0);
        }
        if (!empty($result)) {
            $object = new static($result[0]);
            $this->populateRelation($object);
            return $object;
        }
        return null;
    }

    /**
     * @return Collection|array|null
     * @throws BaseException
     */
    public function all(): Collection|array|null
    {
        $this->build();
        $result = $this->connection->select($this->rawSql, $this->queryParams);
        if ($this->as_array) {
            return $result;
        }
        $return = Collection::factory();
        if ($exception = $this->connection->getLastException()) {
            throw new BaseException($exception['message'], 0);
        }
        if (!empty($result)) {
            foreach ($result as $item) {
                $object = new static($item);
                $this->populateRelation($object);
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
    private function populateRelation(ActiveRecord $object): void
    {
        $class = new ReflectionClass($object);
        $methods = [];
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $methods[] = $method->getName();
            }
        }
        foreach ($this->with as $relation) {
            if (in_array($relation, $methods)) {
                if (isset($this->withSelects[$relation])) {
                    $call = $object->{$relation}()
                        ->select($this->withSelects[$relation]);
                    $all = $call->all();
                    $object->needsToArray[] = $relation;
                    $object->{$relation} = (($call->isHasOne() && count($all) > 0) ? $all[0] : $all);
                } else {
                    $object->{$relation};
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
    public function build(): self
    {
        $this->argCount = 0;
        $this->queryParams = [];
        if (isset($this->queryCommands['begin'])) {
            switch ($this->queryCommands['begin']) {
                case 'INSERT INTO':
                    $this->rawSql = trim(
                        sprintf(
                            "%s {$this->tableName} %s",
                            $this->queryCommands['begin'],
                            $this->queryInsert,
                        )
                    );
                    break;
                case 'SELECT':
                    $this->rawSql = trim(
                        sprintf(
                            "%s %s %s FROM {$this->tableName} %s %s %s %s",
                            $this->queryCommands['begin'],
                            $this->queryDistinct,
                            $this->querySelect,
                            $this->queryAlias,
                            $this->queryJoin,
                            $this->queryWhere,
                            $this->queryOrder
                        )
                    );
                    break;
                case 'UPDATE':
                    $this->rawSql = trim(
                        sprintf(
                            "%s {$this->tableName} %s SET %s %s",
                            $this->queryCommands['begin'],
                            $this->queryAlias,
                            $this->queryUpdate,
                            $this->queryWhere,
                        )
                    );
                    break;
                case 'DELETE FROM':
                    $this->rawSql = trim(
                        sprintf(
                            "%s {$this->tableName} %s %s",
                            $this->queryCommands['begin'],
                            $this->queryAlias,
                            $this->queryWhere,
                        )
                    );
                    break;
            }
        }

        return $this;
    }

    /**
     * @return bool|null
     * @throws BaseException
     */
    public function execute(): ?bool
    {
        $this->build();
        $result = $this->connection->query($this->rawSql, $this->queryParams);
        if (!$result) {
            throw new BaseException(
                $this->connection->getLastException(
                )['message'] ?? 'Query ' . $this->rawSql . ' have one or more errors'
                , 0
            );
        }
        return true;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function find(): self
    {
        return (new static([]))->beginSelect();
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasMany(): self
    {
        return (new static([], [
            'many' => true
        ]))->beginSelect();
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function hasOne(): self
    {
        return (new static([], [
            'one' => true
        ]))->beginSelect();
    }

    /**
     * @param $id
     * @return static|null
     * @throws BaseException
     */
    public static function findById($id): ?self
    {
        return static::find()
            ->select(['*'])
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->one();
    }

    /**
     * @param $cond
     * @return static|null
     * @throws BaseException
     */
    public static function findAll($cond): ?self
    {
        if (is_scalar($cond)) {
            return static::findById($cond);
        }
        if (is_array($cond)) {
            return static::find()
                ->select(['*'])
                ->where($cond)
                ->one();
        }
        return null;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function put(): self
    {
        return (new static([]))->beginUpdate();
    }

    /**
     * @param $cond
     * @param array $fields
     * @return static
     * @throws BaseException
     */
    public static function putAll($cond, array $fields): self
    {
        $model = (new static($fields));
        if (is_scalar($cond)) {
            $model->{$model->pkName} = $cond;
            $model->save();
            return $model;
        }
        $model->beginUpdate()
            ->update($fields)
            ->where($cond)
            ->execute();
        return $model;
    }

    /**
     * @param $id
     * @param array $fields
     * @return static
     * @throws BaseException
     */
    public static function putById($id, array $fields): self
    {
        $model = (new static($fields));
        $model->{$model->pkName} = $id;
        $model->save();
        return $model;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function create(array $fields): self
    {
        $model = (new static($fields));
        $model->save();
        return $model;
    }

    /**
     * @return static
     * @throws BaseException
     */
    public static function delete(): self
    {
        return (new static([]))->beginDelete();
    }

    /**
     * @param $cond
     * @return bool
     * @throws BaseException
     */
    public static function deleteAll($cond): bool
    {
        if (is_scalar($cond)) {
            return static::deleteById($cond);
        }
        if (is_array($cond)) {
            return static::delete()
                ->where($cond)
                ->execute();
        }
        return false;
    }

    /**
     * @param $id
     * @return bool
     * @throws BaseException
     */
    public static function deleteById($id): bool
    {
        return static::delete()
            ->where([
                ':tableName.`:pkName`' => $id
            ])
            ->execute();
    }

    public function beginSelect(): self
    {
        $this->queryCommands['begin'] = 'SELECT';
        return $this;
    }

    public function beginUpdate(): self
    {
        $this->queryCommands['begin'] = 'UPDATE';
        return $this;
    }

    public function beginDelete(): self
    {
        $this->queryCommands['begin'] = 'DELETE FROM';
        return $this;
    }

    public function beginInsert(): self
    {
        $this->queryCommands['begin'] = 'INSERT INTO';
        return $this;
    }

    private function addQueryParam($value): string
    {
        $queryKey = '';
        if (is_array($value)) {
            $queryKey .= '(';
            foreach ($value as $item) {
                $key = "arg{$this->argCount}";
                $queryKey .= ":{$key}, ";
                $this->queryParams[$key] = $item;
                $this->argCount++;
            }
            return rtrim($queryKey, ', ') . ')';
        }
        $queryKey .= "arg{$this->argCount}";
        $this->queryParams[$queryKey] = $value;
        $this->argCount++;
        return ":" . $queryKey;
    }

    private function parseField(string $field): string
    {
        return str_replace(
            [
                ':tableName',
                ':pkName'
            ],
            [
                $this->tableName,
                $this->pkName
            ],
            $field
        );
    }

    public function getQueryInsert(): string
    {
        $fields = '(';
        $args = '(';
        $schema = Schema::getFields($this->tableName, $this->connection->getDb());
        foreach ($this->attributes as $attribute => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (in_array($attribute, $schema, true)) {
                $fields .= "`{$attribute}`, ";
                $args .= "{$this->addQueryParam($value)}, ";
            }
        }
        $fields = rtrim($fields, ", ") . ")";
        $args = rtrim($args, ", ") . ")";
        if ($args != "()") {
            return "{$fields} VALUES{$args}";
        }
        return "";
    }

    private function getQueryUpdate(): string
    {
        $update = '';
        if (isset($this->queryCommands['update'])) {
            foreach ($this->queryCommands['update'] as $field => $value) {
                if ((array_key_exists($field, $this->changes)
                    || array_key_exists($field, $this->news))) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $update .= "{$field}={$this->addQueryParam($value)}, ";
                }
            }
        }
        return rtrim($update, ', ');
    }

    private function getQuerySelect(): string
    {
        if (isset($this->queryCommands['select'])) {
            foreach ($this->with as $with) {
                foreach ($this->queryCommands['select'] as $key => $select) {
                    if (str_starts_with($select, "{$with}.")) {
                        $this->withSelects[$with][] = str_replace("{$with}.", "", $select);
                        unset($this->queryCommands['select'][$key]);
                    }
                }
            }
            foreach ($this->queryCommands['select'] as &$select) {
                $select = $this->tableName . "." . $select;
            }
            return rtrim(join(', ', $this->queryCommands['select']), ', ');
        }
        return '*';
    }

    /**
     * @return string
     * @throws BaseException
     */
    private function getQueryWhere(): string
    {
        $open = false;
        $whereString = '';
        if (isset($this->queryCommands['where'])) {
            foreach ($this->queryCommands['where'] as $where) {
                $cond = $where['cond'];
                $field = array_keys($cond)[0];
                $value = $cond[$field];
                $conditionSymbol = '=';
                if (!is_string($field) && !$where['in']) {
                    $field = $cond[0];
                    $conditionSymbol = $cond[1];
                    $value = $cond[2];
                }
                if ($where['in']) {
                    $conditionSymbol = ' IN ';
                }
                $field = $this->parseField($field);
                switch ($where['command']) {
                    case 'initial':
                        $whereString .= "WHERE {$field}{$conditionSymbol}" . $this->addQueryParam($value);
                        break;
                    case 'AND':
                        $whereString .= ($open ? ")" : "") .
                            " AND ({$field}{$conditionSymbol}" . $this->addQueryParam($value);
                        $open = true;
                        break;
                    case 'OR':
                        $whereString .= " OR ({$field}{$conditionSymbol}" . $this->addQueryParam($value);
                        break;
                    default:
                        throw new BaseException("No recognized WHERE command {$where['command']}");
                }
            }
        }
        return $open
            ? $whereString . ')'
            : $whereString;
    }

    private function getQueryJoin(): string
    {
        $joinString = '';
        if (isset($this->queryCommands['join'])) {
            foreach ($this->queryCommands['join'] as $join) {
                $joinString .= "{$join['type']} JOIN {$join['join'][0]} ON {$this->parseField($join['join'][1])}";
            }
        }
        return $joinString;
    }

    private function getQueryOrder(): string
    {
        $orderBy = '';
        if (isset($this->queryCommands['orderBy'])) {
            $orderBy .= "ORDER BY {$this->queryCommands['orderBy'][0]} {$this->queryCommands['orderBy'][1]}";
        }
        return $orderBy;
    }

    private function getQueryAlias(): string
    {
        return isset($this->queryCommands['alias'])
            ? " AS {$this->queryCommands['alias']}"
            : "";
    }

    private function getQueryDistinct(): string
    {
        return $this->queryCommands['distinct'] ?? '';
    }

    private function allEmpty(): bool
    {
        return empty($this->attributes)
            && empty($this->changes)
            && empty($this->news);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        foreach ($this->needsToArray as $attr) {
            $this->{$attr} = $this->{$attr}->toArray();
        }
        $attrs = $this->getAttributes();
        if (!empty($this->hidden)) {
            foreach ($this->hidden as $key) {
                unset($attrs[$key]);
            }
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

}