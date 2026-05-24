#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

use App\Database\Database;
use App\Services\AutomationEngine;

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

echo "=== SmartHome Hub 自动化引擎 ===\n";
echo "检查间隔: {$config['automation']['check_interval']}秒\n";
echo str_repeat('=', 40) . "\n\n";

try {
    Database::configure($config['database']);
    echo "✓ 数据库连接成功\n\n";

    $engine = new AutomationEngine();
    echo "✓ 自动化引擎已启动\n";
    echo "按 Ctrl+C 停止运行\n\n";

    $engine->run();
} catch (\Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
