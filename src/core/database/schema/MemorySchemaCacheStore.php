<?php

namespace Adige\core\database\schema;

class MemorySchemaCacheStore implements SchemaCacheStore
{
    public function load(): array
    {
        return [];
    }

    public function save(array $schema): void
    {
    }
}
