<?php

namespace Adige\core\database\schema;

class FileSchemaCacheStore implements SchemaCacheStore
{
    public function __construct(
        private string $path = './schema.json'
    ) {
    }

    public function load(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        return json_decode(file_get_contents($this->path), true) ?: [];
    }

    public function save(array $schema): void
    {
        file_put_contents($this->path, json_encode($schema));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
