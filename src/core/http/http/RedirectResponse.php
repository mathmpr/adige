<?php

namespace Adige\core\http\http;

class RedirectResponse extends WebResponse
{
    public function __construct(string $url, int $statusCode = 302, array $headers = [])
    {
        parent::__construct($statusCode, $headers);
        $this->getHeaders()?->setHeader('Location', $url);
    }
}
