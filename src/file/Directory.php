<?php

namespace Adige\file;

class Directory
{
    private string $location;
    private string $name;
    private array $content = [];
    private bool $alreadyRead = false;

    const COMPACT_ALL = 'all';
    const COMPACT_FILES = File::class;
    const COMPACT_DIRECTORIES = Directory::class;

    public function __construct($location, $createIfNotExists = false)
    {
        $this->location = str_replace('\\', '/', $location);
        $name = explode('/', $this->location);
        $this->name = array_pop($name);
        if (!$this->exists() && $createIfNotExists) {
            return $this->create();
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @param array $content
     * @return Directory
     */
    public function setContent(array $content): Directory
    {
        $this->content = $content;
        return $this;
    }

    public function exists(): bool
    {
        return is_dir($this->location);
    }

    public function addContent($name): void
    {
        $location = str_ends_with($this->getLocation(), '/') ? ($this->getLocation() . $name) : ($this->getLocation() . '/' . $name);
        if (is_dir($location)) {
            $this->content[] = (new Directory($location))->read();
        } else {
            $this->content[] = new File($location);
        }
    }

    public function read(): Directory
    {
        $this->alreadyRead = true;
        if ($this->exists()) {
            $scan = scandir($this->getLocation());
            foreach ($scan as $value) {
                if (!in_array($value, ['.', '..'])) {
                    $this->addContent($value);
                }
            }
        }
        return $this;
    }

    /**
     * @param string $get
     * @param array $extensions
     * @return Directory[] | File[] | array
     */
    public function compact(string $get = Directory::COMPACT_ALL, array $extensions = []): array
    {
        $result = [];
        if (!$this->alreadyRead) {
            $this->read();
        }
        foreach ($this->content as $item) {
            $recursion = false;
            if (get_class($item) === Directory::class) {
                $recursion = $item->compact($get, $extensions);
            }
            if (get_class($item) === Directory::class && ($get === Directory::COMPACT_DIRECTORIES || $get === Directory::COMPACT_ALL)) {
                $result[] = $item;
            }
            if (get_class($item) === File::class && ($get === Directory::COMPACT_FILES || $get === Directory::COMPACT_ALL)) {
                if (empty($extensions) || in_array($item->getExtension(), $extensions)) {
                    $result[] = $item;
                }
            }
            if ($recursion) {
                $result = array_merge($result, $recursion);
            }
        }
        return $result;
    }

    public function create(): Directory
    {
        return $this;
    }

}