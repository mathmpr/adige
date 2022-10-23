<?php

namespace Adige\cli;

class OutputLn extends Output
{
    public static function __callStatic(string $name, array $arguments)
    {
        $arguments[] = Output::INSTANT;
        foreach ($arguments as &$argument) {
            if ($argument === Output::INSTANT) {
                continue;
            }
            if ($argument === Output::DIE) {
                continue;
            }
            if (is_scalar($argument)) {
                $argument .= "\n";
            }
        }
        return parent::__callStatic($name, $arguments);
    }
}