<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Scene;
use App\Models\Family;
use App\Models\Device;
use App\Models\Log;
use App\Services\Matter\MatterService;
use App\Services\WebSocket\WebSocketPush;

class SceneController extends Controller
{
    private Scene $sceneModel;
    private Family $familyModel;
    private Log $logModel;
    private Device $deviceModel;
    private MatterService $matterService;

    public function __construct()
    {
        $this->sceneModel = new Scene();
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
            $scenes = $this->sceneModel->getByFamily($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $scenes = [];
            foreach ($families as $family) {
                $scenes = array_merge($scenes, $this->sceneModel->getByFamily((int) $family['id']));
            }
        }

        $this->success($scenes);
    }

    public function store(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'name' => 'required|min:1|max:128',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $sceneId = $this->sceneModel->create([
            'family_id' => $familyId,
            'name' => trim($input['name']),
            'icon' => $input['icon'] ?? 'scene',
            'color' => $input['color'] ?? '#4A90D9',
            'description' => $input['description'] ?? '',
            'is_favorite' => (bool) ($input['is_favorite'] ?? false),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);

        $this->logModel->add($familyId, 'info', 'scene', "创建场景: {$input['name']}", [], (int) $current_user['id']);
        $scene = $this->sceneModel->find($sceneId);
        $this->success($scene, '场景创建成功');
    }

    public function show(array $params): never
    {
        global $current_user;
        $scene = $this->sceneModel->find((int) $params['id']);
        if (!$scene || !$this->familyModel->isMember((int) $scene['family_id'], (int) $current_user['id'])) {
            $this->error('场景不存在或无权限', 404, 404);
        }

        $scene['actions'] = $this->sceneModel->getActions((int) $params['id']);
        foreach ($scene['actions'] as &$action) {
            $action['action_params'] = json_decode($action['action_params'] ?? '{}', true);
        }

        $this->success($scene);
    }

    public function update(array $params): never
    {
        global $current_user;
        $scene = $this->sceneModel->find((int) $params['id']);
        if (!$scene || !$this->familyModel->isMember((int) $scene['family_id'], (int) $current_user['id'])) {
            $this->error('场景不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $data = [];
        foreach (['name', 'icon', 'color', 'description', 'is_favorite', 'sort_order'] as $field) {
            if (isset($input[$field])) {
                $data[$field] = $field === 'is_favorite' ? (bool) $input[$field] : $input[$field];
            }
        }

        if (!empty($data)) {
            $this->sceneModel->update((int) $params['id'], $data);
        }

        $this->logModel->add((int) $scene['family_id'], 'info', 'scene', "更新场景: {$scene['name']}", [], (int) $current_user['id']);
        $this->success(null, '更新成功');
    }

    public function destroy(array $params): never
    {
        global $current_user;
        $scene = $this->sceneModel->find((int) $params['id']);
        if (!$scene || !$this->familyModel->isMember((int) $scene['family_id'], (int) $current_user['id'])) {
            $this->error('场景不存在或无权限', 404, 404);
        }

        $this->sceneModel->delete((int) $params['id']);
        $this->logModel->add((int) $scene['family_id'], 'info', 'scene', "删除场景: {$scene['name']}", [], (int) $current_user['id']);
        $this->success(null, '场景已删除');
    }

    public function execute(array $params): never
    {
        global $current_user;
        $scene = $this->sceneModel->find((int) $params['id']);
        if (!$scene || !$this->familyModel->isMember((int) $scene['family_id'], (int) $current_user['id'])) {
            $this->error('场景不存在或无权限', 404, 404);
        }

        $actions = $this->sceneModel->getActions((int) $params['id']);
        $results = [];
        $totalDelay = 0;

        foreach ($actions as $action) {
            $actionParams = json_decode($action['action_params'] ?? '{}', true);
            $delay = (int) $action['delay_ms'];
            $device = $this->deviceModel->find((int) $action['device_id']);

            if (!$device) {
                $results[] = ['device' => $action['device_name'], 'action' => $action['action_type'], 'success' => false, 'error' => '设备不存在'];
                continue;
            }

            $totalDelay += $delay;
            if ($delay > 0) {
                usleep($delay * 1000);
            }

            $result = $this->matterService->sendCommand($device, $action['action_type'], $actionParams);
            $results[] = [
                'device' => $action['device_name'],
                'action' => $action['action_type'],
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
            ];

            if ($result['success']) {
                $currentState = json_decode($device['state'] ?? '{}', true);
                $newState = array_merge($currentState, $actionParams);
                $this->deviceModel->updateState((int) $device['id'], $newState);
                $this->deviceModel->addHistory((int) $device['id'], ['scene_id' => (int) $params['id'], 'action' => $action['action_type'], 'params' => $actionParams]);

                WebSocketPush::broadcast([
                    'type' => 'device_state_change',
                    'device_id' => (int) $device['id'],
                    'action' => $action['action_type'],
                    'params' => $actionParams,
                    'state' => $newState,
                ]);
            }
        }

        $this->logModel->add(
            (int) $scene['family_id'],
            'info',
            'scene',
            "执行场景: {$scene['name']}",
            ['actions' => $results],
            (int) $current_user['id']
        );

        WebSocketPush::broadcast([
            'type' => 'scene_executed',
            'scene_id' => (int) $params['id'],
            'scene_name' => $scene['name'],
            'results' => $results,
        ]);

        $this->success([
            'scene' => $scene['name'],
            'results' => $results,
            'total_delay' => $totalDelay,
        ], '场景执行完成');
    }

    public function addAction(array $params): never
    {
        global $current_user;
        $scene = $this->sceneModel->find((int) $params['id']);
        if (!$scene || !$this->familyModel->isMember((int) $scene['family_id'], (int) $current_user['id'])) {
            $this->error('场景不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'device_id' => 'required|integer',
            'action_type' => 'required',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $actionId = $this->sceneModel->addAction(
            (int) $params['id'],
            (int) $input['device_id'],
            $input['action_type'],
            $input['params'] ?? [],
            (int) ($input['delay_ms'] ?? 0),
            (int) ($input['sort_order'] ?? 0)
        );

        $this->success(['action_id' => $actionId], '动作添加成功');
    }

    public function updateAction(array $params): never
    {
        global $current_user;
        $input = $this->getInput();
        $data = [];
        if (isset($input['action_type'])) $data['action_type'] = $input['action_type'];
        if (isset($input['params'])) $data['params'] = $input['params'];
        if (isset($input['delay_ms'])) $data['delay_ms'] = $input['delay_ms'];
        if (isset($input['sort_order'])) $data['sort_order'] = $input['sort_order'];

        if (empty($data)) {
            $this->error('没有需要更新的数据');
        }

        $this->sceneModel->updateAction(
            (int) $params['id'],
            $data['action_type'] ?? '',
            $data['params'] ?? [],
            (int) ($data['delay_ms'] ?? 0),
            (int) ($data['sort_order'] ?? 0)
        );

        $this->success(null, '动作更新成功');
    }

    public function deleteAction(array $params): never
    {
        $this->sceneModel->deleteAction((int) $params['id']);
        $this->success(null, '动作已删除');
    }
}
