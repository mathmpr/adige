<?php

use Adige\core\routing\Route;
use app\web\controllers\IndexController;
use app\web\middlewares\DefaultMiddleware;

Route::group(null, function () {
    Route::get('/', IndexController::class, 'actionIndex');
}, DefaultMiddleware::class);