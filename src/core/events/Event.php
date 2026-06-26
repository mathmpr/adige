<?php

namespace Adige\core\events;

class Event
{
    private static array $events = [];

    public static function on(string $class, string $event, callable $handler): void
    {
        if (!isset(self::$events[$class])) {
            self::$events[$class] = [];
        }
        if (!isset(self::$events[$class][$event])) {
            self::$events[$class][$event] = [];
        }
        self::$events[$class][$event][] = $handler;
    }

    public static function trigger(string $class, string $event, mixed $emitter, ...$args): void
    {
        foreach (self::resolveEventClasses($class) as $eventClass) {
            if (!isset(self::$events[$eventClass][$event])) {
                continue;
            }

            foreach (self::$events[$eventClass][$event] as $handler) {
                call_user_func_array($handler, [$emitter, ...$args]);
            }
        }
    }

    public static function clear(?string $class = null, ?string $event = null): void
    {
        if ($class === null) {
            self::$events = [];
            return;
        }

        if ($event === null) {
            unset(self::$events[$class]);
            return;
        }

        unset(self::$events[$class][$event]);

        if (empty(self::$events[$class])) {
            unset(self::$events[$class]);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function resolveEventClasses(string $class): array
    {
        return array_values(array_unique([
            $class,
            ...array_values(class_parents($class) ?: []),
            ...array_values(class_implements($class) ?: []),
        ]));
    }
}
