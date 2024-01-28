<?php

namespace app\controllers;

use Adige\core\controller\Controller;
use Adige\core\database\ActiveRecord;

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