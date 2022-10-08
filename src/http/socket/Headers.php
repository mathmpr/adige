<?php

namespace Adige\http\socket;

use Adige\helpers\Str;

/**
 * @property ?string $host
 * @property ?string $connection
 * @property ?string $secChUa
 * @property ?string $secChUaMobile
 * @property ?string $userAgent
 * @property ?string $secChUaPlatform
 * @property ?string $accept
 * @property ?string $secFetchSite
 * @property ?string $secFetchMode
 * @property ?string $secFetchDest
 * @property ?string $referer
 * @property ?string $acceptEncoding
 * @property ?string $acceptLanguage
 * @property ?string $cookie
 * @property ?string $contentType
 * @property ?string $range
 * @property ?string $server
 * @property ?string $contentLength
 * @property ?string $contentRange
 * @property ?string $keepAlive
 */
class Headers
{

    private array $headers = [];
    private array $parsedToOriginal = [];

    public function __get(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->headers[$name] = $value;
    }

    public function __toString(): string
    {
        $headers = '';
        foreach ($this->headers as $key => $value) {
            $headers .= ($this->parsedToOriginal[$key] ?? Str::camelToKebab($key)) . ': ' . $value . "\r\n";
        }
        return $headers;
    }

    /**
     * @param array $headers
     * @return Headers
     */
    public function setHeaders(array $headers): Headers
    {
        foreach ($headers as $key => $header) {
            if (is_string($key)) {
                $original = trim($key);
                $parsed = Str::camel($original);
                $this->{$parsed} = trim($header);
            } else {
                $header = explode(':', $header);
                $original = trim($header[0]);
                $parsed = Str::camel($original);
                if (count($header) > 1) {
                    $this->{$parsed} = trim($header[1]);
                }
            }
            $this->parsedToOriginal[$parsed] = $original;
        }
        return $this;
    }

}
