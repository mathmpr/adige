<?php

namespace Adige\core\database;

class MigrationField
{
    private string $name;
    private ?string $type = null;
    private bool $nullable = true;
    private bool $unique = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $primary = false;
    private bool $autoIncrement = false;
    private int $length = 255;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function notNull(): self
    {
        $this->nullable = false;
        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;
        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;
        $this->hasDefault = true;
        return $this;
    }

    public function primary(bool $truth = true): self
    {
        $this->primary = $truth;

        if ($truth) {
            $this->nullable = false;
        }

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function integer(): self
    {
        return $this->type('INTEGER');
    }

    public function string(int $length = 255): self
    {
        return $this->type("VARCHAR($length)");
    }

    public function text(): self
    {
        return $this->type('TEXT');
    }

    public function boolean(): self
    {
        return $this->type('BOOLEAN');
    }

    public function timestamp(): self
    {
        return $this->type('TIMESTAMP');
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;

        if ($value) {
            $this->primary();
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function length(int $length): self
    {
        $this->length = $length;
        return $this;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function tinyInt(): self
    {
        return $this->type('TINYINT')
            ->length(2);
    }
}
