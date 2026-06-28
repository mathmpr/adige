<?php

namespace Tests\Support;

use Adige\core\BaseRequest;

class TestRequest extends BaseRequest
{
    public function __construct(string $uri = '/', ?string $method = 'GET', array $input = [])
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->input = $input;

        parent::__construct();
    }

    public function fixUri(): void
    {
        $this->uri = '/' . trim($this->uri, '/');
        if ($this->uri === '//') {
            $this->uri = '/';
        }
    }

    public function setUri(string $uri): static
    {
        $this->uri = $uri;
        $this->uriParts = [];
        $this->fixUri();

        return $this;
    }
}
