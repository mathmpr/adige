<?php

namespace Adige\core\http\http;

class JsonResponse extends WebResponse
{
    public function __construct(
        array|object $data,
        int $statusCode = 200,
        array $headers = []
    ) {
        parent::__construct($statusCode, $headers);
        if (!$this->getHeaders()?->hasHeader('Content-Type')) {
            $this->getHeaders()?->setHeader('Content-Type', 'application/json');
        }
        $this->setBody(encode_json($data));
    }
}
