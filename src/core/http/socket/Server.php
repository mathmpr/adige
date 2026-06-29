<?php

namespace Adige\core\http\socket;

use Adige\cli\Output;
use Adige\core\Adige;
use Adige\core\BaseEnvironment;
use Adige\core\BaseObject;

class Server extends BaseObject
{

    private string|int $port;

    private string $host;

    private string|int $documentRoot = './';

    /**
     * start php pure web server
     * @param string|int $port
     * @param string $host
     * @param string $document_root
     * @return Server
     */
    public static function start(
        string|int $port = 8080,
        string $host = 'localhost',
        string $document_root = './'
    ): Server {
        $originalPort = $port;
        $port = self::checkIfPortIsBusy($port);
        if ($port !== $originalPort) {
            Output::white("Port " . $originalPort . " is busy, using port " . $port . " instead\n")
                ->bgRed()
                ->output();
        }
        return new self($port, $host, $document_root);
    }

    public function __construct(string|int $port, string $host, string $document_root = './')
    {
        $this->port = $port;
        $this->host = $host;
        $this->documentRoot = $document_root;
        $this->serve();
        parent::__construct();
    }

    /**
     * @return void
     */
    public function serve(): void
    {
        Output::green("Server started at http://" . $this->host . ":" . $this->port . "\n")
            ->output();
        exec(sprintf(
            'php -S %s:%s -t %s',
            $this->host,
            $this->port,
            escapeshellarg($this->resolveDocumentRoot())
        ));
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
                'netstat' => [
                    'command' => "netstat -an | find \"LISTENING\" | find \":%s\"",
                    'eval' => 'return $result == 0;',
                ],
                'powershell' => [
                    'command' => "powershell Get-NetTCPConnection -LocalPort %s",
                    'eval' => 'return !empty($output);'
                ],

            ],
            BaseEnvironment::SO_LINUX => [
                'netstat' => [
                    'command' => "netstat -an | grep \":%s\"",
                    'eval' => 'return $result == 0;',
                ],
                'ss' => [
                    'command' => "ss -tln | grep \":%s\"",
                    'eval' => 'return $result == 0;'
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

    private function resolveDocumentRoot(): string
    {
        if ($this->isAbsolutePath((string) $this->documentRoot)) {
            return (string) $this->documentRoot;
        }

        return rtrim(Adige::basePath(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim((string) $this->documentRoot, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}
