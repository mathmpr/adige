<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class BooleanValidator extends AbstractValidator
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

        $strict = (bool) ($params['strict'] ?? false);
        $trueValue = $params['trueValue'] ?? true;
        $falseValue = $params['falseValue'] ?? false;

        $isValid = $strict
            ? $value === $trueValue || $value === $falseValue
            : in_array($value, $this->normalizeAcceptedValues($trueValue, $falseValue), true);

        if (!$isValid) {
            $model->addError($field, $this->message($params, "Field '$field' must be a boolean."));
        }
    }

    private function normalizeAcceptedValues(mixed $trueValue, mixed $falseValue): array
    {
        return array_values(array_unique([
            true,
            false,
            1,
            0,
            '1',
            '0',
            'true',
            'false',
            'on',
            'off',
            'yes',
            'no',
            $trueValue,
            $falseValue,
        ], SORT_REGULAR));
    }
}
