<?php

namespace Adige\core;

use Adige\core\events\Observable;

abstract class BaseRequest extends BaseObject
{
    use Observable;

    public const EVENT_BEFORE_INIT = 'beforeInit';
    public const EVENT_AFTER_INIT = 'afterInit';
    public const EVENT_BEFORE_FIX_URI = 'beforeFixUri';
    public const EVENT_AFTER_FIX_URI = 'afterFixUri';

    protected string $uri;

    protected array $uriParts = [];

    protected ?string $method = null;

    protected array $input = [];

    abstract function fixUri(): void;

    abstract function setUri(string $uri): self;

    public function getUriParts(): array
    {
        if (!empty($this->uriParts)) {
            return $this->uriParts;
        }
        $this->uriParts = array_values(array_filter(explode('/', $this->uri), fn($item) => !empty($item)));
        return $this->uriParts;
    }

    public function init(): void
    {
        $this->trigger(self::EVENT_BEFORE_INIT);
        $this->trigger(self::EVENT_BEFORE_FIX_URI);
        $this->fixUri();
        $this->trigger(self::EVENT_AFTER_FIX_URI);
        $this->trigger(self::EVENT_AFTER_INIT);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function input(?string $key = null): mixed
    {
        return $key !== null
            ? ($this->input[$key] ?? null)
            : $this->input;
    }

    public function setInput(array $input): static
    {
        $this->input = $input;
        return $this;
    }
}
