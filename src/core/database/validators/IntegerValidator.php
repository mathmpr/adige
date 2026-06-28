<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class IntegerValidator extends AbstractValidator
{
    protected function validateField(
        ActiveRecord $model,
        string $field,
        array $params = [],
        ?Connection $connection = null
    ): void {
        $value = $model->{$field};

        if ($value === null || $value === '') {
            return;
        }

        if (is_int($value)) {
            return;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return;
        }

        $model->addError($field, $this->message($params, "Field '$field' must be an integer."));
    }
}
