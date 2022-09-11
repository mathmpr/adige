<?php

namespace Adige\cli;

class Console
{
    public $argv;
    public $argc;
    public $command;
    public $command_first;
    public $command_last;
    public $parsed_args = [];

    public function __construct()
    {
        global $_argv, $_argc;
        if (!$_argv) return;
        array_shift($_argv);
        $this->argv = $_argv;
        $this->argc = $_argc - 1;
        if ($this->argc === 0) {
            $this->command_list();
        } else {
            $exp = explode(':', array_shift($this->argv));
            if (count($exp) > 1) {
                $this->command_first = $exp[0];
                $this->command_last = $exp[1];
                $this->command = $this->command_first . '_' . $this->command_last;
            } else {
                $this->command_first = $exp[0];
                $this->command = $this->command_first;
            }

            foreach ($this->argv as $arg) {
                if (ltrim($arg, '-') != $arg) {
                    $exp = explode('=', $arg);
                    $arg_name = array_shift($exp);
                    $arg_name = str_replace(['---', '--', '-'], '', $arg_name);
                    $arg_value = join('=', $exp);
                    if (empty($arg_value)) {
                        $arg_value = true;
                    }
                    $this->parsed_args[$arg_name] = $arg_value;
                }
            }

            if (method_exists($this, $this->command)) {
                $method = new \ReflectionMethod($this, $this->command);
                $params = $method->getParameters();
                $names = [];
                $order = [];
                foreach ($params as $param) {
                    try {
                        $default = $param->getDefaultValue();
                    } catch (\Exception $exception) {
                        if (!array_key_exists($param->getName(), $this->parsed_args)) {
                            die("\n\e[0;31mCommand " . str_replace('_', ':', $this->command) . " require a missing parameter: " . $param->getName() . "\e[0m");
                        }
                    }

                    if (isset($this->parsed_args[$param->getName()])) {
                        $order[$param->getPosition()] = $this->parsed_args[$param->getName()];
                    }

                    $names[] = $param->getName();

                }

                foreach ($this->parsed_args as $arg_name => $value) {
                    if (!in_array($arg_name, $names)) {
                        die("\n\e[0;31mCommand " . str_replace('_', ':', $this->command) . " have a unregognized argument: " . $arg_name . ".\e[0m");
                    }
                }

                call_user_func_array([$this, $this->command], $order);

            } else {
                if ($this->command_last && method_exists($this, $this->command_first)) {
                    echo "\n\e[0;31mCommand " . $this->command_first . " is a possible command, but subcommand " . $this->command_last . " is not.\e[0m";
                    $this->command_list(true);
                } else {
                    echo "\n\e[0;31mCommand " . $this->command_first . ":" . $this->command_last . " is a not possible command.\e[0m";
                    $this->command_list(true);
                }
            }
        }
    }

    private function did_you_say()
    {
        $reflection = new \ReflectionClass($this);
        $methods = $reflection->getMethods();
        $protected = [];
        foreach ($methods as $method) {
            if ($method->isProtected()) $protected[] = $method->getName();
        }

        $percent = 0;

        $says = '';

        foreach ($protected as $name) {
            similar_text($this->command, $name, $percent);
            if ($percent >= 75) {
                $says .= "\n* " . str_replace('_', ':', $name);
            }
        }

        if (!empty($says)) {
            echo "\n\n\e[1;34m********** Did you say ************";
            echo "\n*";
            echo $says;
            echo "\n*";
            echo "\n***********************************\e[0m\n";
        }

    }

    private function command_list($check_possibles = false)
    {
        if ($check_possibles) {
            echo "\n\n\e[1;32mCheck possible commands below.\e[0m";
            $this->did_you_say();
        }
        die(file_get_contents(__DIR__ . '/cli_commands.txt'));
    }

    public function hello() {
        echo "\n\e[1;32mhello, console works.\e[0m\n";
    }

}
