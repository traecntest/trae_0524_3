<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Device;
use App\Models\Family;
use App\Models\Log;
use App\Models\Alert;
use App\Services\Matter\MatterService;
use App\Services\WebSocket\WebSocketPush;

class DeviceController extends Controller
{
    private Device $deviceModel;
    private Family $familyModel;
    private Log $logModel;
    private Alert $alertModel;
    private MatterService $matterService;

    public function __construct()
    {
        $this->deviceModel = new Device();
        $this->familyModel = new Family();
        $this->logModel = new Log();
        $this->alertModel = new Alert();
        $this->matterService = new MatterService();
    }

    public function index(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);
        $roomId = (int) ($query['room_id'] ?? 0);

        if ($roomId > 0) {
            $room = (new \App\Models\Room())->find($roomId);
            if (!$room || !$this->familyModel->isMember((int) $room['family_id'], (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $devices = $this->deviceModel->getByRoom($roomId);
        } elseif ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $devices = $this->deviceModel->getByFamily($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $devices = [];
            foreach ($families as $family) {
                $devices = array_merge($devices, $this->deviceModel->getByFamily((int) $family['id']));
            }
        }

        foreach ($devices as &$device) {
            $device['state'] = json_decode($device['state'] ?? '{}', true);
            $device['capabilities'] = json_decode($device['capabilities'] ?? '{}', true);
            $device['config'] = json_decode($device['config'] ?? '{}', true);
        }

        $this->success($devices);
    }

    public function store(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'type_id' => 'required|integer',
            'name' => 'required|min:1|max:128',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $deviceId = $this->deviceModel->create([
            'family_id' => $familyId,
            'room_id' => (int) ($input['room_id'] ?? 0) ?: null,
            'type_id' => (int) $input['type_id'],
            'name' => trim($input['name']),
            'matter_node_id' => isset($input['matter_node_id']) ? (int) $input['matter_node_id'] : null,
            'matter_endpoint' => isset($input['matter_endpoint']) ? (int) $input['matter_endpoint'] : null,
            'matter_device_type' => isset($input['matter_device_type']) ? (int) $input['matter_device_type'] : null,
            'matter_vendor_id' => isset($input['matter_vendor_id']) ? (int) $input['matter_vendor_id'] : null,
            'matter_product_id' => isset($input['matter_product_id']) ? (int) $input['matter_product_id'] : null,
            'matter_unique_id' => $input['matter_unique_id'] ?? null,
            'status' => 'offline',
            'is_online' => false,
            'state' => json_encode([]),
            'capabilities' => json_encode([]),
            'config' => json_encode([]),
        ]);

        $this->logModel->add($familyId, 'info', 'device', "注册设备: {$input['name']}", [], (int) $current_user['id'], $deviceId);
        $device = $this->deviceModel->find($deviceId);
        $this->success($device, '设备注册成功');
    }

    public function types(): never
    {
        $types = $this->deviceModel->getTypes();
        foreach ($types as &$type) {
            $type['capabilities'] = json_decode($type['capabilities'] ?? '[]', true);
        }
        $this->success($types);
    }

    public function show(array $params): never
    {
        global $current_user;
        $device = $this->deviceModel->find((int) $params['id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $device['state'] = json_decode($device['state'] ?? '{}', true);
        $device['capabilities'] = json_decode($device['capabilities'] ?? '{}', true);
        $device['config'] = json_decode($device['config'] ?? '{}', true);

        $this->success($device);
    }

    public function update(array $params): never
    {
        global $current_user;
        $device = $this->deviceModel->find((int) $params['id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $data = [];
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (isset($input['room_id'])) $data['room_id'] = (int) $input['room_id'] ?: null;
        if (isset($input['config'])) $data['config'] = json_encode($input['config']);

        if (!empty($data)) {
            $this->deviceModel->update((int) $params['id'], $data);
        }

        $this->logModel->add((int) $device['family_id'], 'info', 'device', "更新设备: {$device['name']}", [], (int) $current_user['id'], (int) $params['id']);
        $this->success(null, '更新成功');
    }

    public function destroy(array $params): never
    {
        global $current_user;
        $device = $this->deviceModel->find((int) $params['id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        if ($device['matter_unique_id']) {
            $this->matterService->unpair($device['matter_unique_id']);
        }

        $this->deviceModel->delete((int) $params['id']);
        $this->logModel->add((int) $device['family_id'], 'info', 'device', "删除设备: {$device['name']}", [], (int) $current_user['id']);
        $this->success(null, '设备已删除');
    }

    public function control(array $params): never
    {
        global $current_user;
        $device = $this->deviceModel->find((int) $params['id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $action = $input['action'] ?? '';
        $actionParams = $input['params'] ?? [];

        if (empty($action)) {
            $this->error('缺少操作类型', 422, 422);
        }

        $result = $this->matterService->sendCommand($device, $action, $actionParams);

        if ($result['success']) {
            $currentState = json_decode($device['state'] ?? '{}', true);
            $newState = array_merge($currentState, $actionParams);
            $this->deviceModel->updateState((int) $params['id'], $newState);
            $this->deviceModel->addHistory((int) $params['id'], ['action' => $action, 'params' => $actionParams]);

            $this->logModel->add(
                (int) $device['family_id'],
                'info',
                'device',
                "控制设备: {$device['name']} - {$action}",
                $actionParams,
                (int) $current_user['id'],
                (int) $params['id']
            );

            WebSocketPush::broadcast([
                'type' => 'device_state_change',
                'device_id' => (int) $params['id'],
                'action' => $action,
                'params' => $actionParams,
                'state' => $newState,
            ]);

            $this->success(['state' => $newState], '控制指令已发送');
        }

        $this->alertModel->add(
            (int) $device['family_id'],
            'device_error',
            'warning',
            "设备控制失败: {$device['name']}",
            $result['error'] ?? '未知错误',
            (int) $params['id']
        );

        $this->logModel->add(
            (int) $device['family_id'],
            'error',
            'device',
            "设备控制失败: {$device['name']} - {$action}",
            ['error' => $result['error'] ?? 'unknown'],
            (int) $current_user['id'],
            (int) $params['id']
        );

        $this->error('设备控制失败: ' . ($result['error'] ?? '未知错误'));
    }

    public function history(array $params): never
    {
        global $current_user;
        $device = $this->deviceModel->find((int) $params['id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $query = $this->getQueryParams();
        $limit = (int) ($query['limit'] ?? 50);
        $history = $this->deviceModel->getHistory((int) $params['id'], min($limit, 200));

        foreach ($history as &$item) {
            $item['state'] = json_decode($item['state'] ?? '{}', true);
        }

        $this->success($history);
    }

    public function discover(): never
    {
        global $current_user;
        $input = $this->getInput();
        $familyId = (int) ($input['family_id'] ?? 0);

        if ($familyId > 0 && !$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $this->logModel->add($familyId ?: null, 'info', 'matter', '开始设备发现', [], (int) $current_user['id']);

        $discoveredDevices = $this->matterService->discoverDevices();

        $this->logModel->add($familyId ?: null, 'info', 'matter', '设备发现完成', ['count' => count($discoveredDevices)], (int) $current_user['id']);

        $this->success([
            'devices' => $discoveredDevices,
            'count' => count($discoveredDevices),
        ]);
    }

    public function commission(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'matter_unique_id' => 'required',
            'name' => 'required',
            'type_id' => 'required|integer',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $result = $this->matterService->commission($input['matter_unique_id']);

        if ($result['success']) {
            $existing = $this->deviceModel->findByMatterUid($input['matter_unique_id']);
            if ($existing) {
                $deviceId = (int) $existing['id'];
                $this->deviceModel->update($deviceId, [
                    'name' => trim($input['name']),
                    'type_id' => (int) $input['type_id'],
                    'room_id' => (int) ($input['room_id'] ?? 0) ?: null,
                    'is_online' => true,
                    'status' => 'online',
                ]);
            } else {
                $deviceId = $this->deviceModel->create([
                    'family_id' => $familyId,
                    'room_id' => (int) ($input['room_id'] ?? 0) ?: null,
                    'type_id' => (int) $input['type_id'],
                    'name' => trim($input['name']),
                    'matter_unique_id' => $input['matter_unique_id'],
                    'matter_node_id' => $result['node_id'] ?? null,
                    'matter_endpoint' => $result['endpoint'] ?? null,
                    'matter_device_type' => $result['device_type'] ?? null,
                    'matter_vendor_id' => $result['vendor_id'] ?? null,
                    'matter_product_id' => $result['product_id'] ?? null,
                    'is_online' => true,
                    'status' => 'online',
                    'state' => json_encode($result['state'] ?? []),
                    'capabilities' => json_encode($result['capabilities'] ?? []),
                    'config' => json_encode([]),
                ]);
            }

            $this->matterService->subscribe($input['matter_unique_id'], $deviceId);

            $this->logModel->add($familyId, 'info', 'matter', "设备入网成功: {$input['name']}", [], (int) $current_user['id'], $deviceId);

            $device = $this->deviceModel->find($deviceId);
            $this->success($device, '设备入网成功');
        }

        $this->logModel->add($familyId, 'error', 'matter', "设备入网失败: {$input['name']}", ['error' => $result['error'] ?? 'unknown'], (int) $current_user['id']);
        $this->error('设备入网失败: ' . ($result['error'] ?? '未知错误'));
    }
}
