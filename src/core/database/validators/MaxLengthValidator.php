<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class MaxLengthValidator extends AbstractValidator
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

        $maxLength = null;

        if (isset($params['max']) && is_numeric($params['max'])) {
            $maxLength = (int) $params['max'];
        } elseif (isset($params['length']) && is_numeric($params['length'])) {
            $maxLength = (int) $params['length'];
        } elseif (isset($params['args'][0]) && is_numeric($params['args'][0])) {
            $maxLength = (int) $params['args'][0];
        }

        if ($maxLength === null) {
            return;
        }

        if (mb_strlen($value) > $maxLength) {
            $model->addError($field, $this->message($params, "Field '$field' must contain at most {$maxLength} characters."));
        }
    }
}
