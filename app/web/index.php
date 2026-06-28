<?php

include '../../vendor/autoload.php';

use app\web\app\Adige;

const ROOT = __DIR__ . DIRECTORY_SEPARATOR . '../../';
const APP_ROOT = __DIR__ . DIRECTORY_SEPARATOR;

include_once './routes.php';

Adige::run();
