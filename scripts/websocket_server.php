#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

use App\Services\WebSocket\WebSocketServer;

$config = require __DIR__ . '/../config/config.php';
$wsConfig = $config['websocket'];

echo "=== SmartHome Hub WebSocket Server ===\n";
echo "Host: {$wsConfig['host']}\n";
echo "Port: {$wsConfig['port']}\n";
echo str_repeat('=', 40) . "\n\n";

$server = new WebSocketServer($wsConfig['host'], $wsConfig['port']);
$server->start();
