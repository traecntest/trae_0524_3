<?php

declare(strict_types=1);

namespace App\Services\WebSocket;

class WebSocketPush
{
    private static array $clients = [];
    private static ?string $pushUrl = null;

    public static function broadcast(array $message): void
    {
        $config = config('websocket');
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 8081;

        $payload = json_encode($message, JSON_UNESCAPED_UNICODE);

        $context = new \ZMQContext();
        $socket = $context->getSocket(\ZMQ::SOCKET_PUSH);

        try {
            $socket->connect("tcp://{$host}:{$port}");
            $socket->send($payload);
        } catch (\Exception $e) {
            logger('debug', 'WebSocket推送失败: ' . $e->getMessage());
        }
    }

    public static function sendToUser(int $userId, array $message): void
    {
        $message['user_id'] = $userId;
        self::broadcast($message);
    }

    public static function sendToFamily(int $familyId, array $message): void
    {
        $message['family_id'] = $familyId;
        self::broadcast($message);
    }

    public static function sendDeviceUpdate(int $deviceId, array $state): void
    {
        self::broadcast([
            'type' => 'device_update',
            'device_id' => $deviceId,
            'state' => $state,
            'timestamp' => time(),
        ]);
    }

    public static function sendSceneExecuted(int $sceneId, string $sceneName): void
    {
        self::broadcast([
            'type' => 'scene_executed',
            'scene_id' => $sceneId,
            'scene_name' => $sceneName,
            'timestamp' => time(),
        ]);
    }

    public static function sendAutomationTriggered(int $ruleId, string $ruleName): void
    {
        self::broadcast([
            'type' => 'automation_triggered',
            'rule_id' => $ruleId,
            'rule_name' => $ruleName,
            'timestamp' => time(),
        ]);
    }

    public static function sendAlert(array $alert): void
    {
        self::broadcast([
            'type' => 'new_alert',
            'alert' => $alert,
            'timestamp' => time(),
        ]);
    }

    public static function sendLog(array $log): void
    {
        self::broadcast([
            'type' => 'new_log',
            'log' => $log,
            'timestamp' => time(),
        ]);
    }
}
