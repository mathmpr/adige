<?php

namespace app\controllers;

use Adige\core\controller\Controller;

class AutoDiscoverController extends Controller
{
    public function actionIndex()
    {
        return respond([
            'hello' => 'I am auto discover controller, check option AUTO_DISCOVER_CONTROLLER in ./.env file',
        ]);
    }
}