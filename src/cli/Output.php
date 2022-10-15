<?php

namespace Adige\cli;

/**
 * @method static black(...$outputs)
 * @method static red(...$outputs)
 * @method static green(...$outputs)
 * @method static yellow(...$outputs)
 * @method static blue(...$outputs)
 * @method static magenta(...$outputs)
 * @method static cyan(...$outputs)
 * @method static white(...$outputs)
 * @method static bgBlack(...$outputs)
 * @method static bgRed(...$outputs)
 * @method static bgGreen(...$outputs)
 * @method static bgYellow(...$outputs)
 * @method static bgBlue(...$outputs)
 * @method static bgMagenta(...$outputs)
 * @method static bgCyan(...$outputs)
 * @method static bgWhite(...$outputs)
 */
class Output
{
    public const INSTANT = __CLASS__ . ':instant output';
    public const DIE = __CLASS__ . ':kill execution';

    private string|array|object|int|bool|float $message;
    private int $color = 0;
    private int $background = 0;
    private string $style = "\e[%s;%sm%s\e[0m";
    private array $colors = [
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'white' => 37,
    ];

    private array $backgrounds = [
        'black' => 40,
        'red' => 41,
        'green' => 42,
        'yellow' => 43,
        'blue' => 44,
        'magenta' => 45,
        'cyan' => 46,
        'white' => 47,
    ];

    public function __construct($message)
    {
        if (!is_scalar($message)) {
            $message = trim(print_r($message, true));
        }
        $this->message = $message;
        $this->color = $this->colors['green'];
    }

    public static function __callStatic(string $name, array $arguments)
    {
        $instantOutput = false;
        $die = false;
        $message = '';
        foreach ($arguments as $argument) {
            if ($argument === static::INSTANT) {
                $instantOutput = true;
                continue;
            }
            if ($argument === static::DIE) {
                $die = true;
                continue;
            }

            if (!is_scalar($argument)) {
                $message .= print_r($argument, true);
            } else {
                $message .= $argument;
            }
        }

        $object = new self($message);
        if ($instantOutput && $die) {
            $object->{$name}()->output(true);
        } else if ($instantOutput) {
            $object->{$name}()->output();
        } else {
            return $object->{$name}();
        }
        return null;
    }

    /**
     * @param bool $exit
     * @return void
     */
    public function output(bool $exit = false): void
    {
        $firstWord = '';
        $lastWord = '';
        $message = explode(" ", str_replace("\n", " ", $this->message));
        foreach ($message as $word) {
            if (empty($firstWord) && !empty(trim($word))) {
                $firstWord = $word;
                break;
            }
        }
        $message = array_reverse($message);
        foreach ($message as $word) {
            if (empty($lastWord) && !empty(trim($word))) {
                $lastWord = $word;
                break;
            }
        }
        $firstWordPos = stripos($this->message, $firstWord);
        $lastWordPos = strrpos($this->message, $lastWord);
        $leftLines = substr_count($this->message, "\n", 0, $firstWordPos);
        $rightLines = substr_count($this->message, "\n", $lastWordPos, (strlen($this->message) - $lastWordPos));
        $message = sprintf($this->style, ($this->background > 0 ? $this->color : 0), ($this->background > 0 ? $this->background : $this->color), trim($this->message));
        fwrite(STDOUT, str_repeat("\n", $leftLines) . $message . str_repeat("\n", $rightLines));
        if ($exit) exit;
    }

    public function __call(string $name, array $arguments): Output
    {
        if (str_starts_with($name, 'bg')) {
            $this->background = $this->backgrounds['green'];
            $name = str_replace('bg', '', strtolower($name));
            if (array_key_exists($name, $this->backgrounds)) {
                $this->background = $this->backgrounds[$name];
            }
        } else {
            $this->color = $this->colors['green'];
            if (array_key_exists($name, $this->colors)) {
                $this->color = $this->colors[$name];
            }
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @return Output
     */
    public function setMessage(string $message): Output
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return mixed|string
     */
    public function getColor(): mixed
    {
        return $this->color;
    }

    /**
     * @return string
     */
    public function getBackground(): string
    {
        return $this->background;
    }

    /**
     * @return string
     */
    public function getStyle(): string
    {
        return $this->style;
    }

}