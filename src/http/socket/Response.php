<?php

namespace Adige\http\socket;

class Response {

    private int $code;
    private bool $file = false;
    private string $content = '';
    public ?Headers $headers;

    public static array $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Unused',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fiedls Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    public function __construct(int $code = 200)
    {
        $this->code = $code;
        $this->headers = new Headers();
        if (empty($this->headers->server)) {
            $this->headers->server = 'PHP ' . phpversion();
        }
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     * @return Response
     */
    public function setCode(int $code): Response
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return Response
     */
    public function setContent(string $content): Response
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->file;
    }

    /**
     * @param bool $file
     * @return Response
     */
    public function setFile(bool $file): Response
    {
        $this->file = $file;
        return $this;
    }

    public function appendContent(string $data) : Response
    {
        $this->content .= $data;
        return $this;
    }

    public function contentHtml(): Response {
        $this->headers->contentType = 'text/html; charset=utf-8';
        return $this;
    }

    public function contentJson(): Response {
        $this->headers->contentType = 'application/json; charset=utf-8';
        return $this;
    }

    public function contentText(): Response {
        $this->headers->contentType = 'text/plain; charset=utf-8';
        return $this;
    }

    public function __toString(): string
    {
        return "HTTP/1.1 $this->code ". (static::$codes[$this->code] ?? "OK") ."\r\n" . ((string) $this->headers) . "\r\n" . $this->content;
    }

}