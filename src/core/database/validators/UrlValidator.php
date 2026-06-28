<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;

class UrlValidator extends AbstractValidator
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

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $model->addError($field, $this->message($params, "Field '$field' must be a valid URL."));
        }
    }
}
