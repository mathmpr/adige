<?php

namespace Adige\core\database\dialects\mysql;

use Adige\core\database\Connection;

class DsnBuilder extends \Adige\core\database\DsnBuilder
{
    public function build(Connection $connection): string
    {
        return Connection::TYPE_MYSQL .
            ':host=' . $connection->getHost() .
            ';port=' . $connection->getPort() .
            (!empty($connection->getDatabase()) ? ';dbname=' . $connection->getDatabase() : '') .
            ';charset=' . $connection->getCharset();
    }
}
