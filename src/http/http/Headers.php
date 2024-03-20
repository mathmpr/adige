<?php

namespace Adige\http\http;

use Adige\core\BaseObject;
use Adige\helpers\Str;

class Headers extends BaseObject
{
    private array $headers = [];

    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->setHeader(Str::slug($name), $value);
        }
        parent::__construct();
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

}