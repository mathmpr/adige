<?php

namespace tests\Fixtures\web\controllers\alpha;

use Adige\core\controller\BaseController;

class BetaController extends BaseController
{
    public function actionIndex()
    {
        return ['fixture' => 'alpha-beta-controller-index'];
    }
}
