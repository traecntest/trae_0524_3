#!/bin/bash

echo "=== SmartHome Hub 启动脚本 ==="
echo ""

echo "1. 检查 PHP 环境..."
if ! command -v php &> /dev/null; then
    echo "✗ 请先安装 PHP 8.1+"
    exit 1
fi
echo "✓ PHP 版本: $(php -v | head -1)"

echo ""
echo "2. 检查目录结构..."
mkdir -p storage/logs
echo "✓ 目录结构已就绪"

echo ""
echo "3. 创建配置文件..."
if [ ! -f config/config.php ]; then
    cp config/config.php.example config/config.php 2>/dev/null || true
    echo "✓ 配置文件已创建"
else
    echo "✓ 配置文件已存在"
fi

echo ""
echo "4. 数据库迁移..."
php migrations/run.php

echo ""
echo "5. 启动服务..."
echo "  Web 服务器: http://localhost:8080"
echo "  WebSocket: ws://localhost:8081"
echo ""
echo "  可用命令:"
echo "    - 迁移数据库: php migrations/run.php"
echo "    - 演示脚本: php scripts/matter_demo.php"
echo "    - WebSocket: php scripts/websocket_server.php"
echo "    - 自动化引擎: php scripts/automation_engine.php"
echo ""
echo "按 Ctrl+C 停止所有服务"
echo ""

trap "kill 0" EXIT

php -S localhost:8080 router.php &
php scripts/websocket_server.php &
php scripts/automation_engine.php &

wait
