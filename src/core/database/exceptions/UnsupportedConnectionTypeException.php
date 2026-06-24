<?php

namespace Adige\core\database\exceptions;

use Adige\core\BaseException;

class UnsupportedConnectionTypeException extends BaseException
{
    public function __construct(string $type, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct("Unsupported connection type '{$type}'", $code, $previous);
    }
}
