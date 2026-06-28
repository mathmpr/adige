<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class MinLengthValidator extends AbstractValidator
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

        $minLength = null;

        if (isset($params['min']) && is_numeric($params['min'])) {
            $minLength = (int) $params['min'];
        } elseif (isset($params['length']) && is_numeric($params['length'])) {
            $minLength = (int) $params['length'];
        } elseif (isset($params['args'][0]) && is_numeric($params['args'][0])) {
            $minLength = (int) $params['args'][0];
        }

        if ($minLength === null) {
            return;
        }

        if (mb_strlen($value) < $minLength) {
            $model->addError($field, $this->message($params, "Field '$field' must contain at least {$minLength} characters."));
        }
    }
}
