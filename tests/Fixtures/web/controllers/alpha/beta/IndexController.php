<?php

namespace tests\Fixtures\web\controllers\alpha\beta;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'alpha-beta-index'];
    }
}
