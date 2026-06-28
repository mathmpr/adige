<?php

namespace Adige\core\events;

trait Observable
{
    protected array $events = [];

    public function on(string $event, callable $handler): void
    {
        $this->events[$event][] = $handler;
    }

    public function trigger(string $event, ...$args): void
    {
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $handler) {
                call_user_func_array($handler, [$this, ...$args]);
            }
        }
        Event::trigger(static::class, $event, $this, ...$args);
    }
}
