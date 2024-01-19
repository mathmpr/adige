<?php

namespace Adige\http\socket;

use Adige\cli\Output;
use Adige\core\BaseEnvironment;
use Adige\core\BaseObject;

class Server extends BaseObject
{

    private string|int $port;

    private string|int $documentRoot = './';

    /**
     * start php pure web server
     * @param string|int $port which port will the server listen to (default is 8080)
     * @param string $document_root the document root where is located your index.php (default is ./)
     * @return Server
     */
    public static function start(string|int $port = 8080, string $document_root = './'): Server
    {
        $originalPort = $port;
        $port = self::checkIfPortIsBusy($port);
        if ($port !== $originalPort) {
            Output::white("Port " . $originalPort . " is busy, using port " . $port . " instead\n")
                ->bgRed()
                ->output();
        }
        return new self($port, $document_root);
    }

    public function __construct(string|int $port, string $document_root = './')
    {
        $this->port = $port;
        $this->documentRoot = $document_root;
        $this->serve();
        parent::__construct();
    }

    /**
     * @return void
     */
    public function serve(): void
    {
        Output::green("Server started at http://localhost:" . $this->port . "\n")
            ->output();
        exec('php -S localhost:' . $this->port . ' -t ' . ROOT . $this->documentRoot);
    }

    /**
     * check if port is busy and return the next available port
     * @param int $port
     * @return int
     */
    private static function checkIfPortIsBusy(int $port): int
    {
        $commands = [
            BaseEnvironment::SO_WINDOWS => [
                'netstat'    => [
                    'command' => "netstat -an | find \"LISTENING\" | find \":%s\"",
                    'eval'    => 'return $result == 0;',
                ],
                'powershell' => [
                    'command' => "powershell Get-NetTCPConnection -LocalPort %s",
                    'eval'    => 'return !empty($output);'
                ],

            ],
            BaseEnvironment::SO_LINUX   => [
                'netstat' => [
                    'command' => "netstat -an | grep \":%s\"",
                    'eval'    => 'return $result == 0;',
                ],
                'ss'      => [
                    'command' => "ss -tln | grep \":%s\"",
                    'eval'    => 'return $result == 0;'
                ]
            ],
        ];
        $isWindows = BaseEnvironment::isWindows();
        $isLinux = BaseEnvironment::isLinux();

        if (!$isLinux && !$isWindows) {
            return $port;
        }

        $exists = $isWindows ? 'where' : 'which';
        $command = $commands[$isWindows ? BaseEnvironment::SO_WINDOWS : BaseEnvironment::SO_LINUX];
        $cmd = false;
        foreach ($command as $base => $cmd) {
            exec($exists . ' ' . $base, $output, $result);
            if (empty($output)) {
                continue;
            }
            break;
        }

        if ($cmd) {
            exec(sprintf($cmd['command'], $port), $output, $result);
            while (eval($cmd['eval'])) {
                $port++;
                exec(sprintf($cmd['command'], $port), $output, $result);
            }
        }
        return $port;
    }
}

