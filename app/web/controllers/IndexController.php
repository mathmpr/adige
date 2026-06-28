<?php

namespace app\web\controllers;

use Adige\core\controller\BaseController;
use app\web\models\TicketOrders;
use app\web\models\Tickets;

class IndexController extends BaseController
{
    public function actionIndex()
    {
        return $this->render('index');

        $ticket = Tickets::find()
            ->joinWith(['orders.transactions'])
            ->where(['id' => 1])
            ->andWhere(['ticket_order_transactions.id' => 1])
            ->asArray()
            ->one();

        return $this->respond([
            'hello' => 'world',
            'message' => $this->request->get('message') ?? 'No message',
            'ticket' => $ticket->toArray()
        ]);
    }
}
