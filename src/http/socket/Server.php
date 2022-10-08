<?php

namespace Adige\http\socket;

use Adige\cli\Output;
use Adige\file\File;

class Server
{
    private string|int $port;
    private string $documentRoot;
    private int $allToIndex;
    private array $pids = [];

    CONST PID_STATUS_IN_USE = 'in use';
    CONST PID_STATUS_CLOSED = 'closed';

    /**
     * start php pure web server
     * @param string|int $port
     * @return ?Server
     */
    public static function start(string|int $port = 8085, int $all_to_index = 0, string $document_root = '/'): ?Server
    {
        return (new self($port, $all_to_index, $document_root))->serve();
    }

    public function __construct(string|int $port, int $allToIndex, string $documentRoot)
    {
        $this->port = $port;
        $this->documentRoot = $documentRoot;
        $this->allToIndex = $allToIndex;
    }

    public function setPidStatus($pid, $status = self::PID_STATUS_CLOSED): Server
    {
        $this->pids[$pid] = $status;
        return $this;
    }

    private function parseRequest(string $request): Request
    {
        return new Request($request);
    }

    private function isolatedContext(?Connection &$connection = null, ?Request &$request = null, ?Response &$response = null): void
    {
        ob_start();

        $response->contentHtml();

        $that = $this;

        $_REQUEST = array_merge($request->getGet(), $request->getPost());
        $_GET = $request->getGet();
        $_POST = $request->getPost();
        $root = str_replace('//', '/', ADIGE_ROOT . $this->documentRoot . '/');
        $_SERVER = [
            'HEADERS' => $request->headers,
            'REQUEST_URI' => $request->getUri(),
            'REQUEST_METHOD' => $request->getMethod(),
            'PHP_SELF' => $request->getFile(),
            'DOCUMENT_ROOT' => $root,
        ];

        if (is_dir($root)) {
            $path = str_replace('//', '/', $root . $request->getFile());
            $index = $root . 'index.php';

            register_shutdown_function(function () use ($connection, $response, $that) {

                $error = error_get_last();

                if(ob_get_level()) {
                    ob_end_clean();
                }

                if ($error !== NULL && $connection) {
                    $errno = $error["type"];
                    $errfile = $error["file"];
                    $errline = $error["line"];
                    $errstr = $error["message"];

                    $content =
                        "<pre>" . print_r([
                            "type" => $errno,
                            "file" => $errfile,
                            "line" => $errline,
                            "message" => $errstr,
                        ], true) .
                        "</pre>";

                    $response->contentHtml();
                    $response->setCode(502);
                    $response->setContent(trim($content));

                    $connection->write($response, true);
                }
            });

            if (is_dir($path) && file_exists($path . '/index.php')) {
                $_SERVER['PHP_SELF'] .= '/index.php';
                $_SERVER['PHP_SELF'] = str_replace('//', '/', $_SERVER['PHP_SELF']);
                $path .= '/index.php';
                require $path;
            } else {
                if (file_exists($path) && !is_dir($path)) {
                    require $path;
                } else if (file_exists($index) && $this->allToIndex > 0) {
                    $_SERVER['PHP_SELF'] .= 'index.php';
                    $_SERVER['PHP_SELF'] = str_replace('//', '/', $_SERVER['PHP_SELF']);
                    require $index;
                } else {
                    $response->setCode(404);
                    $response->setContent('<h1>404 not found</h1>');
                }
            }
        }

        $response->appendContent(ob_get_clean());
    }

    /**
     * @return ?Server
     */
    public function serve(): ?Server
    {
        $error = '';
        $errorMessage = '';
        $stream = stream_socket_server(
            "tcp://0.0.0.0:{$this->port}",
            $error,
            $errorMessage,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            stream_context_create([
                'socket' => [
                    'tcp_nodelay' => false,
                ]
            ]));

        if ($stream) {

            stream_set_read_buffer($stream, 4096);
            stream_set_blocking($stream, 0);

            while (true) {

                $client = @stream_socket_accept($stream, 120);

                if ($client === false) {
                    Output::yellow("after 120 secconds no clients are connected\n", true);
                    usleep(100);
                } else {
                    $pid = pcntl_fork();
                    if ($pid == -1) {
                        Output::red("fork main PID to child PID failed.\n", true);
                        exit;
                    } else if (!$pid) {

                        $pid = getmypid();

                        $this->pids[$pid] = self::PID_STATUS_IN_USE;

                        $connection = new Connection($client, $pid, $this);
                        $request = $connection->read();

                        if (empty(trim($request))) {
                            $connection->close();
                            continue;
                        }

                        Output::yellow("request recevied at " . date("Y-m-d H:i:s.u") . "\n", true);

                        $root = trim(str_replace('//', '/', ADIGE_ROOT . $this->documentRoot . '/')) . '/';

                        $request = $this->parseRequest($request);
                        $response = new Response();

                        $file = new File(str_replace('//', '/', $root . $request->getFile()));

                        if ($file->exists()) {

                            $totalSize = filesize($file->getLocation());

                            $response->headers->setHeaders([
                                "accept-ranges" => "bytes",
                                "content-type" => File::ext2mime($file->getExtension()),
                                "content-length" => $totalSize,
                            ]);

                            if (!is_null($request->headers->range)) {
                                $bytes = explode('-', str_replace('bytes=', '', $request->headers->range));
                                $from = intval(trim($bytes[0]));
                                $to = $totalSize;

                                if (!empty($bytes[1])) {
                                    $to = intval($bytes[1]);
                                }

                                if ($from > 0 && empty(trim($bytes[1]))) {
                                    $length = $totalSize - $from;
                                } else {
                                    $length = $to - $from;
                                }

                                $response->setCode(206);
                                $response->headers->contentLength = $length;
                                $response->headers->contentRange = "bytes $from-" . ($to - 1) . "/$totalSize";

                                $fopen = fopen($file->getLocation(), 'r');
                                fseek($fopen, $from);
                                $response->setContent(fread($fopen, $length));
                                fclose($fopen);

                            } else {
                                $response->setContent(file_get_contents($file->getLocation()));
                            }

                            $connection->write($response, true);
                            continue;
                        }

                        $this->isolatedContext($connection, $request, $response);

                        $connection->write($response, true);
                    }
                }

                while (pcntl_waitpid(0, $status) != -1) {
                    $status = pcntl_wexitstatus($status);
                }
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getPids(): array
    {
        return $this->pids;
    }
}

