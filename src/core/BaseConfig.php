<?php

namespace Adige\core;

class BaseConfig extends BaseObject
{
    private static array $configs = [];

    private array $config = [];

    public function __construct(string $name, array $config = [])
    {
        $this->config = $config;
        self::$configs[$name] = $this;
        parent::__construct();
    }

    public static function config($name): ?BaseConfig
    {
        return self::$configs[$name] ?? null;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        foreach ($keys as $k) {
            if (isset($config[$k])) {
                $config = &$config[$k];
                continue;
            }
            return $default;
        }
        return $config;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        foreach ($keys as $i => $k) {
            if (isset($config[$k])) {
                $config = &$config[$k];
                continue;
            }
            if (is_array($config) && $i === count($keys) - 1) {
                $config[$k] = $value;
                return;
            }
            return;
        }
    }

}