<?php

namespace Adige\core\database\exceptions;

use Adige\core\BaseException;

class ConnectionNameAlreadyExistsException extends BaseException
{
    public function __construct(string $name, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct("Connection name '{$name}' is already registered", $code, $previous);
    }
}
