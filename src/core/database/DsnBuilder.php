<?php

namespace Adige\core\database;

use Adige\core\BaseObject;

abstract class DsnBuilder extends BaseObject
{
    abstract public function build(Connection $connection): string;
}
