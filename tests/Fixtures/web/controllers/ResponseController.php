<?php

namespace tests\Fixtures\web\controllers;

use Adige\core\controller\BaseController;
use Adige\core\http\http\RedirectResponse;

class ResponseController extends BaseController
{
    public function actionJson(): array
    {
        return [
            'fixture' => 'json-response',
        ];
    }

    public function actionRedirect(): RedirectResponse
    {
        return new RedirectResponse('/target');
    }

    public function actionError(): never
    {
        throw new \RuntimeException('boom');
    }
}
