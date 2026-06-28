<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class NumberValidator extends AbstractValidator
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

        if (!is_numeric($value)) {
            $model->addError($field, $this->message($params, "Field '$field' must be a number."));
            return;
        }

        $numeric = (float) $value;
        $min = $this->resolveNumericParam($params, ['min'], 0);
        $max = $this->resolveNumericParam($params, ['max'], 1);

        if ($min !== null && $numeric < $min) {
            $model->addError($field, $this->message($params, "Field '$field' must be greater than or equal to {$min}."));
            return;
        }

        if ($max !== null && $numeric > $max) {
            $model->addError($field, $this->message($params, "Field '$field' must be less than or equal to {$max}."));
        }
    }

    private function resolveNumericParam(array $params, array $keys, int $argIndex): ?float
    {
        foreach ($keys as $key) {
            if (isset($params[$key]) && is_numeric($params[$key])) {
                return (float) $params[$key];
            }
        }

        $args = $params['args'] ?? [];
        if (isset($args[$argIndex]) && is_numeric($args[$argIndex])) {
            return (float) $args[$argIndex];
        }

        return null;
    }
}
