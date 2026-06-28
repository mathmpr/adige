<?php

namespace Adige\core\database\dialects\sqlite;

use Adige\core\database\Connection;

class DsnBuilder extends \Adige\core\database\DsnBuilder
{
    public function build(Connection $connection): string
    {
        $path = $connection->getDatabase() ?: $connection->getHost();

        return Connection::TYPE_SQLITE . ':' . $path;
    }
}
