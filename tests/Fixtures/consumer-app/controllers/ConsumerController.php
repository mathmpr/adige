<?php

namespace Tests\Fixtures\consumerapp\controllers;

use Adige\core\controller\BaseController;

class ConsumerController extends BaseController
{
    public function actionIndex(): array
    {
        return ['fixture' => 'consumer-index'];
    }

    public function actionPing(): string
    {
        return 'pong';
    }
}
