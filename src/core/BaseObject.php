<?php

namespace Adige\core;

use ReflectionClass;

class BaseObject
{

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }
        return null;
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        return null;
    }

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }
    }

    /**
     * return class name based in self object without namespace
     * @return string
     */
    public function getClassShortName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function getClassFullName(): string
    {
        return (new ReflectionClass($this))->getName();
    }

    /**
     * return class name based in static caller
     * @return string
     */
    public static function getCallerName(): string
    {
        return get_called_class();
    }
}