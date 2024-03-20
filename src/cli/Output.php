<?php

namespace Adige\cli;

use Adige\core\BaseObject;

/**
 * @method static black($message = '')
 * @method static red($message = '')
 * @method static green($message = '')
 * @method static yellow($message = '')
 * @method static blue($message = '')
 * @method static magenta($message = '')
 * @method static cyan($message = '')
 * @method static white($message = '')
 * @method static bgBlack($message = '')
 * @method static bgRed($message = '')
 * @method static bgGreen($message = '')
 * @method static bgYellow($message = '')
 * @method static bgBlue($message = '')
 * @method static bgMagenta($message = '')
 * @method static bgCyan($message = '')
 * @method static bgWhite($message = '')
 */
class Output extends BaseObject
{

    private string $message;
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
        $this->message = $message;
        $this->color = $this->colors['green'];
        parent::__construct();
    }

    public static function __callStatic(string $name, array $arguments)
    {
        $object = new self($arguments[0]);
        return $object->{$name}();
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
        $lastWordPos = 0;
        $message = $this->message;
        while (is_int($pos = stripos($message, $lastWord))) {
            $lastWordPos = $pos;
            if ($pos === 0) {
                $message = str_repeat('@', strlen($lastWord)) . substr(
                        $message,
                        strlen($lastWord),
                        strlen($message) - strlen($lastWord)
                    );
            } else {
                $message = substr($message, 0, $pos) . substr(
                        $message,
                        $pos + strlen($lastWord) - 1,
                        (strlen($message) - $pos - strlen($lastWord))
                    );
            }
        }
        $leftLines = substr_count($this->message, "\n", 0, $firstWordPos);
        $rightLines = substr_count($this->message, "\n", $lastWordPos, (strlen($this->message) - $lastWordPos));
        $message = sprintf(
            $this->style,
            ($this->background > 0 ? $this->color : 0),
            ($this->background > 0 ? $this->background : $this->color),
            trim($this->message)
        );
        echo str_repeat("\n", $leftLines) . $message . str_repeat("\n", $rightLines);
        if ($exit) {
            exit;
        }
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