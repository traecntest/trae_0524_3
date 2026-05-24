<?php

declare(strict_types=1);

namespace App\Services\WebSocket;

class WebSocketServer
{
    private $socket;
    private array $clients = [];
    private string $host;
    private int $port;
    private bool $running = true;

    public function __construct(string $host = '0.0.0.0', int $port = 8081)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function start(): void
    {
        echo "[WebSocket] 启动服务器: {$this->host}:{$this->port}\n";

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket);

        echo "[WebSocket] 服务器已启动，等待连接...\n";

        $this->clients[] = $this->socket;

        while ($this->running) {
            $read = $this->clients;
            $write = null;
            $except = null;

            if (socket_select($read, $write, $except, null) === false) {
                break;
            }

            foreach ($read as $client) {
                if ($client === $this->socket) {
                    $this->acceptNewClient();
                } else {
                    $this->handleClientData($client);
                }
            }
        }

        $this->stop();
    }

    private function acceptNewClient(): void
    {
        $newClient = socket_accept($this->socket);

        if ($newClient === false) {
            return;
        }

        $this->performHandshake($newClient);
        $this->clients[] = $newClient;

        echo "[WebSocket] 新客户端连接\n";
    }

    private function performHandshake($client): void
    {
        $data = socket_read($client, 2048);

        if (preg_match('/Sec-WebSocket-Key: (.+)/', $data, $matches)) {
            $key = trim($matches[1]);
            $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $response = "HTTP/1.1 101 Switching Protocols\r\n";
            $response .= "Upgrade: websocket\r\n";
            $response .= "Connection: Upgrade\r\n";
            $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n";
            $response .= "\r\n";

            socket_write($client, $response);
        }
    }

    private function handleClientData($client): void
    {
        $data = @socket_read($client, 2048);

        if ($data === false || $data === '') {
            $this->disconnectClient($client);
            return;
        }

        $message = $this->decode($data);
        if ($message === null) {
            return;
        }

        if ($message === 'ping') {
            $this->send($client, 'pong');
        }
    }

    private function disconnectClient($client): void
    {
        $index = array_search($client, $this->clients);
        if ($index !== false) {
            unset($this->clients[$index]);
            socket_close($client);
            echo "[WebSocket] 客户端断开连接\n";
        }
    }

    public function broadcast(string $message): void
    {
        $encoded = $this->encode($message);

        foreach ($this->clients as $client) {
            if ($client !== $this->socket) {
                @socket_write($client, $encoded);
            }
        }
    }

    public function send($client, string $message): void
    {
        $encoded = $this->encode($message);
        @socket_write($client, $encoded);
    }

    private function encode(string $message): string
    {
        $length = strlen($message);
        $encoded = '';

        if ($length <= 125) {
            $encoded = chr(0x81) . chr($length);
        } elseif ($length <= 65535) {
            $encoded = chr(0x81) . chr(126) . pack('n', $length);
        } else {
            $encoded = chr(0x81) . chr(127) . pack('J', $length);
        }

        return $encoded . $message;
    }

    private function decode(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }

        $length = ord($data[1]) & 127;

        if ($length === 126) {
            $data = substr($data, 4);
        } elseif ($length === 127) {
            $data = substr($data, 10);
        } else {
            $data = substr($data, 2);
        }

        return $data;
    }

    public function stop(): void
    {
        $this->running = false;

        foreach ($this->clients as $client) {
            if ($client !== $this->socket) {
                @socket_close($client);
            }
        }

        @socket_close($this->socket);
        echo "[WebSocket] 服务器已停止\n";
    }
}
