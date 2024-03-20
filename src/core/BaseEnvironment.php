<?php

namespace Adige\core;

use Adige\file\File;

class BaseEnvironment extends BaseObject
{
    const SO_WINDOWS = 'windows';
    const SO_LINUX = 'linux';
    const SO_MAC = 'mac';
    const SO_FREEBSD = 'freebsd';
    const SO_SOLARIS = 'solaris';

    private static array $env = [];

    public static function getSO(): string
    {
        return strtolower(PHP_OS);
    }

    public static function isWindows(): bool
    {
        return str_starts_with(self::getSO(), 'win')
            ? self::SO_WINDOWS
            : false;
    }

    public static function isLinux(): bool
    {
        return str_starts_with(self::getSO(), 'linux')
            ? self::SO_LINUX
            : false;
    }

    public static function isMac(): bool
    {
        return str_starts_with(self::getSO(), 'darwin')
            ? self::SO_MAC
            : false;
    }

    public static function isFreeBSD(): bool
    {
        return str_starts_with(self::getSO(), 'freebsd')
            ? self::SO_FREEBSD
            : false;
    }

    public static function isSolaris(): bool
    {
        return str_starts_with(self::getSO(), 'sunos')
            ? self::SO_SOLARIS
            : false;
    }

    /**
     * @throws \Exception
     */
    public static function readEnv(string $path = '/.env'): void
    {
        $file = new File($path);
        if ($file->exists()) {
            $file->forEachLine(function ($line) {
                $line = trim($line);
                if (!empty($line) && !str_starts_with($line, '#')) {
                    $line = explode('=', $line);
                    $key = trim($line[0]);
                    $value = trim($line[1]);
                    $value = trim(explode('#', $value)[0]);
                    if (!empty($key) && !empty($value)) {
                        $_ENV[$key] = determine_var($value);
                        self::$env[$key] = $value;
                    }
                }
            });
        } else {
            throw new BaseException('Env file not found in path: ' . $path);
        }
    }

    public static function getEnv(string $key): bool|int|float|string|null
    {
        return self::$env[$key] ?? null;
    }

    public static function setEnv(string $key, string $value): void
    {
        self::$env[$key] = $value;
    }

}