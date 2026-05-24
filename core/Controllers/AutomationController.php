<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AutomationRule;
use App\Models\Family;
use App\Models\Device;
use App\Models\Log;
use App\Services\Matter\MatterService;
use App\Services\WebSocket\WebSocketPush;

class AutomationController extends Controller
{
    private AutomationRule $automationModel;
    private Family $familyModel;
    private Log $logModel;
    private Device $deviceModel;
    private MatterService $matterService;

    public function __construct()
    {
        $this->automationModel = new AutomationRule();
        $this->familyModel = new Family();
        $this->logModel = new Log();
        $this->deviceModel = new Device();
        $this->matterService = new MatterService();
    }

    public function index(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $rules = $this->automationModel->getByFamily($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $rules = [];
            foreach ($families as $family) {
                $rules = array_merge($rules, $this->automationModel->getByFamily((int) $family['id']));
            }
        }

        foreach ($rules as &$rule) {
            $rule['trigger_config'] = json_decode($rule['trigger_config'] ?? '{}', true);
            $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
            $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);
        }

        $this->success($rules);
    }

    public function store(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'name' => 'required|min:1|max:128',
            'trigger_type' => 'required',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $ruleId = $this->automationModel->create([
            'family_id' => $familyId,
            'name' => trim($input['name']),
            'description' => $input['description'] ?? '',
            'is_enabled' => (bool) ($input['is_enabled'] ?? true),
            'trigger_type' => $input['trigger_type'],
            'trigger_config' => json_encode($input['trigger_config'] ?? []),
            'conditions' => json_encode($input['conditions'] ?? []),
            'actions' => json_encode($input['actions'] ?? []),
        ]);

        $this->logModel->add($familyId, 'info', 'automation', "创建自动化规则: {$input['name']}", [], (int) $current_user['id']);

        $rule = $this->automationModel->find($ruleId);
        $this->success($rule, '规则创建成功');
    }

    public function show(array $params): never
    {
        global $current_user;
        $rule = $this->automationModel->find((int) $params['id']);
        if (!$rule || !$this->familyModel->isMember((int) $rule['family_id'], (int) $current_user['id'])) {
            $this->error('规则不存在或无权限', 404, 404);
        }

        $rule['trigger_config'] = json_decode($rule['trigger_config'] ?? '{}', true);
        $rule['conditions'] = json_decode($rule['conditions'] ?? '[]', true);
        $rule['actions'] = json_decode($rule['actions'] ?? '[]', true);

        $this->success($rule);
    }

    public function update(array $params): never
    {
        global $current_user;
        $rule = $this->automationModel->find((int) $params['id']);
        if (!$rule || !$this->familyModel->isMember((int) $rule['family_id'], (int) $current_user['id'])) {
            $this->error('规则不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $data = [];
        foreach (['name', 'description', 'is_enabled', 'trigger_type'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $field === 'is_enabled' ? (bool) $input[$field] : $input[$field];
            }
        }
        if (isset($input['trigger_config'])) $data['trigger_config'] = json_encode($input['trigger_config']);
        if (isset($input['conditions'])) $data['conditions'] = json_encode($input['conditions']);
        if (isset($input['actions'])) $data['actions'] = json_encode($input['actions']);

        if (!empty($data)) {
            $this->automationModel->update((int) $params['id'], $data);
        }

        $this->logModel->add((int) $rule['family_id'], 'info', 'automation', "更新自动化规则: {$rule['name']}", [], (int) $current_user['id']);
        $this->success(null, '更新成功');
    }

    public function destroy(array $params): never
    {
        global $current_user;
        $rule = $this->automationModel->find((int) $params['id']);
        if (!$rule || !$this->familyModel->isMember((int) $rule['family_id'], (int) $current_user['id'])) {
            $this->error('规则不存在或无权限', 404, 404);
        }

        $this->automationModel->delete((int) $params['id']);
        $this->logModel->add((int) $rule['family_id'], 'info', 'automation', "删除自动化规则: {$rule['name']}", [], (int) $current_user['id']);
        $this->success(null, '规则已删除');
    }

    public function trigger(array $params): never
    {
        global $current_user;
        $rule = $this->automationModel->find((int) $params['id']);
        if (!$rule || !$this->familyModel->isMember((int) $rule['family_id'], (int) $current_user['id'])) {
            $this->error('规则不存在或无权限', 404, 404);
        }

        $actions = json_decode($rule['actions'] ?? '[]', true);
        $results = [];

        foreach ($actions as $action) {
            if (!isset($action['device_id'])) continue;

            $device = $this->deviceModel->find((int) $action['device_id']);
            if (!$device) continue;

            $actionType = $action['action_type'] ?? 'toggle';
            $actionParams = $action['params'] ?? [];

            $result = $this->matterService->sendCommand($device, $actionType, $actionParams);
            $results[] = [
                'device' => $device['name'],
                'action' => $actionType,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $currentState = json_decode($device['state'] ?? '{}', true);
                $newState = array_merge($currentState, $actionParams);
                $this->deviceModel->updateState((int) $device['id'], $newState);
                $this->deviceModel->addHistory((int) $device['id'], ['automation_id' => (int) $params['id'], 'action' => $actionType, 'params' => $actionParams]);

                WebSocketPush::broadcast([
                    'type' => 'device_state_change',
                    'device_id' => (int) $device['id'],
                    'action' => $actionType,
                    'params' => $actionParams,
                    'state' => $newState,
                ]);
            }

            if (isset($action['delay_ms']) && $action['delay_ms'] > 0) {
                usleep((int) $action['delay_ms'] * 1000);
            }
        }

        $this->automationModel->markTriggered((int) $params['id']);

        $this->logModel->add(
            (int) $rule['family_id'],
            'info',
            'automation',
            "手动触发自动化规则: {$rule['name']}",
            ['results' => $results],
            (int) $current_user['id']
        );

        WebSocketPush::broadcast([
            'type' => 'automation_triggered',
            'rule_id' => (int) $params['id'],
            'rule_name' => $rule['name'],
            'results' => $results,
        ]);

        $this->success(['results' => $results], '规则已触发');
    }

    public function toggle(array $params): never
    {
        global $current_user;
        $rule = $this->automationModel->find((int) $params['id']);
        if (!$rule || !$this->familyModel->isMember((int) $rule['family_id'], (int) $current_user['id'])) {
            $this->error('规则不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $enabled = (bool) ($input['enabled'] ?? !$rule['is_enabled']);
        $this->automationModel->toggle((int) $params['id'], $enabled);

        $this->logModel->add(
            (int) $rule['family_id'],
            'info',
            'automation',
            ($enabled ? '启用' : '禁用') . "自动化规则: {$rule['name']}",
            [],
            (int) $current_user['id']
        );

        $this->success(['is_enabled' => $enabled], $enabled ? '规则已启用' : '规则已禁用');
    }
}
