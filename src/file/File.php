<?php

namespace Adige\file;

use Adige\core\BaseObject;

class File extends BaseObject
{
    private string $location;
    private ?string $name;
    private ?string $fullName;
    private ?string $extension;
    private ?string $mimeType;

    public function __construct($location)
    {
        $this->location = str_replace('\\', '/', $location);
        $this->mimeType = file_exists($this->location)
            ? mime_content_type($this->location)
            : null;
        $extension = explode('.', $this->location);
        $this->extension = strtolower(array_pop($extension));
        $name = explode('/', join('.', $extension));
        $this->name = array_pop($name);
        $this->fullName = $this->name . '.' . $this->extension;
        parent::__construct();
    }

    public function exists(): bool
    {
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
        if ($this->mimeType === null && $this->exists()) {
            $this->mimeType = mime_content_type($this->location);
        }
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

    public function read(): string
    {
        return file_get_contents($this->location);
    }

    public function write(string $content): bool
    {
        return file_put_contents($this->location, $content) !== false;
    }

    public function delete(): bool
    {
        return unlink($this->location);
    }

    public function copy(string $destination): bool
    {
        return copy($this->location, $destination);
    }

    public function move(string $destination): bool
    {
        $result = rename($this->location, $destination);
        if ($result) {
            $this->location = $destination;
        }
        return $result;
    }

    public function forEachLine($callback = null): void
    {
        $file = fopen($this->location, 'r');
        while (!feof($file)) {
            $line = fgets($file);
            if (is_callable($callback)) {
                $callback($line);
            }
        }
        fclose($file);
    }

}