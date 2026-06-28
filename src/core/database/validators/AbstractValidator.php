<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

abstract class AbstractValidator implements ValidatorInterface
{
    public function validate(
        ActiveRecord $model,
        array $fields,
        array $params = [],
        ?Connection $connection = null
    ): void {
        foreach ($fields as $field) {
            $this->validateField($model, $field, $params, $connection);
        }
    }

    abstract protected function validateField(
        ActiveRecord $model,
        string $field,
        array $params = [],
        ?Connection $connection = null
    ): void;

    protected function message(array $params, string $default): string
    {
        return isset($params['message']) && is_string($params['message'])
            ? $params['message']
            : $default;
    }
}
