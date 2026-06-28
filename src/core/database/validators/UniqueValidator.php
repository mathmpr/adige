<?php

namespace Adige\core\database\validators;

use Adige\core\database\ActiveRecord;
use Adige\core\database\Connection;
use Adige\core\database\Schema;

class UniqueValidator extends AbstractValidator
{
    public function validate(
        ActiveRecord $model,
        array $fields,
        array $params = [],
        ?Connection $connection = null
    ): void {
        $targets = $this->normalizeTargetAttributes($fields, $params);
        $conditions = [];

        foreach ($targets as $targetField => $sourceField) {
            $value = $model->{$sourceField};
            if ($value === null || $value === '') {
                return;
            }

            $conditions[$targetField] = $value;
        }

        if ($conditions === []) {
            return;
        }

        $connection ??= $model->getConnection() ?? Connection::getDefaultConnection();
        $table = $model->getTableName();
        $pdo = $connection->getDb();
        $pkName = ($table !== null && $pdo !== null)
            ? Schema::pkName($table, $pdo)
            : null;

        $query = $model::find($connection)
            ->select($pkName !== null ? [$pkName] : ['*'])
            ->where($conditions);

        $pkValue = $this->resolveCurrentPrimaryKeyValue($model, $pkName);
        if ($pkName !== null && $pkValue !== null) {
            $query->andWhere([
                ':tableName.`:pkName`',
                '!=',
                $pkValue,
            ]);
        }

        if ($query->one($connection) !== null) {
            $message = $this->message(
                $params,
                count($conditions) === 1
                    ? "Field '" . array_key_first($conditions) . "' must be unique."
                    : 'The combination of the selected fields must be unique.'
            );

            foreach ($fields as $field) {
                $model->addError($field, $message);
            }
        }
    }

    protected function validateField(
        ActiveRecord $model,
        string $field,
        array $params = [],
        ?Connection $connection = null
    ): void {
        $this->validate($model, [$field], $params, $connection);
    }

    /**
     * @return array<string, string>
     */
    private function normalizeTargetAttributes(array $fields, array $params): array
    {
        $targetAttribute = $params['targetAttribute'] ?? $fields;

        if (is_string($targetAttribute)) {
            $sourceField = $fields[0] ?? $targetAttribute;
            return [$targetAttribute => $sourceField];
        }

        if (!is_array($targetAttribute) || $targetAttribute === []) {
            return array_combine($fields, $fields) ?: [];
        }

        $targets = [];
        foreach ($targetAttribute as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value) || $value === '') {
                    continue;
                }

                $targets[$value] = $value;
                continue;
            }

            if (!is_string($key) || $key === '' || !is_string($value) || $value === '') {
                continue;
            }

            $targets[$key] = $value;
        }

        return $targets;
    }

    private function resolveCurrentPrimaryKeyValue(ActiveRecord $model, ?string $pkName): mixed
    {
        if ($pkName === null) {
            return null;
        }

        $oldAttributes = $model->getOldAttributes();
        if (array_key_exists($pkName, $oldAttributes)) {
            return $oldAttributes[$pkName];
        }

        $attributes = $model->getAttributes();
        return $attributes[$pkName] ?? null;
    }
}
