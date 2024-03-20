<?php

use Adige\http\http\Response;
use Adige\core\Adige;
use Adige\core\BaseEnvironment;
use Adige\core\collection\Collection;
use Adige\file\File;

function respond(string|array|object|null $content, int $statusCode = 200, array $headers = []): Response
{
    $string = '';
    if ($content instanceof Response) {
        return $content;
    }
    if ($content instanceof File) {
        if (ob_get_level() > 0) {
            ob_end_clean();
            Adige::$response->getHeaders()->setHeaders([
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $content->getFullName() . '"',
                'Content-Length' => filesize($content->getLocation()),
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Pragma' => 'public',
            ]);
            Adige::$response->setBody($content);
            return Adige::$response;
        }
    }
    if (ob_get_level() > 0) {
        $string = ob_get_clean();
    }
    foreach ($headers as $key => $value) {
        Adige::$response->getHeaders()->setHeader($key, $value);
    }
    Adige::$response->setStatusCode($statusCode);
    if ($content instanceof Collection) {
        $content = $content->toArray();
    }
    if (is_array($content)) {
        Adige::$response->getHeaders()->setHeader('Content-Type', 'application/json');
        Adige::$response->setBody(json_encode($content) . $string);
    } elseif (is_string($content)) {
        Adige::$response->setBody($content . $string);
    } elseif (is_object($content)) {
        Adige::$response->setBody(json_encode($content) . $string);
    } else {
        Adige::$response->setBody($string);
    }
    return Adige::$response;
}

function env(string $key, string $default = ''): string
{
    $value = BaseEnvironment::getEnv($key);
    if (is_null($value)) {
        return $default;
    }
    return $value;
}

function determine_var($value)
{
    $filters = [
        FILTER_VALIDATE_BOOLEAN,
        FILTER_VALIDATE_INT,
        FILTER_VALIDATE_FLOAT,
    ];
    foreach ($filters as $filter) {
        $filtered = filter_var($value, $filter, FILTER_NULL_ON_FAILURE);
        if (!is_null($filtered)) {
            $value = $filtered;
            break;
        }
    }
    return $value;
}