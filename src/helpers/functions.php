<?php

use Adige\http\http\Response;
use Adige\core\Adige;
use Adige\core\BaseEnvironment;
use Adige\core\collection\Collection;

function respond(string|array|object $content, int $statusCode = 200, array $headers = []): Response
{
    $string = '';
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