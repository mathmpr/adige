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

    public static function tableName(): string
    {
        return 'fake_models';
    }
}
