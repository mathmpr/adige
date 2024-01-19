<?php

require_once __DIR__ . '/vendor/autoload.php';

const ROOT = __DIR__ . DIRECTORY_SEPARATOR;

require_once __DIR__ . '/app/bootstrap.php';

use Adige\core\Adige;

Adige::run();
