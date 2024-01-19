<?php

namespace Adige\http\http;

use Adige\core\BaseObject;

class Response extends BaseObject
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

    protected ?string $body = null;

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

    public function setStatusCode(int $statusCode): Response
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

    public function redirect(string $url): void
    {
        $this->setStatusCode(302);
        $this->setHeaders([
            'Location' => $url
        ]);
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function setStatuses(array $statuses): Response
    {
        $this->statuses = $statuses;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): Response
    {
        $this->body = $body;
        return $this;
    }

    public function dispatch(): void
    {
        header("HTTP/1.1 " . $this->getFullStatus());
        foreach ($this->headers->getHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

}