<?php

return [
    'app' => [
        'name' => 'SmartHome Hub',
        'version' => '1.0.0',
        'debug' => true,
        'timezone' => 'Asia/Shanghai',
        'base_url' => 'http://localhost:8080',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 5432,
        'database' => 'smarthome',
        'username' => 'smarthome',
        'password' => 'smarthome123',
        'charset' => 'utf8mb4',
    ],
    'matter' => [
        'bridge_ip' => '127.0.0.1',
        'bridge_port' => 5540,
        'controller_port' => 5580,
        'commissioning_timeout' => 120,
        'device_discovery_timeout' => 30,
    ],
    'websocket' => [
        'host' => '0.0.0.0',
        'port' => 8081,
        'reconnect_interval' => 3000,
    ],
    'automation' => [
        'check_interval' => 5,
        'max_conditions' => 10,
        'max_actions' => 10,
    ],
    'logging' => [
        'path' => __DIR__ . '/../storage/logs',
        'level' => 'debug',
        'max_files' => 30,
    ],
    'security' => [
        'session_lifetime' => 86400,
        'password_min_length' => 8,
        'rate_limit' => 60,
    ],
];
