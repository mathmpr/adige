<?php

use Adige\core\BaseEnvironment;
use Adige\core\Adige;
use Adige\core\BaseConfig;
use Adige\core\database\Connection;

require ROOT . "vendor/larapack/dd/src/helper.php";
Adige::commons();
BaseEnvironment::readEnv(ROOT . '/.env');
$root = new BaseConfig('root', require ROOT . '/app/config.php');
Connection::setDefaultConnection($root->get('connections.default'));

require_once ROOT . '/app/routes.php';
