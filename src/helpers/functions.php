<?php

use Adige\core\BaseEnvironment;
use Adige\core\collection\Collection;
use Adige\core\file\File;
use Adige\core\http\http\FileResponse;
use Adige\core\http\http\JsonResponse;
use Adige\core\http\http\WebResponse;
use Adige\core\http\http\exceptions\JsonEncodingException;

function respond(
    WebResponse $response,
    string|array|object|null $content,
    int $statusCode = 200,
    array $headers = [],
    string $buffer = ''
): WebResponse
{
    if ($content instanceof WebResponse) {
        return $content;
    }

    if ($content instanceof File) {
        return new FileResponse($content, $statusCode, $headers);
    }

    foreach ($headers as $key => $value) {
        $response->getHeaders()->setHeader($key, $value);
    }
    $response->setStatusCode($statusCode);
    if ($content instanceof Collection) {
        $content = $content->toArray();
    }

    if (is_array($content)) {
        $response = new JsonResponse($content, $statusCode, $headers);
        $response->setBody(($response->getBody() ?? '') . $buffer);
    } elseif (is_string($content)) {
        $response->setBody($content . $buffer);
    } elseif (is_object($content)) {
        $response = new JsonResponse($content, $statusCode, $headers);
        $response->setBody(($response->getBody() ?? '') . $buffer);
    } else {
        $response->setBody($buffer);
    }
    return $response;
}

/**
 * @throws JsonEncodingException
 */
function encode_json(mixed $value): string
{
    try {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    } catch (\JsonException $exception) {
        $type = get_debug_type($value);
        throw new JsonEncodingException(
            "Failed to encode value of type {$type} to JSON: " . $exception->getMessage(),
            previous: $exception
        );
    }
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
    if ($value === '') {
        return '';
    }

    $filters = [
        FILTER_VALIDATE_INT,
        FILTER_VALIDATE_FLOAT,
        FILTER_VALIDATE_BOOLEAN,
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

function deep_merge(array $array1, array $array2): array
{
    $merged = $array1;
    foreach ($array2 as $key => $value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = deep_merge($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }
    return $merged;
}
