<?php

namespace Adige\file;

class File {

    private string $location;
    private ?string $name;
    private ?string $fullName;
    private ?string $extension;
    private ?string $mimeType;

    public function __construct($location)
    {
        $this->location = str_replace('\\', '/', $location);
        $mimeType = mime_content_type($this->location);
        $this->mimeType = $mimeType ?? null;
        $extension = explode('.', $this->location);
        $this->extension = strtolower(array_pop($extension));
        $name = explode('/', join('.', $extension));
        $this->name = array_pop($name);
        $this->fullName = $this->name . '.' . $this->extension;
    }

    public function exists() :bool {
        return file_exists($this->location) && is_file($this->location);
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return false|string|null
     */
    public function getMimeType(): bool|string|null
    {
        return $this->mimeType;
    }

    /**
     * @return false|string|null
     */
    public function getExtension(): bool|string|null
    {
        return $this->extension;
    }

    /**
     * @return string|null
     */
    public function getFullName(): ?string
    {
        return $this->fullName;
    }

}