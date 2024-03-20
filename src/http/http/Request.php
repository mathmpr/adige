<?php

namespace Adige\http\http;

use Adige\core\BaseObject;
use Adige\helpers\Str;

class Request extends BaseObject
{
    private string $method;

    private string $uri;

    private ?array $uriParts = [];

    private string $url;

    private string $host;

    private bool $overHttps;

    private ?Headers $headers;

    private array $get;

    private array $post;

    private string $body;

    private array $files;

    public function __construct()
    {
        $this->overHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $this->setMethod($_SERVER['REQUEST_METHOD'])
            ->setUri($_SERVER['REQUEST_URI'])
            ->setHost($_SERVER['HTTP_HOST'])
            ->setHeaders(getallheaders())
            ->setGet($_GET)
            ->setPost($_POST)
            ->setBody(file_get_contents('php://input'))
            ->setFiles($_FILES)
            ->defineUrl()
            ->fixUri();

        parent::__construct();
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

    private function fixUri(): void
    {
        $uri = $this->getUriParts();
        $root = array_filter(explode('/', ROOT), fn($item) => !empty($item));
        $intersect = array_intersect($uri, $root);
        $intersect = end($intersect);
        if ($intersect) {
            $uri = explode($intersect, $this->getUri(), 2);
            $this->uri = end($uri);
            $this->uriParts = [];
        }
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPureUrl(): string
    {
        return $this->host . $this->uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getUriParts(): array
    {
        if (!empty($this->uriParts)) {
            return $this->uriParts;
        }
        $this->uriParts = array_values(array_filter(explode('/', $this->uri), fn($item) => !empty($item)));
        return $this->uriParts;
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
        return $key
            ? $this->get[$key] ?? null
            : $this->get;
    }

    public function setGet(array $get): self
    {
        $this->get = $get;
        return $this;
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

}