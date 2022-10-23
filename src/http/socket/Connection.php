<?php

namespace Adige\http\socket;

use Adige\cli\Output;

class Connection
{

    /**
     * @var resource
     */
    private $client;
    private string|int $pid;
    private Server $server;
    private bool $closed = false;

    public function __construct($resource, $pid, Server $server)
    {
        $this->client = $resource;
        $this->pid = $pid;
        $this->server = $server;
        stream_set_chunk_size($this->client, 1024 * 1000 * 1000);
        stream_set_timeout($this->client, 1024);
        stream_set_blocking($this->client, 0);
        $null = null;
        $reads = [$this->client];
        $writes = [$this->client];
        stream_select($reads, $writes, $null, 5);
    }

    public function write($data, $close = false): bool
    {
        if (!$this->closed) {
            stream_set_write_buffer($this->client, 1024);
            if(stream_socket_sendto($this->client, (string)$data)) {
                if ($close) {
                    Output::blue("server send response at " . date("Y-m-d H:i:s.u") . "\n", Output::INSTANT);
                    $this->close();
                }
                return true;
            }
        }
        return false;
    }

    public function read(): string
    {
        if ($this->closed) {
            return '';
        }
        $allLength = 0;
        $waitPacket = 0;
        $readBytes = 32;
        $ampBytes = 64;
        $ampIn = 65535;
        $nextAmp = 65535;
        $totalPacketsForWait = 10;
        $retryPackets = 4;
        $waitForNextPacket = 150;
        $request = '';
        while (!feof($this->client)) {
            $block = @fread($this->client, $readBytes);
            $request .= $block;
            if ($allLength != strlen($request)) {
                $allLength = strlen($request);
            } else {
                if ($waitPacket >= $totalPacketsForWait) {
                    break;
                }
                usleep($waitForNextPacket);
                $waitPacket += 1;
                continue;
            }

            if ($allLength > $ampIn) {
                $ampIn += $nextAmp;
                $readBytes += $ampBytes;
                $totalPacketsForWait += $retryPackets;
            }
        }
        return $request;
    }

    public function close() : void
    {
        stream_socket_shutdown($this->client, STREAM_SHUT_WR);
        $this->closed = true;
        $this->server->setPidStatus($this->pid);
        exit;
    }

}