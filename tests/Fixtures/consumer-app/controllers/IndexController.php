<?php

namespace Tests\Fixtures\consumerapp\controllers;

use Adige\core\controller\BaseController;

class IndexController extends BaseController
{
    public function actionIndex(): array
    {
        return ['fixture' => 'consumer-root'];
    }
}
