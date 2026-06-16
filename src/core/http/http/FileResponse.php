<?php

namespace Adige\core\http\http;

use Adige\core\file\File;

class FileResponse extends WebResponse
{
    public function __construct(File $file, int $statusCode = 200, array $headers = [])
    {
        parent::__construct($statusCode, $headers);
        $this->getHeaders()?->setHeaders(array_merge([
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $file->getFullName() . '"',
            'Content-Length' => (string) filesize($file->getLocation()),
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
        ], $headers));
        $this->setBody($file);
    }
}
