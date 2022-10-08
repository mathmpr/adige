<?php

namespace Adige\http\socket;

use Adige\file\File;
use Exception;

class Request
{

    public ?Headers $headers = null;
    private ?string $boundary = null;
    private string|array $pureRequest = '';
    private ?string $method = null;
    private ?string $uri = null;
    private ?string $file = null;
    private ?array $get = [];
    private ?array $post = [];
    private ?array $files = [];

    public function __construct(string $request)
    {
        $this->pureRequest = $request;
        $this->parseRequest();
        $this->pureRequest = '';
    }

    /**
     * @param array $headers
     * @return Request
     */
    public function setHeaders(array $headers): Request
    {
        if (empty($this->headers)) {
            $this->headers = new Headers();
        }

        $this->headers->setHeaders($headers);

        if ($this->isMultiPartFormData()) {
            $boundary = explode('; boundary=', $this->headers->contentType);
            $this->boundary = end($boundary);
        }

        return $this;
    }

    private function parseRequest(): void
    {
        $contentAndHeaders = explode("\r\n\r\n", $this->pureRequest);
        $content = explode("\r\n", array_shift($contentAndHeaders));
        $requestBody = join("\r\n\r\n", $contentAndHeaders);
        $firstLine = trim(array_shift($content));
        $firstLine = explode(' ', $firstLine);
        $this->method = $firstLine[0];
        $this->uri = $firstLine[1];
        $get = explode('?', $this->uri);
        if (count($get) > 1) {
            parse_str($get[1], $this->get);
        }
        $this->file = array_shift($get);
        $this->setHeaders(array_values($content));
        $this->parseRequestBody($requestBody);
    }

    private function parseRequestBody(string $requestBody): void
    {
        if (!empty($requestBody)) {
            if ($this->isMultiPartFormData()) {

                $requestBody = str_replace([
                    '--' . $this->boundary . "\r\n",
                    '--' . $this->boundary . "--\r\n"
                ], $this->boundary, $requestBody);

                $requestBody = explode($this->boundary, $requestBody);
                foreach ($requestBody as $boundary) {
                    if (empty(trim($boundary))) continue;
                    $content = explode("\r\n\r\n", $boundary);
                    $lines = explode("\r\n", array_shift($content));
                    $content = end($content);
                    $content = explode("\r\n", $content);
                    array_pop($content);
                    $content = join("\r\n", $content);

                    $name = '';
                    $filename = '';
                    $mimeType = '';
                    $length = strlen($content);

                    foreach ($lines as $line) {
                        if (str_contains($line, 'Content-Disposition')) {
                            $line = explode('; ', $line);
                            array_shift($line);
                            foreach ($line as $var) {
                                $var = explode('="', $var);
                                switch ($var[0]) {
                                    case 'name':
                                        $name = rtrim($var[1], '"');
                                        break;
                                    case 'filename':
                                        $filename = rtrim($var[1], '"');
                                        break;
                                }
                            }
                        } else if (str_contains($line, 'Content-Type')) {
                            $mimeType = str_replace('Content-Type: ', '', $line);
                        }
                    }

                    if (!empty($filename)) {

                        $extension = explode('.', $filename);
                        $extension = (end($extension) != $filename) ? end($extension) : '';
                        $file = new File(ADIGE_ROOT . 'tmp/' . uniqid() . (!empty($extension) ? ('.' . $extension) : ''));
                        $file->save($content);

                        $this->files[$name] = [
                            'name' => $filename,
                            'length' => $length,
                            'path' => $file->getLocation(),
                            'extension' => $extension,
                            'mimeType' => $mimeType,
                        ];
                    } else if(!empty($name) && !empty($content)) {
                        $this->post[$name] = $content;
                    }
                }
            } else {
                try {
                    $body = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
                    $this->post = $body;
                } catch (Exception $exception) {
                    $res = [];
                    parse_str($requestBody, $res);
                    if (!empty($res)) {
                        $this->post = $res;
                    }
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function isMultiPartFormData(): bool
    {
        if (!is_null($this->headers->contentType)) {
            return str_contains($this->headers->contentType, 'multipart/form-data');
        }
        return false;
    }

    /**
     * @return string|null
     */
    public function getBoundary(): ?string
    {
        return $this->boundary;
    }

    /**
     * @return string|null
     */
    public function getPureRequest(): ?string
    {
        return $this->pureRequest;
    }

    /**
     * @return string|null
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @return string|null
     */
    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return string|null
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * @return array|null
     */
    public function getGet(): ?array
    {
        return $this->get;
    }

    /**
     * @return array|null
     */
    public function getPost(): ?array
    {
        return $this->post;
    }

    /**
     * @return array|null
     */
    public function getFiles(): ?array
    {
        return $this->files;
    }

}
