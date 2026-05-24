<?php

declare(strict_types=1);

namespace App\Services\Matter;

use App\Models\Device;
use App\Models\Log;
use App\Services\WebSocket\WebSocketPush;

class MatterService
{
    private MatterBridgeClient $bridgeClient;
    private Device $deviceModel;
    private Log $logModel;
    private bool $simulationMode = true;

    private array $simulatedDevices = [];

    public function __construct()
    {
        $this->bridgeClient = new MatterBridgeClient();
        $this->deviceModel = new Device();
        $this->logModel = new Log();

        $this->initSimulatedDevices();
    }

    private function initSimulatedDevices(): void
    {
        $this->simulatedDevices = [
            'MATTER-LIGHT-001' => [
                'name' => '客厅主灯',
                'type' => 'light',
                'device_type' => 0x0100,
                'vendor_id' => 0xFFF1,
                'product_id' => 0x0001,
                'capabilities' => ['onoff', 'brightness', 'colortemp', 'color'],
                'state' => ['on' => false, 'brightness' => 100, 'colortemp' => 4000, 'color' => '#ffffff'],
                'online' => true,
            ],
            'MATTER-LIGHT-002' => [
                'name' => '卧室灯',
                'type' => 'light',
                'device_type' => 0x0100,
                'vendor_id' => 0xFFF1,
                'product_id' => 0x0002,
                'capabilities' => ['onoff', 'brightness'],
                'state' => ['on' => false, 'brightness' => 80],
                'online' => true,
            ],
            'MATTER-SWITCH-001' => [
                'name' => '智能开关-客厅',
                'type' => 'switch',
                'device_type' => 0x0103,
                'vendor_id' => 0xFFF2,
                'product_id' => 0x0001,
                'capabilities' => ['onoff'],
                'state' => ['on' => false],
                'online' => true,
            ],
            'MATTER-OUTLET-001' => [
                'name' => '智能插座-电视',
                'type' => 'outlet',
                'device_type' => 0x010A,
                'vendor_id' => 0xFFF3,
                'product_id' => 0x0001,
                'capabilities' => ['onoff', 'power'],
                'state' => ['on' => false, 'power' => 0],
                'online' => true,
            ],
            'MATTER-THERMOSTAT-001' => [
                'name' => '客厅温控器',
                'type' => 'thermostat',
                'device_type' => 0x0301,
                'vendor_id' => 0xFFF4,
                'product_id' => 0x0001,
                'capabilities' => ['temperature', 'humidity', 'mode', 'setpoint'],
                'state' => ['temperature' => 22.5, 'humidity' => 45, 'mode' => 'auto', 'setpoint' => 24],
                'online' => true,
            ],
            'MATTER-SENSOR-MOTION-001' => [
                'name' => '客厅人体传感器',
                'type' => 'sensor_motion',
                'device_type' => 0x0107,
                'vendor_id' => 0xFFF5,
                'product_id' => 0x0001,
                'capabilities' => ['occupancy', 'battery'],
                'state' => ['occupancy' => false, 'battery' => 85],
                'online' => true,
            ],
            'MATTER-SENSOR-DOOR-001' => [
                'name' => '入户门传感器',
                'type' => 'sensor_door',
                'device_type' => 0x0015,
                'vendor_id' => 0xFFF5,
                'product_id' => 0x0002,
                'capabilities' => ['contact', 'battery'],
                'state' => ['contact' => true, 'battery' => 90],
                'online' => true,
            ],
            'MATTER-SENSOR-TEMP-001' => [
                'name' => '卧室温湿度传感器',
                'type' => 'sensor_temp',
                'device_type' => 0x0302,
                'vendor_id' => 0xFFF5,
                'product_id' => 0x0003,
                'capabilities' => ['temperature', 'humidity', 'battery'],
                'state' => ['temperature' => 23.1, 'humidity' => 48, 'battery' => 78],
                'online' => true,
            ],
            'MATTER-FAN-001' => [
                'name' => '客厅风扇',
                'type' => 'fan',
                'device_type' => 0x002B,
                'vendor_id' => 0xFFF6,
                'product_id' => 0x0001,
                'capabilities' => ['onoff', 'speed', 'mode'],
                'state' => ['on' => false, 'speed' => 2, 'mode' => 'normal'],
                'online' => true,
            ],
            'MATTER-CURTAIN-001' => [
                'name' => '客厅窗帘',
                'type' => 'curtain',
                'device_type' => 0x0202,
                'vendor_id' => 0xFFF7,
                'product_id' => 0x0001,
                'capabilities' => ['position', 'direction'],
                'state' => ['position' => 100, 'direction' => 'stopped'],
                'online' => true,
            ],
            'MATTER-LOCK-001' => [
                'name' => '智能门锁',
                'type' => 'lock',
                'device_type' => 0x000A,
                'vendor_id' => 0xFFF8,
                'product_id' => 0x0001,
                'capabilities' => ['lockstate', 'battery'],
                'state' => ['lockstate' => 'locked', 'battery' => 95],
                'online' => true,
            ],
            'MATTER-SPEAKER-001' => [
                'name' => '智能音箱',
                'type' => 'speaker',
                'device_type' => 0x0022,
                'vendor_id' => 0xFFF9,
                'product_id' => 0x0001,
                'capabilities' => ['volume', 'playback'],
                'state' => ['volume' => 30, 'playback' => 'stopped'],
                'online' => true,
            ],
        ];
    }

    public function getBridgeStatus(): array
    {
        if ($this->simulationMode) {
            return [
                'status' => 'online',
                'mode' => 'simulation',
                'paired_devices' => count($this->simulatedDevices),
                'version' => '1.0.0-sim',
            ];
        }
        return $this->bridgeClient->getBridgeStatus();
    }

    public function discoverDevices(): array
    {
        if ($this->simulationMode) {
            $devices = [];
            foreach ($this->simulatedDevices as $uid => $device) {
                $devices[] = [
                    'matter_unique_id' => $uid,
                    'name' => $device['name'],
                    'type' => $device['type'],
                    'device_type' => $device['device_type'],
                    'vendor_id' => $device['vendor_id'],
                    'product_id' => $device['product_id'],
                    'online' => $device['online'],
                ];
            }
            return $devices;
        }

        $result = $this->bridgeClient->discover();
        return $result['success'] ? ($result['data']['devices'] ?? []) : [];
    }

    public function commission(string $matterUniqueId): array
    {
        if ($this->simulationMode) {
            if (!isset($this->simulatedDevices[$matterUniqueId])) {
                return ['success' => false, 'error' => '设备未找到'];
            }

            $device = $this->simulatedDevices[$matterUniqueId];
            return [
                'success' => true,
                'node_id' => rand(1, 65535),
                'endpoint' => 1,
                'device_type' => $device['device_type'],
                'vendor_id' => $device['vendor_id'],
                'product_id' => $device['product_id'],
                'state' => $device['state'],
                'capabilities' => $device['capabilities'],
            ];
        }

        $result = $this->bridgeClient->commission($matterUniqueId);
        return $result['success'] ? ['success' => true] + ($result['data'] ?? []) : $result;
    }

    public function pairDevice(string $matterUniqueId): array
    {
        return $this->commission($matterUniqueId);
    }

    public function unpair(string $matterUniqueId): array
    {
        if ($this->simulationMode) {
            return ['success' => true, 'message' => '设备已解除配对'];
        }
        return $this->bridgeClient->decommission($matterUniqueId);
    }

    public function sendCommand(array $device, string $action, array $params = []): array
    {
        $matterUid = $device['matter_unique_id'] ?? null;

        if ($this->simulationMode && $matterUid && isset($this->simulatedDevices[$matterUid])) {
            return $this->simulateCommand($matterUid, $action, $params);
        }

        if ($matterUid) {
            return $this->bridgeClient->sendCommand($matterUid, $action, $params);
        }

        return ['success' => true, 'simulated' => true];
    }

    private function simulateCommand(string $matterUid, string $action, array $params): array
    {
        if (!isset($this->simulatedDevices[$matterUid])) {
            return ['success' => false, 'error' => '设备未找到'];
        }

        $device = &$this->simulatedDevices[$matterUid];
        $state = &$device['state'];

        switch ($action) {
            case 'toggle':
                if (isset($state['on'])) {
                    $state['on'] = !$state['on'];
                } elseif (isset($state['lockstate'])) {
                    $state['lockstate'] = $state['lockstate'] === 'locked' ? 'unlocked' : 'locked';
                }
                break;

            case 'on':
                if (isset($state['on'])) $state['on'] = true;
                if (isset($state['playback'])) $state['playback'] = 'playing';
                break;

            case 'off':
                if (isset($state['on'])) $state['on'] = false;
                if (isset($state['playback'])) $state['playback'] = 'stopped';
                break;

            case 'set_brightness':
                if (isset($state['brightness'])) {
                    $state['brightness'] = max(0, min(100, (int)($params['brightness'] ?? $state['brightness'])));
                }
                break;

            case 'set_colortemp':
                if (isset($state['colortemp'])) {
                    $state['colortemp'] = (int)($params['colortemp'] ?? $state['colortemp']);
                }
                break;

            case 'set_color':
                if (isset($state['color'])) {
                    $state['color'] = $params['color'] ?? $state['color'];
                }
                break;

            case 'set_temperature':
                if (isset($state['setpoint'])) {
                    $state['setpoint'] = (float)($params['temperature'] ?? $state['setpoint']);
                }
                break;

            case 'set_mode':
                if (isset($state['mode'])) {
                    $state['mode'] = $params['mode'] ?? $state['mode'];
                }
                break;

            case 'set_speed':
                if (isset($state['speed'])) {
                    $state['speed'] = max(0, min(5, (int)($params['speed'] ?? $state['speed'])));
                }
                if (isset($state['on'])) $state['on'] = (int)($params['speed'] ?? 0) > 0;
                break;

            case 'set_position':
                if (isset($state['position'])) {
                    $state['position'] = max(0, min(100, (int)($params['position'] ?? $state['position'])));
                    $state['direction'] = 'stopped';
                }
                break;

            case 'open':
                if (isset($state['position'])) {
                    $state['position'] = 100;
                    $state['direction'] = 'opening';
                }
                break;

            case 'close':
                if (isset($state['position'])) {
                    $state['position'] = 0;
                    $state['direction'] = 'closing';
                }
                break;

            case 'lock':
                if (isset($state['lockstate'])) $state['lockstate'] = 'locked';
                break;

            case 'unlock':
                if (isset($state['lockstate'])) $state['lockstate'] = 'unlocked';
                break;

            case 'set_volume':
                if (isset($state['volume'])) {
                    $state['volume'] = max(0, min(100, (int)($params['volume'] ?? $state['volume'])));
                }
                break;

            case 'play':
                if (isset($state['playback'])) $state['playback'] = 'playing';
                break;

            case 'pause':
                if (isset($state['playback'])) $state['playback'] = 'paused';
                break;

            case 'stop':
                if (isset($state['playback'])) $state['playback'] = 'stopped';
                if (isset($state['direction'])) $state['direction'] = 'stopped';
                break;

            case 'trigger_sensor':
                if (isset($state['occupancy'])) {
                    $state['occupancy'] = (bool)($params['occupancy'] ?? !$state['occupancy']);
                }
                if (isset($state['contact'])) {
                    $state['contact'] = (bool)($params['contact'] ?? !$state['contact']);
                }
                break;

            default:
                foreach ($params as $key => $value) {
                    if (array_key_exists($key, $state)) {
                        $state[$key] = $value;
                    }
                }
                break;
        }

        return ['success' => true, 'state' => $state, 'simulated' => true];
    }

    public function subscribe(string $matterUniqueId, int $deviceId): array
    {
        if ($this->simulationMode) {
            $this->startSimulationMonitor($matterUniqueId, $deviceId);
            return ['success' => true, 'message' => '订阅成功'];
        }

        return $this->bridgeClient->subscribe($matterUniqueId, $deviceId);
    }

    private function startSimulationMonitor(string $matterUid, int $deviceId): void
    {
        if (!isset($this->simulatedDevices[$matterUid])) {
            return;
        }

        $this->logModel->add(
            null,
            'info',
            'matter',
            "开始监控设备状态: {$matterUid}",
            ['device_id' => $deviceId]
        );
    }

    public function getPairedDevices(): array
    {
        if ($this->simulationMode) {
            return $this->simulatedDevices;
        }

        $result = $this->bridgeClient->getDeviceList();
        return $result['success'] ? ($result['data']['devices'] ?? []) : [];
    }

    public function getSimulatedDeviceState(string $matterUid): ?array
    {
        return $this->simulatedDevices[$matterUid]['state'] ?? null;
    }

    public function updateSimulatedDeviceState(string $matterUid, array $state): void
    {
        if (isset($this->simulatedDevices[$matterUid])) {
            $this->simulatedDevices[$matterUid]['state'] = array_merge(
                $this->simulatedDevices[$matterUid]['state'],
                $state
            );
        }
    }

    public function handleCallback(array $data): void
    {
        $matterUid = $data['unique_id'] ?? '';
        $newState = $data['state'] ?? [];

        if (empty($matterUid)) {
            return;
        }

        $device = $this->deviceModel->findByMatterUid($matterUid);
        if (!$device) {
            return;
        }

        $currentState = json_decode($device['state'] ?? '{}', true);
        $updatedState = array_merge($currentState, $newState);

        $this->deviceModel->updateState((int) $device['id'], $updatedState);
        $this->deviceModel->addHistory((int) $device['id'], $newState);
        $this->deviceModel->setOnline((int) $device['id'], true);

        WebSocketPush::broadcast([
            'type' => 'device_state_report',
            'device_id' => (int) $device['id'],
            'matter_unique_id' => $matterUid,
            'state' => $updatedState,
        ]);

        $this->logModel->add(
            (int) $device['family_id'],
            'info',
            'matter',
            "设备状态上报: {$device['name']}",
            $newState,
            null,
            (int) $device['id']
        );
    }

    public function simulateSensorTrigger(string $matterUid, string $sensorType): void
    {
        if (!isset($this->simulatedDevices[$matterUid])) {
            return;
        }

        $device = &$this->simulatedDevices[$matterUid];

        if ($sensorType === 'motion') {
            $device['state']['occupancy'] = true;
        } elseif ($sensorType === 'door') {
            $device['state']['contact'] = false;
        }

        $dbDevice = $this->deviceModel->findByMatterUid($matterUid);
        if ($dbDevice) {
            $this->deviceModel->updateState((int) $dbDevice['id'], $device['state']);
            $this->deviceModel->addHistory((int) $dbDevice['id'], $device['state']);
        }

        WebSocketPush::broadcast([
            'type' => 'sensor_trigger',
            'matter_unique_id' => $matterUid,
            'sensor_type' => $sensorType,
            'state' => $device['state'],
        ]);
    }

    public function getSimulationDevices(): array
    {
        return $this->simulatedDevices;
    }

    public function isSimulationMode(): bool
    {
        return $this->simulationMode;
    }

    public function setSimulationMode(bool $mode): void
    {
        $this->simulationMode = $mode;
    }
}
