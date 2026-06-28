<?php

namespace Adige\core;

trait EventHandler
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
                call_user_func_array($handler, $args);
            }
        }
    }
}