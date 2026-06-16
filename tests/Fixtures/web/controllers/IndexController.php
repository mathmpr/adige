<?php

namespace tests\Fixtures\web\controllers;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'root-index'];
    }
}
