<?php

namespace tests\Fixtures\web\controllers;

use Adige\core\controller\BaseController;

class ParamsController extends BaseController
{
    public function actionRequired(string $name)
    {
        return [
            'name' => $name,
        ];
    }

    public function actionOptional(?string $name = null)
    {
        return [
            'name' => $name,
        ];
    }

    public function actionDefaults($count = 5, $enabled = true, $name = 'fallback')
    {
        return [
            'count' => $count,
            'enabled' => $enabled,
            'name' => $name,
        ];
    }

    public function actionRoute(int $id, $page = 1, $enabled = true)
    {
        return [
            'id' => $id,
            'page' => $page,
            'enabled' => $enabled,
        ];
    }
}
