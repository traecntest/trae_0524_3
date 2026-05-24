<?php

declare(strict_types=1);

function json(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function config(string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    if ($key === null) {
        return $config;
    }

    $keys = explode('.', $key);
    $value = $config;
    foreach ($keys as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) {
            return $default;
        }
        $value = $value[$k];
    }

    return $value;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function generate_id(): string
{
    return bin2hex(random_bytes(16));
}

function logger(string $level, string $message, array $context = []): void
{
    $logDir = config('logging.path', __DIR__ . '/../storage/logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $entry = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );

    @file_put_contents($logFile, $entry, FILE_APPEND);
}

function sanitize_output(mixed $data): mixed
{
    if (is_array($data)) {
        return array_map('sanitize_output', $data);
    }
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}
