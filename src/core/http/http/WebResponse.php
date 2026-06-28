<?php

namespace Adige\core\http\http;

use Adige\core\BaseResponse;
use Adige\core\file\File;

class WebResponse extends BaseResponse
{
    protected int $statusCode = 200;

    protected array $statuses = [
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '229' => 'Too Many Requests',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '304' => 'Not Modified',
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '503' => 'Service Unavailable',
    ];

    protected ?Headers $headers;

    protected string|null|File $body = null;

    public function __construct(int $statusCode = 200, array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = new Headers($headers);
        parent::__construct();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): WebResponse
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusText(): string
    {
        return $this->statuses[$this->statusCode] ?? 'Unknown status code';
    }

    public function getFullStatus(): string
    {
        return $this->statusCode . ' ' . $this->getStatusText();
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

    public function redirect(string $url): static
    {
        $this->setStatusCode(302);
        $this->getHeaders()?->setHeader('Location', $url);
        return $this;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function setStatuses(array $statuses): WebResponse
    {
        $this->statuses = $statuses;
        return $this;
    }

    public function getBody(): string|null|File
    {
        return $this->body;
    }

    public function setBody(string|null|File $body): WebResponse
    {
        $this->body = $body;
        return $this;
    }

    public function resolveContentType(): ?string
    {
        $contentType = $this->headers?->getHeader('Content-Type');
        if ($contentType !== null) {
            return $contentType;
        }

        if ($this->body instanceof File) {
            return 'application/octet-stream';
        }

        if (is_string($this->body)) {
            return 'text/html; charset=UTF-8';
        }

        return null;
    }

    public function getDispatchHeaders(): array
    {
        $headers = $this->headers?->getHeaders() ?? [];
        $contentType = $this->resolveContentType();

        if ($contentType !== null) {
            $headersObject = new Headers($headers);
            $headersObject->setHeader('Content-Type', $contentType);
            $headers = $headersObject->getHeaders();
        }

        if (!array_key_exists('Content-Type', $headers)) {
            return $headers;
        }

        $orderedHeaders = ['Content-Type' => $headers['Content-Type']];
        unset($headers['Content-Type']);

        foreach ($headers as $name => $value) {
            $orderedHeaders[$name] = $value;
        }

        return $orderedHeaders;
    }

    public function dispatch(): void
    {
        $this->trigger(self::EVENT_BEFORE_DISPATCH);
        header("HTTP/1.1 " . $this->getFullStatus());
        foreach ($this->getDispatchHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
        if (is_string($this->body)){
            echo $this->body;
        }
        if ($this->body instanceof File) {
            readfile($this->body->getLocation());
        }
        $this->trigger(self::EVENT_AFTER_DISPATCH);
    }

}
