<?php

use Adige\core\BaseEnvironment;
use Adige\core\Adige;

require ROOT . "vendor/larapack/dd/src/helper.php";

Adige::commons();
BaseEnvironment::readEnv(ROOT . '/.env');

require_once ROOT . '/app/routes.php';