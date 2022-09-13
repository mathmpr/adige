<?php

namespace Adige\http\socket;

class Server {

    /**
     * start php pure web server
     * @param string $port
     * @return void
     */
    public static function start(string $port = '8081'): void {
        $app = function($request) {
            $body = <<<EOS
<!DOCTYPE html>
<html>
 <meta charset=utf-8>
 <title>Hello World!</title>
 <h1>Hello World!</h1>
</html>
EOS;
            return array(
                '200 OK',
                array('Content-Type' => 'text/html;charset=utf-8'),
                $body
            );
        };

        $defaults = array(
            'Content-Type' => 'text/html',
            'Server' => 'PHP '.phpversion()
        );

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
            echo 'failed to create socket : ', socket_strerror($sock), PHP_EOL;
            exit();
        }
        if (($ret = socket_bind($sock, '0.0.0.0', $port)) < 0) {
            echo 'failed to bind socket : ', socket_strerror($ret), PHP_EOL;
            exit();
        }
        if (($ret = socket_listen($sock, 0)) < 0) {
            echo 'failed to listent to socket : ', socket_strerror($ret), PHP_EOL;
            exit();
        }

        echo 'Server is running on 0.0.0.0:'. $port .', relax.', PHP_EOL;
        while (true) {
            $conn = socket_accept($sock);
            if ($conn < 0) {
                echo 'error: ', socket_strerror($conn), PHP_EOL;
                exit();
            } else if ($conn === false) {
                usleep(100);
            } else {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    echo 'fork failure: ', PHP_EOL;
                    exit();
                } else if (!$pid) {
                    socket_close($sock);
                    $request = '';
                    while (substr($request, -4) !== "\r\n\r\n") {
                        $request .= socket_read($conn, 1024);
                    }
                    list($code, $headers, $body) = $app($request);
                    $headers += $defaults;
                    if (!isset($headers['Content-Length'])) {
                        $headers['Content-Length'] = strlen($body);
                    }
                    $header = '';
                    foreach ($headers as $k => $v) {
                        $header .= $k.': '.$v."\r\n";
                    }
                    socket_write($conn, implode("\r\n", array(
                        'HTTP/1.1 '.$code,
                        $header,
                        $body
                    )));
                    socket_close($conn);
                    exit;
                } else {
                    socket_close($conn);
                }
            }

            while (pcntl_waitpid(0, $status) != -1) {
                $status = pcntl_wexitstatus($status);
            }

        }
    }

}

