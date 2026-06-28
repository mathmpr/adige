<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use DateTimeImmutable;

class DateValidator extends AbstractValidator
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
            $model->addError($field, $this->message($params, "Field '$field' must be a valid date."));
            return;
        }

        $format = $params['format'] ?? ($params['args'][0] ?? 'Y-m-d');
        if (!is_string($format) || $format === '') {
            $format = 'Y-m-d';
        }

        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if ($date === false || $hasErrors || $date->format($format) !== $value) {
            $model->addError($field, $this->message($params, "Field '$field' must be a valid date."));
        }
    }
}
