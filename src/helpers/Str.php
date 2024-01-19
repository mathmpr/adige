<?php

namespace Adige\helpers;

use voku\helper\ASCII;

class Str
{
    private static array $cache = [];

    public static function snake($value, $delimiter = '_'): string
    {
        if ($cache = static::getCache(__FUNCTION__, $value, $delimiter)) {
            return $cache;
        }
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return static::setCache(__FUNCTION__, $value, $value, $delimiter);
    }

    public static function lower($value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function kebab($value): string
    {
        if ($cache = static::getCache(__FUNCTION__, $value)) {
            return $cache;
        }
        return self::setCache(__FUNCTION__, $value, static::snake($value, '-'));
    }

    public static function camel($value)
    {
        if ($cache = static::getCache(__FUNCTION__, $value)) {
            return $cache;
        }
        return self::setCache(__FUNCTION__, $value, lcfirst(static::studly($value)));
    }

    public static function studly($value)
    {
        if ($cache = static::getCache(__FUNCTION__, $value)) {
            return $cache;
        }
        $words = explode(' ', static::replace(['-', '_'], ' ', $value));
        $studlyWords = array_map(fn($word) => static::ucfirst($word), $words);
        return static::setCache(__FUNCTION__, $value, implode($studlyWords));
    }

    public static function replace($search, $replace, $subject, $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, $replace, $subject)
            : str_ireplace($search, $replace, $subject);
    }

    public static function ucfirst($string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    public static function upper($value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function substr($string, $start, $length = null, $encoding = 'UTF-8'): string
    {
        return mb_substr($string, $start, $length, $encoding);
    }

    private static function getCache($method, $value, ...$keys)
    {
        if (!empty($keys)) {
            $value = implode($keys) . $value;
        }
        return static::$cache[$method][$value] ?? null;
    }

    private static function setCache($method, $value, $result, ...$keys)
    {
        if (!empty($keys)) {
            $value = implode($keys) . $value;
        }
        static::$cache[$method][$value] = $result;
        return $result;
    }

    public static function slug($title, $separator = '-', $language = 'en', $dictionary = ['@' => 'at']): string
    {
        $title = $language ? static::ascii($title, $language) : $title;

        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $separator . $value . $separator;
        }

        $title = str_replace(array_keys($dictionary), array_values($dictionary), $title);

        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    public static function transliterate($string, $unknown = '?', $strict = false): string
    {
        return ASCII::to_transliterate($string, $unknown, $strict);
    }

    public static function ascii($value, $language = 'en'): string
    {
        return ASCII::to_ascii((string)$value, $language);
    }

    public static function contains($haystack, $needles, $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

}
