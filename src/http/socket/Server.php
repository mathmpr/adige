<?php

namespace Adige\http\socket;

use Adige\cli\Output;
use Exception;

class Server
{

    private string|int $port;
    private string $documentRoot;

    /**
     * start php pure web server
     * @param string|int $port
     * @return Server
     */
    public static function start(string|int $port = 8085, string $document_root = '/index'): Server
    {
        return new self($port, $document_root);
    }

    public function __construct(string|int $port, string $documentRoot)
    {
        $this->port = $port;
        $this->documentRoot = $documentRoot;
        $this->serve();
    }

    private function parseRequest(string $request): array
    {
        $result = [
            'post' => [],
            'get' => [],
        ];

        $content = explode("\r\n\r\n", $request);
        $requestBody = trim(array_pop($content));
        $content = explode("\r\n", array_shift($content));
        $firstLine = trim(array_shift($content));
        $firstLine = explode(' ', $firstLine);
        $result['method'] = $firstLine[0];
        $result['uri'] = $firstLine[1];
        $get = explode('?', $result['uri']);
        if(count($get) > 1){
            parse_str($get[1], $result['get']);
        }
        $result['file'] = array_shift($get);
        $result['headers'] = array_values($content);
        if (!empty($requestBody)) {
            try {
                $body = json_decode($requestBody, true, 512, JSON_THROW_ON_ERROR);
                $result['post'] = $body;
            } catch (Exception $exception) {
                $res = [];
                parse_str($requestBody, $res);
                if (!empty($res)) {
                    $result['post'] = $res;
                }
            }
        }
        return $result;
    }

    private function isolatedContext($conn, array $request): string
    {
        ob_start();
        $_REQUEST = array_merge($request['get'], $request['post']);
        $_GET = $request['get'];
        $_POST = $request['post'];
        $root = str_replace('//', '/', ADIGE_ROOT . $this->documentRoot . '/');
        $_SERVER = [
            'headers'       => $request['headers'],
            'uri'           => $request['uri'],
            'method'        => $request['method'],
            'file'          => $request['file'],
            'document_root' => $root,
        ];

        if (is_dir($root)){
            $path = str_replace('//', '/', $root . $request['file']);
            $index = $root . 'index.php';
            register_shutdown_function(function() use ($conn){
                $errfile = "unknown file";
                $errstr  = "shutdown";
                $errno   = E_CORE_ERROR;
                $errline = 0;

                $error = error_get_last();

                Output::blue(print_r($error, true))->output();

                if($error !== NULL) {
                    $errno   = $error["type"];
                    $errfile = $error["file"];
                    $errline = $error["line"];
                    $errstr  = $error["message"];
                    socket_write($conn, implode("\r\n", array(
                            'HTTP/1.1 200 OK',
                            implode("\r\n", [
                                "Content-Type: text/html;charset=utf-8",
                                "Server: PHP 8.1.10"
                            ]),
                            "\r\n" . "<pre>". print_r([
                                "type" => $errno,
                                "file" => $errfile,
                                "line" => $errline,
                                "message" => $errstr,
                            ], true) ."</pre>")
                    ));
                }
            });
            try {
                if (file_exists($path) && !is_dir($path)) {
                    include_once $path;
                } else if(file_exists($index)) {
                    include_once $index;
                }
            } catch (Exception $exception) {
                Output::red($exception->getMessage() . "\n");
                Output::yellow($exception->getTraceAsString() . "\n");
            }

        }
        return ob_get_clean();
    }

    /**
     * @return void
     */
    public function serve(): void
    {
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
            Output::red("failed to create to socket: " . socket_strerror($sock) . "\n")->output(true);
            exit();
        }
        if (($ret = socket_bind($sock, '0.0.0.0', $this->port)) < 0) {
            Output::red("failed to bind to socket: " . socket_strerror($ret) . "\n")->output(true);
            exit();
        }
        if (($ret = socket_listen($sock, 0)) < 0) {
            Output::red("failed to listent to socket: " . socket_strerror($ret) . "\n")->output(true);
            exit();
        }

        Output::blue("Server is running on 0.0.0.0:" . $this->port . ", relax.\n")->output();
        while (true) {

            Output::cyan("begin main while\n")->output();

            $conn = socket_accept($sock);

            if ($conn < 0) {
                Output::red("error: " . socket_strerror($conn) . "\n");
                exit;
            } else if ($conn === false) {
                Output::yellow("sleeping\n")->output();
                usleep(100);
            } else {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    Output::red("fork failure.\n");
                    exit;
                } else if (!$pid) {
                    $request = '';

                    $null = null;
                    //$selects = [$conn];

                    //socket_select($selects, $null, $null, 5);

                    Output::red("before while\n")->output();
                    while (!str_ends_with($request, "\r\n\r\n")) {
                        Output::yellow("before read\n")->output();
                        $block = socket_read($conn, 128, PHP_BINARY_READ);
                        $request .= $block;
                        if (strlen($block) < 128) {
                            break;
                        }
                        Output::green("after read\n")->output();
                    }
                    Output::cyan("after while\n")->output();

                    socket_write($conn, implode("\r\n", array(
                        'HTTP/1.1 200 OK',
                        implode("\r\n", [
                            "Content-Type: text/html;charset=utf-8",
                            "Server: PHP 7"
                        ]),
                        "\r\n\r\n" . $this->isolatedContext($conn, $this->parseRequest($request))
                    )));
                    Output::cyan("after write\n")->output();
                    socket_close($conn);
                    Output::cyan("after close conn\n")->output();
                    unset($conn);
                } else {
                    socket_close($conn);
                    unset($conn);
                }
            }

            Output::cyan("end of main while\n")->output();

            while (pcntl_waitpid(0, $status) != -1) {
                $status = pcntl_wexitstatus($status);
            }

            Output::cyan("end of main while after kill childs\n\n\n\n\n\n")->output();
        }
    }
}

