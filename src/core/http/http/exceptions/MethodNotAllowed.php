<?php

namespace Adige\core\http\http\exceptions;

use Adige\core\BaseException;

class MethodNotAllowed extends BaseException
{
    protected array $allowedMethods = [];

    public function __construct(
        array $allowedMethods = [],
        string $message = 'Method not allowed',
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->allowedMethods = array_values(array_unique($allowedMethods));
        parent::__construct($message, $code, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
