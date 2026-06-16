<?php

namespace app\web\controllers;

use Adige\core\controller\BaseController;
use app\web\models\TicketOrders;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        $order = TicketOrders::find()
            ->where(['id' => 1])
            ->asArray()
            ->one();

        return $this->respond([
            'hello' => 'world',
            'message' => $this->request->get('message') ?? 'No message',
            'order' => $order->toArray(),
        ]);
    }
}
