<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Device;
use App\Models\Family;
use App\Models\Log;
use App\Models\Alert;
use App\Services\Matter\MatterService;

class MatterController extends Controller
{
    private MatterService $matterService;
    private Device $deviceModel;
    private Family $familyModel;
    private Log $logModel;
    private Alert $alertModel;

    public function __construct()
    {
        $this->matterService = new MatterService();
        $this->deviceModel = new Device();
        $this->familyModel = new Family();
        $this->logModel = new Log();
        $this->alertModel = new Alert();
    }

    public function devices(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);

        if ($familyId > 0 && !$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $devices = $this->matterService->getPairedDevices();
        $this->success($devices);
    }

    public function pair(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'matter_unique_id' => 'required',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $result = $this->matterService->pairDevice($input['matter_unique_id']);

        if ($result['success']) {
            $this->logModel->add($familyId, 'info', 'matter', "Matter设备配对成功: {$input['matter_unique_id']}", $result, (int) $current_user['id']);
            $this->success($result, '设备配对成功');
        }

        $this->logModel->add($familyId, 'error', 'matter', "Matter设备配对失败: {$input['matter_unique_id']}", $result, (int) $current_user['id']);
        $this->error('设备配对失败: ' . ($result['error'] ?? '未知错误'));
    }

    public function unpair(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, ['matter_unique_id' => 'required']);
        if (!empty($errors)) {
            $this->error('缺少设备唯一标识', 422, 422);
        }

        $result = $this->matterService->unpair($input['matter_unique_id']);

        $this->logModel->add(null, 'info', 'matter', "Matter设备解除配对: {$input['matter_unique_id']}", $result, (int) $current_user['id']);
        $this->success($result, '设备已解除配对');
    }

    public function subscribe(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'matter_unique_id' => 'required',
            'device_id' => 'required|integer',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $device = $this->deviceModel->find((int) $input['device_id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $result = $this->matterService->subscribe($input['matter_unique_id'], (int) $input['device_id']);
        $this->success($result, '订阅成功');
    }

    public function command(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'matter_unique_id' => 'required',
            'command' => 'required',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $device = $this->deviceModel->findByMatterUid($input['matter_unique_id']);
        if (!$device || !$this->familyModel->isMember((int) $device['family_id'], (int) $current_user['id'])) {
            $this->error('设备不存在或无权限', 404, 404);
        }

        $result = $this->matterService->sendCommand($device, $input['command'], $input['params'] ?? []);

        if ($result['success']) {
            $currentState = json_decode($device['state'] ?? '{}', true);
            $newState = array_merge($currentState, $input['params'] ?? []);
            $this->deviceModel->updateState((int) $device['id'], $newState);
        }

        $this->logModel->add(
            (int) $device['family_id'],
            $result['success'] ? 'info' : 'error',
            'matter',
            "Matter指令: {$device['name']} -> {$input['command']}",
            $result,
            (int) $current_user['id'],
            (int) $device['id']
        );

        if ($result['success']) {
            $this->success($result, '指令发送成功');
        }

        $this->error('指令发送失败: ' . ($result['error'] ?? '未知错误'));
    }
}
