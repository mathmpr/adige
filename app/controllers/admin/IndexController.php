<?php

namespace app\controllers\admin;

use Adige\core\controller\Controller;

class IndexController extends Controller
{
    public function actionIndex()
    {
        return respond([
            'hello' => 'world',
            'message' => $this->request->get('message') ?? 'No message',
        ]);
    }
}