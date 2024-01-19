<?php

namespace app\controllers;

use Adige\core\controller\Controller;
use Adige\http\http\Response;

class IndexController extends Controller
{
    public function actionIndex(): Response
    {
        return respond([
            'hello' => 'world',
            'message' => $this->request->get('message') ?? 'No message',
        ]);
    }
}