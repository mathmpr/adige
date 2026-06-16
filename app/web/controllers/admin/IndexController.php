<?php

namespace app\web\controllers\admin;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return $this->respond([
            'hello' => 'world',
            'message' => $this->request->get('message') ?? 'No message',
        ]);
    }
}
