<?php

namespace Adige\core\http\http;

use Adige\core\BaseObject;
use Adige\helpers\Str;

class Headers extends BaseObject
{
    private array $headers = [];

    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
        parent::__construct();
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = [];
        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, (string) $value);
        }
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        if (array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        }

        $lowerName = Str::lower($name);
        foreach ($this->headers as $headerName => $value) {
            if (Str::lower($headerName) === $lowerName) {
                return $value;
            }
        }

        return null;
    }

    public function setHeader(string $name, string $value): self
    {
        $existingName = $this->findHeaderName($name);
        if ($existingName !== null && $existingName !== $name) {
            unset($this->headers[$existingName]);
        }

        $this->headers[$name] = $value;
        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return $this->findHeaderName($name) !== null;
    }

    public function removeHeader(string $name): self
    {
        $existingName = $this->findHeaderName($name);
        if ($existingName !== null) {
            unset($this->headers[$existingName]);
        }

        return $this;
    }

    protected function findHeaderName(string $name): ?string
    {
        if (array_key_exists($name, $this->headers)) {
            return $name;
        }

        $lowerName = Str::lower($name);
        foreach ($this->headers as $headerName => $value) {
            if (Str::lower($headerName) === $lowerName) {
                return $headerName;
            }
        }

        return null;
    }

}
