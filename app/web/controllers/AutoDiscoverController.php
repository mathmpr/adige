<?php

namespace app\web\controllers;

use Adige\core\controller\BaseController;

class AutoDiscoverController extends BaseController
{
    public function actionIndex()
    {
        return $this->respond([
            'hello' => 'I am auto discover controller, check option AUTO_DISCOVER_CONTROLLER in ./.env file',
        ]);
    }
}
