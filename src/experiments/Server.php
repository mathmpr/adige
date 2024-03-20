<?php

namespace Adige\experiments;

use Adige\cli\Output;

class Server
{

    private string|int $port;
    private string $content;
    private int $tick = 0;

    /**
     * start php pure web server
     * @param string|int $port
     * @return Server
     */
    public static function start(string|int $port = 8085): Server
    {
        return new self($port);
    }

    public function __construct(string|int $port)
    {
        $this->port = $port;
        $this->serve();
    }

    /**
     * @return void
     */
    public function serve(): void
    {
        $app = function ($request) {
            $body = <<<EOS
<!DOCTYPE html>
<html>
 <meta charset=utf-8>
 <title>Hello World!</title>
 <h1>Hello World!</h1>
 <pre>
 %s
</pre>
<form action="http://localhost:$this->port/?opa=opa" method="post">
    <input type="hidden" name="ok" value="ok">
    <button type="submit">Sub</button>
</form>
</html>
EOS;
            return array(
                '200 OK',
                array('Content-Type' => 'text/html;charset=utf-8'),
                sprintf($body, print_r([$request, $_REQUEST, $_POST, $_GET], true))
            );
        };

        $defaults = array(
            'Content-Type' => 'text/html',
            'Server' => 'PHP ' . phpversion()
        );

        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
            Output::red("failed to create to socket: " . socket_strerror($sock) . "\n")->output();
            exit();
        }
        if (($ret = socket_bind($sock, '0.0.0.0', $this->port)) < 0) {
            Output::red("failed to bind to socket: " . socket_strerror($ret) . "\n")->output();
            exit();
        }
        if (($ret = socket_listen($sock, 0)) < 0) {
            Output::red("failed to listent to socket: " . socket_strerror($ret) . "\n")->output();
            exit();
        }


        Output::blue("Server is running on 0.0.0.0:" . $this->port . ", relax.\n")->output();
        while (true) {

            /**
             * @var $conn \Socket
             */
            Output::green("in while\n")->output();
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
                    Output::yellow("child\n")->output();
                    $request = '';
                    while (!str_ends_with($request, "\r\n\r\n")) {
                        $block = socket_read($conn, 8, PHP_NORMAL_READ);
                        $request .= $block;
                    }
                    list($code, $headers, $body) = $app(trim($request));
                    $headers += $defaults;
                    if (!isset($headers['Content-Length'])) {
                        $headers['Content-Length'] = strlen($body);
                    }
                    $header = '';
                    foreach ($headers as $k => $v) {
                        $header .= $k . ': ' . $v . "\r\n";
                    }
                    Output::yellow("before write\n")->output();
                    socket_write($conn, implode("\r\n", array(
                        'HTTP/1.1 ' . $code,
                        $header,
                        $body
                    )));
                    Output::yellow("after write\n")->output();
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

