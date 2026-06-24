<?php

namespace Adige\core\database;

class RelationDefinition
{
    public const TYPE_ONE = 'one';
    public const TYPE_MANY = 'many';

    private string $type;

    private string $relatedModelClass;

    private string $localKey;

    private string $foreignKey;

    private ?Connection $connection;

    private array $select = ['*'];

    public function __construct(
        string $type,
        string $relatedModelClass,
        string $localKey,
        string $foreignKey,
        ?Connection $connection = null
    ) {
        $this->type = $type;
        $this->relatedModelClass = $relatedModelClass;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->connection = $connection;
    }

    public function select(array $fields): self
    {
        $this->select = $fields;
        return $this;
    }

    public function applyRuntimeConnection(?Connection $connection = null): self
    {
        if ($connection !== null) {
            $this->connection = $connection;
        }

        return $this;
    }

    public function isHasOne(): bool
    {
        return $this->type === self::TYPE_ONE;
    }

    public function isHasMany(): bool
    {
        return $this->type === self::TYPE_MANY;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getRelatedModelClass(): string
    {
        return $this->relatedModelClass;
    }

    public function createQuery(array $localValues, ?Connection $connection = null): ActiveRecord
    {
        $relatedModelClass = $this->relatedModelClass;
        $connection = $connection ?? $this->connection;
        $query = $relatedModelClass::find($connection)
            ->select($this->getQuerySelectFields());

        $field = ':tableName.' . $this->foreignKey;
        if (count($localValues) === 1) {
            return $query->where([
                $field => array_values($localValues)[0]
            ]);
        }

        return $query->whereIn([
            $field => array_values($localValues)
        ]);
    }

    private function getQuerySelectFields(): array
    {
        if (in_array('*', $this->select, true)) {
            return $this->select;
        }

        if (in_array($this->foreignKey, $this->select, true)) {
            return $this->select;
        }

        return array_values([...$this->select, $this->foreignKey]);
    }
}
