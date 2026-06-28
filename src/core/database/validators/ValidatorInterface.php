<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

interface ValidatorInterface
{
    public function validate(
        ActiveRecord $model,
        array $fields,
        array $params = [],
        ?Connection $connection = null
    ): void;
}
