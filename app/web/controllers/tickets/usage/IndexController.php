<?php

namespace app\web\controllers\tickets\usage;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return $this->respond([
            'hello' => 'meu home'
        ]);
    }
}
