<?php

namespace Adige\core\database\exceptions;

use Adige\core\BaseException;

class CantConnectException extends BaseException
{
    public function __construct(
        string $host = '',
        string $user = '',
        string $password = '',
        string $database = '',
        string $charset = 'utf8mb4',
        $previous = null
    ) {
        $message = "'Can't connect to database (host: $host, user: $user, password: $password, database: $database, charset: $charset)";
        parent::__construct($message, 0, $previous);
    }
}