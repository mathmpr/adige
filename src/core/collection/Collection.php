<?php

namespace Adige\core\collection;

use Adige\core\BaseObject;
use ArrayIterator;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

class Collection extends BaseObject implements IteratorAggregate, ArrayAccess, Countable
{
    private ?int $position = null;

    private array $collection = [];

    private array $hashes = [];

    private array $objectOffsets = [];

    private array $arrayOffsets = [];
    /**
     * @param array $collection
     *
     * @return Collection
     */
    public static function factory(array $collection = []): Collection
    {
        return new Collection($collection);
    }

    /**
     * Collection constructor.
     *
     * @param array $collection
     */
    public function __construct(array $collection = [])
    {
        $this->position = 0;
        $this->setCollection($collection);
        parent::__construct();
    }

    public function getCollection(): array
    {
        return $this->collection;
    }

    /**
     * put an array inside collection object
     * @param array $collection
     * @return Collection
     */
    public function setCollection(array $collection = []): Collection
    {
        if (is_array($collection)) {
            $this->collection = $collection;
        } else {
            if (is_object($collection)) {
                $this->collection = (array)$collection;
            } else {
                $this->collection[] = $collection;
            }
        }
        return $this;
    }

    /**
     * return offset has string if offset is an object
     * @param $offset
     * @return bool|string
     */
    public function stringOffset($offset): bool|string
    {
        if ($this->keyExists($offset)) {
            if (is_object($offset)) {
                $offset = spl_object_hash($offset);
            }
            if (is_array($offset)) {
                $offset = serialize($offset);
            }
            return $offset;
        }
        return false;
    }

    /**
     * return offset has object if offset exists inside $this->objectOffsets
     * @param $offset
     * @return bool|mixed|string
     */
    public function offset($offset): mixed
    {
        if ($this->keyExists($offset)) {
            if (is_object($offset)) {
                $offset = spl_object_hash($offset);
            }

            if (array_key_exists($offset, $this->objectOffsets)) {
                return $this->objectOffsets[$offset];
            }

            if (is_array($offset)) {
                $offset = serialize($offset);
            }

            if (array_key_exists($offset, $this->arrayOffsets)) {
                return $this->arrayOffsets[$offset];
            }

            return $offset;
        }
        return false;
    }

    /**
     * check if offset exists inside collection
     * @param $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        if (is_object($offset)) {
            $offset = spl_object_hash($offset);
        }
        if (is_array($offset)) {
            $offset = serialize($offset);
        }
        if (array_key_exists($offset, $this->collection)) {
            return true;
        }
        return false;
    }

    /**
     * check if offset exists inside collection
     * @param $offset
     * @return bool
     */
    public function keyExists($offset): bool
    {
        return $this->offsetExists($offset);
    }

    /**
     * return a value linked with offset if offset exists
     * @param $offset
     * @return bool|mixed
     */
    public function offsetGet($offset): mixed
    {
        $offset = $this->stringOffset($offset);
        if (array_key_exists($offset, $this->collection)) {
            return $this->collection[$offset];
        }
        return false;
    }

    /**
     * set value linked to the offset, can be an object
     * if value is an object, the value is the only one inside collection
     * other equal object can't be pass to set() ou offsetSet()
     * @param $offset
     * @param $value
     */
    public function offsetSet($offset, $value): void
    {
        if (is_object($offset)) {
            $_offset = spl_object_hash($offset);
            $this->objectOffsets[$_offset] = $offset;
            $offset = $_offset;
        }
        if (is_array($offset)) {
            $_offset = serialize($offset);
            $this->arrayOffsets[$_offset] = $offset;
            $offset = $_offset;
        }

        if (is_scalar($offset)) {
            $this->collection[$offset] = $value;
        } else {
            if (!$offset) {
                $this->collection[] = $value;
            }
        }
    }

    /**
     * unset value liked with offset, can be an object
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $offset = $this->stringOffset($offset);
        if (array_key_exists($offset, $this->collection)) {
            if (is_object($this->collection[$offset])) {
                $hash = spl_object_hash($this->collection[$offset]);
                $search = array_search($hash, $this->hashes, true);
                if ($search || is_int($search)) {
                    unset($this->hashes[$search]);
                }
            }
            unset($this->collection[$offset]);
            if (array_key_exists($offset, $this->objectOffsets)) {
                unset($this->objectOffsets[$offset]);
            }
            if (array_key_exists($offset, $this->arrayOffsets)) {
                unset($this->arrayOffsets[$offset]);
            }

            if ($this->position !== null && $this->position >= $this->count()) {
                $this->position = max(0, $this->count() - 1);
            }
        }
    }

    /**
     * check if offset is valid
     * @return bool
     */
    function valid(): bool
    {
        $keys = $this->iterationKeys();

        return $this->position !== null
            && $this->position >= 0
            && $this->position < count($keys);
    }

    /**
     * return current key of collection
     * @return int|mixed|string|null
     */
    function key(): mixed
    {
        $keys = $this->iterationKeys();

        if (!$this->valid()) {
            return null;
        }

        return $this->offset($keys[$this->position]);
    }

    /**
     * go to next position in collection
     * @return bool|mixed
     */
    public function next(): void
    {
        if ($this->position === null) {
            $this->position = 0;
            return;
        }

        $this->position++;
    }

    /**
     * go to prev element in collection
     * @return bool|mixed
     */
    public function prev(): mixed
    {
        if ($this->position === null || $this->position <= 0) {
            $this->position = 0;
            return false;
        }

        $this->position--;

        return $this->current() ?: false;
    }

    /**
     * return current element of internal pointer of collection
     * @return mixed
     */
    public function current(): mixed
    {
        $keys = $this->iterationKeys();

        if (!$this->valid()) {
            return false;
        }

        return $this->collection[$keys[$this->position]] ?? false;
    }

    /**
     * reset position of collection
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * check if value exists inside collection, value can be an object
     * @param $value
     * @return bool
     */
    public function contains($value): bool
    {
        if (is_object($value)) {
            return in_array(spl_object_hash($value), $this->hashes);
        }
        return in_array($value, $this->collection);
    }

    /**
     * return first key of collection
     * @return string|int|null|array|object|bool
     */
    public function firstKey(): string|int|null|array|object|bool
    {
        return array_key_first($this->collection);
    }

    /**
     * return last key of collection
     * @return string|int|null|array|object|bool
     */
    public function lastKey(): string|int|null|array|object|bool
    {
        return array_key_last($this->collection);
    }

    /**
     * return last element of collection
     * @return string|int|null|array|object|bool
     */
    public function end(): string|int|null|array|object|bool
    {
        $key = $this->lastKey();
        if ($key !== null) {
            return $this->offsetGet($key);
        }
        return false;
    }

    /**
     * return fist element of collection
     * @return string|int|null|array|object|bool
     */
    public function begin(): string|int|null|array|object|bool
    {
        $key = $this->firstKey();
        if ($key !== null) {
            return $this->offsetGet($key);
        }
        return false;
    }

    /**
     * return count of elements of collection
     * @return int
     */
    public function count(): int
    {
        return count($this->collection);
    }

    /**
     * check if collection is empty
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !($this->count() > 0);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->collection as $item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $array[] = $item->toArray();
            } else {
                $array[] = $item;
            }
        }
        return $array;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->collection);
    }

    /**
     * @return array<int, int|string>
     */
    private function iterationKeys(): array
    {
        return array_keys($this->collection);
    }

}
