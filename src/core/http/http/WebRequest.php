<?php

namespace Adige\core\http\http;

use Adige\core\BaseRequest;

class WebRequest extends BaseRequest
{
    private string $url;

    private string $host;

    private bool $overHttps;

    private ?Headers $headers;

    private array $get;

    private array $post;

    private string $body;

    private array $files;

    public function init(): void
    {
        $this->overHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $this->setMethod($_SERVER['REQUEST_METHOD'])
            ->setUri($_SERVER['REQUEST_URI'])
            ->setHost($_SERVER['HTTP_HOST'])
            ->setHeaders(getallheaders())
            ->setInput($_GET)
            ->setGet($_GET)
            ->setPost($_POST)
            ->setBody(file_get_contents('php://input'))
            ->setFiles($_FILES)
            ->defineUrl();
        parent::init();
    }

    public function defineUrl(): self
    {
        $this->url = ($this->overHttps ? 'https://' : 'http://') . $this->host . $this->uri
            . (!empty($this->get)
                ? '?' . http_build_query($this->get)
                : ''
            );
        return $this;
    }

    public function fixUri(): void
    {
        $uri = '/' . ltrim($this->uri, '/');
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = rtrim(dirname($scriptName), '/');
        $scriptBase = basename($scriptName);

        if ($scriptDir !== '' && $scriptDir !== '.' && str_starts_with($uri, $scriptDir . '/')) {
            $uri = substr($uri, strlen($scriptDir));
        }

        if ($scriptBase !== '' && str_starts_with($uri, '/' . $scriptBase)) {
            $uri = substr($uri, strlen('/' . $scriptBase));
        }

        $this->uri = $uri === '' ? '/' : $uri;
        $this->uriParts = [];
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPureUrl(): string
    {
        return $this->host . $this->uri;
    }

    public function setMethod(?string $method): static
    {
        parent::setMethod($method);
        return $this;
    }

    public function setUri(string $uri): self
    {
        $uri = explode('?', $uri, 2);
        $this->uri = array_shift($uri);
        return $this;
    }

    public function getHeaders(): ?Headers
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = new Headers($headers);
        return $this;
    }

    public function get(?string $key = null): null|bool|string|array
    {
        return $this->input($key);
    }

    public function setGet(array $get): self
    {
        $this->get = $get;
        $this->setInput($get);
        return $this;
    }

    public function setInput(array $input): static
    {
        $this->get = $input;
        return parent::setInput($input);
    }

    public function post(?string $key = null): null|bool|string|array
    {
        return $key
            ? $this->post[$key] ?? null
            : $this->post;
    }

    public function setPost(array $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function files(?string $key = null): null|array
    {
        return $key
            ? $this->files[$key] ?? null
            : $this->files;
    }

    public function setFiles(array $files): self
    {
        $this->files = $files;
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function isOverHttps(): bool
    {
        return $this->overHttps;
    }

    public function acceptsJson(): bool
    {
        $accept = $this->headers?->getHeader('Accept') ?? '';
        return str_contains(mb_strtolower($accept), 'application/json');
    }

}
