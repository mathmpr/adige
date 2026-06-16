<?php

namespace tests\Fixtures\web\controllers;

use Adige\core\controller\BaseController;

class AlphaController extends BaseController
{
    public function actionBeta()
    {
        return ['fixture' => 'alpha-controller-beta'];
    }
}
