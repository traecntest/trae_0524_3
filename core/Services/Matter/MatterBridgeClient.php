<?php

declare(strict_types=1);

namespace App\Services\Matter;

class MatterBridgeClient
{
    private string $bridgeIp;
    private int $bridgePort;
    private int $timeout;

    public function __construct()
    {
        $config = config('matter');
        $this->bridgeIp = $config['bridge_ip'];
        $this->bridgePort = $config['bridge_port'];
        $this->timeout = $config['device_discovery_timeout'];
    }

    public function sendRequest(string $endpoint, array $payload = [], string $method = 'POST'): array
    {
        $url = "http://{$this->bridgeIp}:{$this->bridgePort}{$endpoint}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $data = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $data];
        }

        return ['success' => false, 'error' => $data['error'] ?? "HTTP {$httpCode}", 'data' => $data];
    }

    public function discover(): array
    {
        return $this->sendRequest('/api/v1/discover', [], 'GET');
    }

    public function commission(string $matterUniqueId): array
    {
        return $this->sendRequest('/api/v1/commission', [
            'unique_id' => $matterUniqueId,
        ]);
    }

    public function decommission(string $matterUniqueId): array
    {
        return $this->sendRequest('/api/v1/decommission', [
            'unique_id' => $matterUniqueId,
        ]);
    }

    public function sendCommand(string $matterUniqueId, string $command, array $params = []): array
    {
        return $this->sendRequest('/api/v1/command', [
            'unique_id' => $matterUniqueId,
            'command' => $command,
            'params' => $params,
        ]);
    }

    public function subscribe(string $matterUniqueId, int $deviceId): array
    {
        return $this->sendRequest('/api/v1/subscribe', [
            'unique_id' => $matterUniqueId,
            'device_id' => $deviceId,
            'callback_url' => config('app.base_url') . '/api/matter/callback',
        ]);
    }

    public function getDeviceList(): array
    {
        return $this->sendRequest('/api/v1/devices', [], 'GET');
    }

    public function getBridgeStatus(): array
    {
        $url = "http://{$this->bridgeIp}:{$this->bridgePort}/api/v1/status";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'offline', 'error' => $error];
        }

        $data = json_decode($response, true);
        return [
            'status' => $httpCode === 200 ? 'online' : 'error',
            'info' => $data,
        ];
    }
}
