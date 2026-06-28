<?php

namespace app\web\app;

use Adige\core\Adige as MainAdige;

class Adige extends MainAdige
{
    protected static string $appClass = App::class;

    public static function app(): ?App
    {
        /** @var App|null $app */
        $app = parent::$app;
        return $app;
    }
}
