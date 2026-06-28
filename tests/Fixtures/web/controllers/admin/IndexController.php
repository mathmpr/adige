<?php

namespace tests\Fixtures\web\controllers\admin;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'admin-index'];
    }
}
