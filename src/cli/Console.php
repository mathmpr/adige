<?php

namespace Adige\cli;

use Adige\cli\exceptions\AlreadyRegisteredCommandException;
use Adige\cli\exceptions\ClassNotExistsException;
use Adige\cli\exceptions\MethodIsNotStaticException;
use Adige\cli\exceptions\MethodNotExistsException;
use Adige\file\Directory;
use JetBrains\PhpStorm\NoReturn;
use ReflectionClass;

class Console
{
    public array $argv;
    public int $argc;
    public string $command;
    public string $commandFirst;
    public string $commandLast = '';
    public array $parsedArgs = [];

    /**
     * @var Command[]
     */
    public static array $commandList = [];

    const DEFAULT_COMMAND = 'default';
    const NOT_DEFAULT_COMMAND = 'not default';

    public function __construct(array $readDirs = [])
    {
        global $_argv, $_argc;
        if (!$_argv) {
            return;
        }
        $readDirs = array_unique(array_merge($readDirs, [
            ROOT . 'app/',
            ROOT . 'src/',
        ]));
        array_shift($_argv);
        $this->argv = $_argv;
        $this->argc = $_argc - 1;

        foreach ($readDirs as $dir) {
            if (is_file($dir) && file_exists($dir)) {
                include_once $dir;
                continue;
            }
            $directory = new Directory($dir);
            $extensions = ['php'];
            foreach ($directory->compact(Directory::COMPACT_FILES, $extensions) as $file) {
                if ($file->getName() === 'Cli') {
                    include_once $file->getLocation();
                }
            }
        }

        if ($this->argc === 0) {
            $this->commandList();
        } else {
            $exp = explode(':', array_shift($this->argv));
            $this->commandFirst = $exp[0];
            if (count($exp) > 1) {
                $this->commandLast = $exp[1];
                $this->command = $this->commandFirst . ':' . $this->commandLast;
            } else {
                $this->command = $this->commandFirst;
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
                    $this->parsedArgs[$arg_name] = $arg_value;
                }
            }

            $foundedCommand = null;

            foreach (static::$commandList as $key => $command) {
                if (str_starts_with($key, $this->commandFirst)) {
                    $exp = explode(':', $key);
                    $main = array_shift($exp);
                    if ($this->commandFirst === $main) {
                        $this->commandFirst = !empty(end($exp)) ? end($exp) : $main;
                        if ($command->isDefault()) {
                            $foundedCommand = $command;
                        }
                    }
                }
            }

            if (array_key_exists($this->command, static::$commandList) || $foundedCommand) {
                if (!$foundedCommand) {
                    $foundedCommand = static::$commandList[$this->command];
                }
                $method = $foundedCommand->getMethod();
                $params = $method->getParameters();
                $names = [];
                $order = [];
                foreach ($params as $param) {
                    try {
                        $default = $param->getDefaultValue();
                    } catch (\Exception $exception) {
                        if (!array_key_exists($param->getName(), $this->parsedArgs)) {
                            Output::red(
                                "\nCommand " . str_replace(
                                    '_',
                                    ':',
                                    $this->command
                                ) . " require a missing parameter: " . $param->getName() . "\n"
                            )
                                ->output(true);
                        }
                    }

                    if (isset($this->parsedArgs[$param->getName()])) {
                        $order[$param->getPosition()] = $this->parsedArgs[$param->getName()];
                    }

                    $names[] = $param->getName();
                }

                foreach ($this->parsedArgs as $arg_name => $value) {
                    if (!in_array($arg_name, $names)) {
                        Output::red(
                            "\nCommand " . str_replace(
                                '_',
                                ':',
                                $this->command
                            ) . " have a unrecognized argument: " . $arg_name . "\n"
                        )
                            ->output(true);
                    }
                }

                call_user_func_array(
                    [
                        $foundedCommand->getClass(),
                        (!empty($this->commandLast) ? $this->commandLast : $this->commandFirst)
                    ],
                    $order
                );
            } else {
                foreach (static::$commandList as $key => $command) {
                    if (str_starts_with($key, $this->commandFirst)) {
                        $exp = explode(':', $key);
                        $main = array_shift($exp);
                        if ($main === $this->commandFirst) {
                            Output::red(
                                "\nCommand " . $this->commandFirst . " is a possible command, but subcommand " . $this->commandLast . " is not."
                            )
                                ->output();
                            $this->commandList(true);
                        }
                    }
                }
                Output::red("Command " . $this->commandFirst . ":" . $this->commandLast . " is a not possible command")
                    ->output();
                $this->commandList(true);
            }
        }
    }

    private function didYouSay(): bool
    {
        $percent = 0;

        $says = '';

        foreach (static::$commandList as $commandObject) {
            $command = explode(':', $commandObject->getCommand());
            foreach ($command as $value) {
                similar_text($this->command, $value, $percent);
                if ($percent >= 75) {
                    $says .= "\n* " . str_replace('_', ':', $commandObject->getCommand());
                }
            }
        }

        if (!empty($says)) {
            Output::blue(
                "\n\n********** Did you say ************\n*" . $says . "\n*\n***********************************\n"
            )
                ->output();
        }

        return !empty($says);
    }

    /**
     * @param bool $check_possibles
     * @return void
     */
    #[NoReturn] private function commandList(bool $check_possibles = false): void
    {
        if ($check_possibles) {
            if (!$this->didYouSay()) {
                echo "\n";
            }
        }

        Output::green("\nCheck possible commands below.\n\n")->output();

        $tab = '  ';
        $list = '';
        foreach (static::$commandList as $command => $commandObject) {
            $command = explode(':', $command);
            $main = false;
            if (count($command) > 1) {
                $main = $command[0];
                $command = $command[1];
            } else {
                $command = $command[0];
            }
            if ($main) {
                $list .= '- ' . $main;
            }
            $desc = $commandObject->getDocumentation()['description'];
            $list .= (!empty($main) ? "\n$tab$tab" : "- ") . $command . ($commandObject->isDefault(
                ) ? ' (default command)' : '') . (!empty($desc) ? (": " . $desc) : '') . "\n";
            $params = $commandObject->getDocumentation()['params'];
            foreach ($params as $param => $description) {
                $list .= (!empty($main) ? "$tab$tab$tab" : "\n$tab") . " - " . $param . ": " . $description . (count(
                        $params
                    ) > 1 ? "\n" : "");
            }
        }
        die($list . "\n\n");
    }

    /**
     * @param $class
     * @param Command[] $commands
     * @param ?string $mainCommand
     * @return void
     * @throws AlreadyRegisteredCommandException|ClassNotExistsException|MethodNotExistsException|MethodIsNotStaticException
     */
    public static function addCommands($class, array $commands = [], ?string $mainCommand = null): void
    {
        foreach ($commands as $command) {
            $originalCommand = $command->getCommand();
            if ($mainCommand) {
                $command->setCommand($mainCommand . ':' . $command->getCommand());
            }
            if (!$mainCommand && array_key_exists($command->getCommand(), static::$commandList)) {
                throw new AlreadyRegisteredCommandException($command->getCommand());
            }
            $command->setClass($class);
            if (!class_exists($class)) {
                throw new ClassNotExistsException($class);
            }
            $reflection = new ReflectionClass($class);
            $methods = $reflection->getMethods();
            $foundedMethod = null;
            foreach ($methods as $method) {
                if ($method->getName() === $originalCommand) {
                    $foundedMethod = $method;
                    break;
                }
            }
            if (!$foundedMethod) {
                throw new MethodNotExistsException($originalCommand, $class);
            }
            if (!$foundedMethod->isStatic()) {
                throw new MethodIsNotStaticException($originalCommand, $class);
            }
            $documentation = $foundedMethod->getDocComment();
            $documentation = static::parseDoc($documentation ?? null);
            $command->setMethod($foundedMethod);
            $command->setDocumentation($documentation);
            static::$commandList[$command->getCommand()] = $command;
        }
    }

    private static function parseDoc(?string $doc): array
    {
        $clear = [
            'params' => [],
            'description' => '',
        ];
        if ($doc) {
            $doc = explode("\n", $doc);
            $endDescription = false;
            $description = '';
            foreach ($doc as $key => $line) {
                if ($key > 0 && $key < (count($doc) - 1)) {
                    $line = trim(trim(trim($line), '*'));
                    if (str_starts_with($line, '@')) {
                        $endDescription = true;
                        if (str_contains($line, '@param')) {
                            $exp = explode(' $', $line);
                            $exp = explode(' ', end($exp));
                            $param = array_shift($exp);
                            $param_description = join(' ', $exp);
                            $clear['params'][$param] = $param_description;
                        }
                    } else {
                        if (!$endDescription) {
                            $description .= empty($description) ? $line : "\n" . $line;
                        }
                    }
                }
            }
            $clear['description'] = $description;
        }
        return $clear;
    }

    public static function isCli(): bool
    {
        return str_contains(php_sapi_name(), 'cli');
    }

}
