<?php

namespace tests\Fixtures\web\controllers;

use Adige\core\controller\BaseController;

class AdminController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'admin-controller-index'];
    }

    public function actionLogin()
    {
        return ['fixture' => 'admin-controller-login'];
    }

    public function actionUserLogin()
    {
        return ['fixture' => 'admin-controller-user-login'];
    }
}
