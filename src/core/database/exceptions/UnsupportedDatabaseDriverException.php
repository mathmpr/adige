<?php

namespace Adige\core\database\exceptions;

use Adige\core\BaseException;

class UnsupportedDatabaseDriverException extends BaseException
{
    public function __construct(string $driver, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct("Unsupported database driver '{$driver}'", $code, $previous);
    }
}
