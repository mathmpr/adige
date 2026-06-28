<?php

namespace Tests\Fixtures\models;

use Adige\core\database\ActiveRecord;

class FakeModel extends ActiveRecord
{
    public function __construct(array $props = [])
    {
        foreach ($props as $name => $value) {
            $this->{$name} = $value;
        }
    }

    public function fields(): array
    {
        $fields = [];

        foreach (array_keys($this->getAttributes()) as $name) {
            $fields[$name] = static fn (ActiveRecord $model) => $model->getAttributes()[$name] ?? null;
        }

        return $fields;
    }

    public static function tableName(): string
    {
        return 'fake_models';
    }
}
