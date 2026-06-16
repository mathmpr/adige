<?php

namespace tests\Fixtures\web\controllers\admin;

use Adige\core\controller\BaseController;

class LoginController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'admin-login-index'];
    }
}
