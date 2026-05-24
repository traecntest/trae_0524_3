#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

use App\Database\Database;
use App\Models\Device;
use App\Models\Family;
use App\Models\Room;
use App\Models\Log;
use App\Models\AutomationRule;
use App\Models\Scene;
use App\Services\Matter\MatterService;
use App\Services\WebSocket\WebSocketPush;

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

echo "=== SmartHome Hub 模拟设备演示 ===\n\n";

try {
    Database::configure($config['database']);
    echo "✓ 数据库连接成功\n\n";

    $matterService = new MatterService();
    $deviceModel = new Device();
    $familyModel = new Family();
    $logModel = new Log();

    echo "1. 检查 Matter 模拟设备列表\n";
    echo str_repeat('-', 40) . "\n";
    $simDevices = $matterService->getSimulationDevices();
    foreach ($simDevices as $uid => $device) {
        echo "  [{$uid}] {$device['name']} ({$device['type']}) - " . json_encode($device['state']) . "\n";
    }
    echo "\n";

    echo "2. 设备发现演示\n";
    echo str_repeat('-', 40) . "\n";
    $discovered = $matterService->discoverDevices();
    echo "  发现 " . count($discovered) . " 个设备:\n";
    foreach ($discovered as $device) {
        echo "    - {$device['name']} ({$device['type']})\n";
    }
    echo "\n";

    echo "3. 模拟设备入网\n";
    echo str_repeat('-', 40) . "\n";

    $families = $familyModel->all();
    if (empty($families)) {
        echo "  请先创建家庭\n";
        echo "  创建示例家庭...\n";

        $userModel = new \App\Models\User();
        $users = $userModel->all();
        $ownerId = !empty($users) ? (int) $users[0]['id'] : 1;

        $familyId = $familyModel->create([
            'name' => '示例家庭',
            'description' => '自动化演示家庭',
            'owner_id' => $ownerId,
            'address' => '',
        ]);
        $familyModel->addMember($familyId, $ownerId, 'owner');

        $roomModel = new Room();
        $roomModel->create(['family_id' => $familyId, 'name' => '客厅', 'type' => 'living_room', 'icon' => 'sofa', 'sort_order' => 1]);
        $roomModel->create(['family_id' => $familyId, 'name' => '卧室', 'type' => 'bedroom', 'icon' => 'bed', 'sort_order' => 2]);
        $roomModel->create(['family_id' => $familyId, 'name' => '厨房', 'type' => 'kitchen', 'icon' => 'kitchen', 'sort_order' => 3]);

        echo "  家庭和房间已创建\n";
    } else {
        $familyId = (int) $families[0]['id'];
    }

    echo "  使用家庭ID: {$familyId}\n\n";

    echo "4. 批量注册模拟设备\n";
    echo str_repeat('-', 40) . "\n";

    $roomModel = new Room();
    $rooms = $roomModel->getByFamily($familyId);
    $roomMap = [];
    foreach ($rooms as $room) {
        $roomMap[$room['name']] = (int) $room['id'];
    }

    $typeMap = [];
    $types = $deviceModel->getTypes();
    foreach ($types as $type) {
        $typeMap[$type['code']] = (int) $type['id'];
    }

    $deviceConfig = [
        'MATTER-LIGHT-001' => ['room' => '客厅', 'type' => 'light'],
        'MATTER-LIGHT-002' => ['room' => '卧室', 'type' => 'light'],
        'MATTER-SWITCH-001' => ['room' => '客厅', 'type' => 'switch'],
        'MATTER-OUTLET-001' => ['room' => '客厅', 'type' => 'outlet'],
        'MATTER-THERMOSTAT-001' => ['room' => '客厅', 'type' => 'thermostat'],
        'MATTER-SENSOR-MOTION-001' => ['room' => '客厅', 'type' => 'sensor_motion'],
        'MATTER-SENSOR-DOOR-001' => ['room' => '客厅', 'type' => 'sensor_door'],
        'MATTER-SENSOR-TEMP-001' => ['room' => '卧室', 'type' => 'sensor_temp'],
        'MATTER-FAN-001' => ['room' => '客厅', 'type' => 'fan'],
        'MATTER-CURTAIN-001' => ['room' => '客厅', 'type' => 'curtain'],
        'MATTER-LOCK-001' => ['room' => '客厅', 'type' => 'lock'],
        'MATTER-SPEAKER-001' => ['room' => '客厅', 'type' => 'speaker'],
    ];

    foreach ($deviceConfig as $uid => $config) {
        $existing = $deviceModel->findByMatterUid($uid);
        if ($existing) {
            echo "  跳过已存在: {$simDevices[$uid]['name']}\n";
            continue;
        }

        $deviceId = $deviceModel->create([
            'family_id' => $familyId,
            'room_id' => $roomMap[$config['room']] ?? null,
            'type_id' => $typeMap[$config['type']] ?? 1,
            'name' => $simDevices[$uid]['name'],
            'matter_unique_id' => $uid,
            'matter_node_id' => rand(1, 65535),
            'matter_endpoint' => 1,
            'matter_device_type' => $simDevices[$uid]['device_type'],
            'matter_vendor_id' => $simDevices[$uid]['vendor_id'],
            'matter_product_id' => $simDevices[$uid]['product_id'],
            'is_online' => true,
            'status' => 'online',
            'state' => json_encode($simDevices[$uid]['state']),
            'capabilities' => json_encode($simDevices[$uid]['capabilities']),
            'config' => json_encode([]),
        ]);

        $matterService->subscribe($uid, $deviceId);
        echo "  注册: {$simDevices[$uid]['name']} (ID: {$deviceId})\n";
    }
    echo "\n";

    echo "5. 设备控制演示\n";
    echo str_repeat('-', 40) . "\n";

    $devices = $deviceModel->getByFamily($familyId);

    $lightDevice = null;
    foreach ($devices as $d) {
        if (str_contains($d['matter_unique_id'] ?? '', 'LIGHT-001')) {
            $lightDevice = $d;
            break;
        }
    }

    if ($lightDevice) {
        echo "  控制: {$lightDevice['name']}\n";

        $result = $matterService->sendCommand($lightDevice, 'on');
        echo "    开灯: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
        sleep(1);

        $result = $matterService->sendCommand($lightDevice, 'set_brightness', ['brightness' => 80]);
        echo "    亮度80%: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
        sleep(1);

        $result = $matterService->sendCommand($lightDevice, 'set_colortemp', ['colortemp' => 3000]);
        echo "    色温3000K: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
        sleep(1);

        $result = $matterService->sendCommand($lightDevice, 'off');
        echo "    关灯: " . ($result['success'] ? '✓ 成功' : '✗ 失败') . "\n";
    }
    echo "\n";

    echo "6. 场景创建演示\n";
    echo str_repeat('-', 40) . "\n";

    $sceneModel = new Scene();
    $scenes = $sceneModel->getByFamily($familyId);

    if (empty($scenes)) {
        $sceneIds = [];

        $sceneIds['home'] = $sceneModel->create([
            'family_id' => $familyId, 'name' => '回家模式', 'icon' => 'home', 'color' => '#27ae60',
            'description' => '回家时自动开启灯光和空调', 'is_favorite' => true, 'sort_order' => 1,
        ]);

        $sceneIds['leave'] = $sceneModel->create([
            'family_id' => $familyId, 'name' => '离家模式', 'icon' => 'walk', 'color' => '#e74c3c',
            'description' => '离家时关闭所有设备', 'is_favorite' => true, 'sort_order' => 2,
        ]);

        $sceneIds['movie'] = $sceneModel->create([
            'family_id' => $familyId, 'name' => '观影模式', 'icon' => 'film', 'color' => '#8e44ad',
            'description' => '关闭主灯，开启氛围灯', 'is_favorite' => true, 'sort_order' => 3,
        ]);

        $sceneIds['sleep'] = $sceneModel->create([
            'family_id' => $familyId, 'name' => '睡眠模式', 'icon' => 'moon', 'color' => '#3498db',
            'description' => '关闭所有灯光，锁定门窗', 'is_favorite' => false, 'sort_order' => 4,
        ]);

        $lightId = null;
        $fanId = null;
        $curtainId = null;
        $lockId = null;
        $speakerId = null;

        foreach ($devices as $d) {
            $uid = $d['matter_unique_id'] ?? '';
            if (str_contains($uid, 'LIGHT-001')) $lightId = (int) $d['id'];
            elseif (str_contains($uid, 'FAN-001')) $fanId = (int) $d['id'];
            elseif (str_contains($uid, 'CURTAIN-001')) $curtainId = (int) $d['id'];
            elseif (str_contains($uid, 'LOCK-001')) $lockId = (int) $d['id'];
            elseif (str_contains($uid, 'SPEAKER-001')) $speakerId = (int) $d['id'];
        }

        $sceneActions = [
            'home' => [
                ['device_id' => $lightId, 'action_type' => 'on', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $lightId, 'action_type' => 'set_brightness', 'params' => ['brightness' => 100], 'delay_ms' => 100],
                ['device_id' => $fanId, 'action_type' => 'on', 'params' => [], 'delay_ms' => 500],
                ['device_id' => $curtainId, 'action_type' => 'open', 'params' => [], 'delay_ms' => 1000],
            ],
            'leave' => [
                ['device_id' => $lightId, 'action_type' => 'off', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $fanId, 'action_type' => 'off', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $curtainId, 'action_type' => 'close', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $lockId, 'action_type' => 'lock', 'params' => [], 'delay_ms' => 500],
            ],
            'movie' => [
                ['device_id' => $lightId, 'action_type' => 'on', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $lightId, 'action_type' => 'set_brightness', 'params' => ['brightness' => 20], 'delay_ms' => 200],
                ['device_id' => $lightId, 'action_type' => 'set_colortemp', 'params' => ['colortemp' => 2700], 'delay_ms' => 400],
                ['device_id' => $curtainId, 'action_type' => 'close', 'params' => [], 'delay_ms' => 600],
            ],
            'sleep' => [
                ['device_id' => $lightId, 'action_type' => 'off', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $fanId, 'action_type' => 'off', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $curtainId, 'action_type' => 'close', 'params' => [], 'delay_ms' => 0],
                ['device_id' => $lockId, 'action_type' => 'lock', 'params' => [], 'delay_ms' => 500],
            ],
        ];

        foreach ($sceneActions as $sceneKey => $actions) {
            $sortOrder = 0;
            foreach ($actions as $action) {
                if ($action['device_id']) {
                    $sceneModel->addAction(
                        $sceneIds[$sceneKey],
                        $action['device_id'],
                        $action['action_type'],
                        $action['params'],
                        $action['delay_ms'],
                        $sortOrder++
                    );
                }
            }
        }

        echo "  ✓ 已创建场景:\n";
        echo "    - 回家模式\n";
        echo "    - 离家模式\n";
        echo "    - 观影模式\n";
        echo "    - 睡眠模式\n";
    } else {
        echo "  场景已存在，跳过创建\n";
    }
    echo "\n";

    echo "7. 自动化规则创建演示\n";
    echo str_repeat('-', 40) . "\n";

    $automationModel = new AutomationRule();
    $existingRules = $automationModel->getByFamily($familyId);

    if (empty($existingRules)) {
        $motionSensorId = null;
        $doorSensorId = null;

        foreach ($devices as $d) {
            $uid = $d['matter_unique_id'] ?? '';
            if (str_contains($uid, 'MOTION-001')) $motionSensorId = (int) $d['id'];
            elseif (str_contains($uid, 'DOOR-001')) $doorSensorId = (int) $d['id'];
        }

        $automationModel->create([
            'family_id' => $familyId,
            'name' => '人体感应开灯',
            'description' => '检测到人体活动时自动开灯',
            'is_enabled' => true,
            'trigger_type' => 'device_state',
            'trigger_config' => json_encode([
                'device_id' => $motionSensorId,
                'field' => 'occupancy',
                'operator' => '==',
                'value' => true,
            ]),
            'conditions' => json_encode([
                'logic' => 'and',
                'rules' => [
                    ['type' => 'time_range', 'start' => '18:00', 'end' => '06:00'],
                ],
            ]),
            'actions' => json_encode([
                ['type' => 'device', 'device_id' => $lightId ?? 0, 'command' => 'on', 'params' => [], 'delay_ms' => 0],
                ['type' => 'notification', 'title' => '人体感应', 'message' => '客厅检测到人体活动'],
            ]),
        ]);

        $automationModel->create([
            'family_id' => $familyId,
            'name' => '门窗打开告警',
            'description' => '门窗被打开时发送告警',
            'is_enabled' => true,
            'trigger_type' => 'device_state',
            'trigger_config' => json_encode([
                'device_id' => $doorSensorId,
                'field' => 'contact',
                'operator' => '==',
                'value' => false,
            ]),
            'conditions' => json_encode(['logic' => 'and', 'rules' => []]),
            'actions' => json_encode([
                ['type' => 'alert', 'alert_type' => 'security', 'severity' => 'warning', 'title' => '门窗打开', 'message' => '入户门被打开了'],
                ['type' => 'notification', 'title' => '安全告警', 'message' => '入户门被打开'],
            ]),
        ]);

        $automationModel->create([
            'family_id' => $familyId,
            'name' => '定时关灯',
            'description' => '每天23:00自动关闭客厅灯',
            'is_enabled' => true,
            'trigger_type' => 'time',
            'trigger_config' => json_encode(['time' => '23:00', 'tolerance' => 60]),
            'conditions' => json_encode(['logic' => 'and', 'rules' => []]),
            'actions' => json_encode([
                ['type' => 'device', 'device_id' => $lightId ?? 0, 'command' => 'off', 'params' => [], 'delay_ms' => 0],
            ]),
        ]);

        echo "  ✓ 已创建自动化规则:\n";
        echo "    - 人体感应开灯 (18:00-06:00)\n";
        echo "    - 门窗打开告警\n";
        echo "    - 定时关灯 (23:00)\n";
    } else {
        echo "  规则已存在，跳过创建\n";
    }
    echo "\n";

    echo "8. 传感器模拟触发\n";
    echo str_repeat('-', 40) . "\n";

    echo "  触发人体传感器...\n";
    $matterService->simulateSensorTrigger('MATTER-SENSOR-MOTION-001', 'motion');
    sleep(1);

    echo "  触发门窗传感器...\n";
    $matterService->simulateSensorTrigger('MATTER-SENSOR-DOOR-001', 'door');
    echo "\n";

    echo "9. 设备状态查询\n";
    echo str_repeat('-', 40) . "\n";

    $devices = $deviceModel->getByFamily($familyId);
    foreach ($devices as $d) {
        $state = json_decode($d['state'] ?? '{}', true);
        $status = $d['is_online'] ? '在线' : '离线';
        echo "  [{$status}] {$d['name']}: " . json_encode($state) . "\n";
    }
    echo "\n";

    echo "10. 设备历史记录\n";
    echo str_repeat('-', 40) . "\n";

    $history = $deviceModel->getHistory($lightId ?? 0, 10);
    echo "  最近操作记录:\n";
    foreach ($history as $h) {
        $state = json_decode($h['state'] ?? '{}', true);
        echo "    [{$h['created_at']}] " . json_encode($state) . "\n";
    }
    echo "\n";

    echo "=== 演示完成 ===\n";
    echo "\n提示: 可使用以下命令启动服务:\n";
    echo "  - 启动 Web 服务器: php -S localhost:8080 -t public/\n";
    echo "  - 启动 WebSocket: php scripts/websocket_server.php\n";
    echo "  - 启动自动化引擎: php scripts/automation_engine.php\n";
    echo "\n";

} catch (\Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
