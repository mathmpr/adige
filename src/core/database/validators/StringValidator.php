<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class StringValidator extends AbstractValidator
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

        $length = mb_strlen($value);
        $exact = $this->resolveIntParam($params, ['length'], 0);
        $min = $this->resolveIntParam($params, ['min'], 1);
        $max = $this->resolveIntParam($params, ['max'], 2);

        if ($exact !== null && $length !== $exact) {
            $model->addError($field, $this->message($params, "Field '$field' must contain exactly {$exact} characters."));
            return;
        }

        if ($min !== null && $length < $min) {
            $model->addError($field, $this->message($params, "Field '$field' must contain at least {$min} characters."));
            return;
        }

        if ($max !== null && $length > $max) {
            $model->addError($field, $this->message($params, "Field '$field' must contain at most {$max} characters."));
        }
    }

    private function resolveIntParam(array $params, array $keys, int $argIndex): ?int
    {
        foreach ($keys as $key) {
            if (isset($params[$key]) && is_numeric($params[$key])) {
                return (int) $params[$key];
            }
        }

        $args = $params['args'] ?? [];
        if (isset($args[$argIndex]) && is_numeric($args[$argIndex])) {
            return (int) $args[$argIndex];
        }

        return null;
    }
}
