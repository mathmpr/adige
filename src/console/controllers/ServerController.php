<?php

namespace Adige\console\controllers;

use Adige\core\http\socket\Server;

class ServerController extends BaseController
{
    /**
     * start php pure web server
     * @param int|null $port which port will the server listen to (default is 8080)
     * @param string|null $host which host will the server listen to (default is localhost)
     * @param string|null $documentRoot the document root where is located your index.php (default is ./)
     * @return void
     */
    public function actionStart(string|null $host = 'localhost', int|null $port = 8080, null|string $documentRoot = './'): void
    {
        Server::start($port, $host, $documentRoot);
    }
}
