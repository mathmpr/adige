<?php

namespace Adige\core\database\schema;

interface SchemaCacheStore
{
    public function load(): array;

    public function save(array $schema): void;
}
