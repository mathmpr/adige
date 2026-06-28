<?php

namespace Adige\core\database;

class MigrationIndex
{
    private ?string $name = null;

    /**
     * @var string[]
     */
    private array $columns = [];

    private bool $unique = false;

    public function __construct(array|string $columns, ?string $name = null)
    {
        $this->columns(is_string($columns) ? [$columns] : $columns);
        $this->name = $name;
    }

    public function columns(array $columns): self
    {
        $this->columns = array_values(array_filter(
            $columns,
            static fn (mixed $column): bool => is_string($column) && trim($column) !== ''
        ));

        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function name(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }
}
