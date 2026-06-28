<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class InValidator extends AbstractValidator
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

        $range = $params['range'] ?? ($params['args'][0] ?? null);
        if (!is_array($range) || $range === []) {
            return;
        }

        $strict = (bool) ($params['strict'] ?? false);
        $not = (bool) ($params['not'] ?? false);
        $contains = in_array($value, $range, $strict);

        if ((!$not && !$contains) || ($not && $contains)) {
            $default = $not
                ? "Field '$field' contains a disallowed value."
                : "Field '$field' must be one of the allowed values.";
            $model->addError($field, $this->message($params, $default));
        }
    }
}
