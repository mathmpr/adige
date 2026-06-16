<?php

namespace Adige\core;

abstract class BaseRequest extends BaseObject
{
    use EventHandler;

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
        $this->fixUri();
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
