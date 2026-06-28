<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class MaskValidator extends AbstractValidator
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

        if (!is_string($value)) {
            $model->addError($field, $this->message($params, "Field '$field' must be a string."));
            return;
        }

        $pattern = $params['pattern'] ?? $params['regexp'] ?? ($params['args'][0] ?? null);
        if (!is_string($pattern) || $pattern === '') {
            return;
        }

        if (@preg_match($pattern, '') === false) {
            $model->addError($field, "Validation pattern for field '$field' is invalid.");
            return;
        }

        if (preg_match($pattern, $value) !== 1) {
            $model->addError($field, $this->message($params, "Field '$field' format is invalid."));
        }
    }
}
