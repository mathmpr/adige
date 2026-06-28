<?php

namespace app\web\models;

use Adige\core\database\ActiveRecord;

class User extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'users';
    }
}