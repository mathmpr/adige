<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class CompareValidator extends AbstractValidator
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

        $operator = strtoupper(trim((string) ($params['operator'] ?? '==')));
        $compareValue = $this->resolveCompareValue($model, $params);

        if ($compareValue['resolved'] === false) {
            return;
        }

        $isValid = match ($operator) {
            '=', '==' => $value == $compareValue['value'],
            '===', 'STRICT' => $value === $compareValue['value'],
            '!=', '<>' => $value != $compareValue['value'],
            '!==', 'NOT STRICT' => $value !== $compareValue['value'],
            '>', '>=', '<', '<=' => $this->compareScalar($value, $compareValue['value'], $operator),
            default => false,
        };

        if (!$isValid) {
            $model->addError($field, $this->message($params, "Field '$field' is invalid."));
        }
    }

    private function resolveCompareValue(ActiveRecord $model, array $params): array
    {
        if (array_key_exists('compareValue', $params)) {
            return ['resolved' => true, 'value' => $params['compareValue']];
        }

        if (isset($params['compareAttribute']) && is_string($params['compareAttribute'])) {
            return ['resolved' => true, 'value' => $model->{$params['compareAttribute']}];
        }

        if (array_key_exists(0, $params['args'] ?? [])) {
            return ['resolved' => true, 'value' => $params['args'][0]];
        }

        return ['resolved' => false, 'value' => null];
    }

    private function compareScalar(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => false,
        };
    }
}
