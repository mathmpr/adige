<?php

use Adige\core\routing\Route;

use app\controllers\IndexController;

use app\middlewares\DefaultMiddleware;

Route::group(null, function () {
    Route::get('/', IndexController::class, 'actionIndex');
}, DefaultMiddleware::class);