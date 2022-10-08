<?php

namespace Adige\helpers;

use voku\helper\ASCII;

class Str
{
    private static array $camelCache = [];
    private static array $camelToSnakeCache = [];
    private static array $camelToKebabCache = [];

    public static function camel($value)
    {
        $key = $value;
        if (isset(static::$camelCache[$key])) {
            return static::$camelCache[$key];
        }
        $value = ASCII::to_ascii($value);
        preg_match_all('/((?:^|[A-Za-z0-9])[a-z]+)/', $value, $matches);
        $join = array_map(function ($el) {
            return ucfirst(strtolower($el));
        }, $matches[0]);
        return static::$camelCache[$key] = lcfirst(join('', $join));
    }

    public static function camelToSnake($value): string
    {
        $key = $value;
        if (isset(static::$camelToSnakeCache[$key])) {
            return static::$camelToSnakeCache[$key];
        }
        return static::$camelToSnakeCache[$key] =  strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    public static function camelToKebab($value): string
    {
        $key = $value;
        if (isset(static::$camelToKebabCache[$key])) {
            return static::$camelToKebabCache[$key];
        }
        return static::$camelToKebabCache[$key] =  strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }

}
