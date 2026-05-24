#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

use App\Database\Database;
use DatabaseMigration;

$config = require __DIR__ . '/../config/config.php';

echo "SmartHome Hub 数据库迁移\n";
echo str_repeat('=', 50) . "\n\n";

try {
    Database::configure($config['database']);
    $pdo = Database::connection();

    echo "✓ 数据库连接成功\n\n";

    $migration = new DatabaseMigration($pdo);
    $results = $migration->migrate();

    foreach ($results as $result) {
        echo "  {$result}\n";
    }

    echo "\n✓ 迁移完成\n";
} catch (\Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
