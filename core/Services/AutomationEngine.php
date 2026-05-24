<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Device;
use App\Models\Log;
use App\Models\Alert;
use App\Services\Matter\MatterService;
use App\Services\WebSocket\WebSocketPush;

class AutomationEngine
{
    private AutomationRule $ruleModel;
    private Device $deviceModel;
    private Log $logModel;
    private Alert $alertModel;
    private MatterService $matterService;

    public function __construct()
    {
        $this->ruleModel = new AutomationRule();
        $this->deviceModel = new Device();
        $this->logModel = new Log();
        $this->alertModel = new Alert();
        $this->matterService = new MatterService();
    }

    public function evaluateAndRun(): void
    {
        $rules = $this->ruleModel->getAllEnabled();

        foreach ($rules as $rule) {
            $triggerType = $rule['trigger_type'];
            $triggerConfig = json_decode($rule['trigger_config'] ?? '{}', true);
            $conditions = json_decode($rule['conditions'] ?? '[]', true);
            $actions = json_decode($rule['actions'] ?? '[]', true);

            $triggered = $this->evaluateTrigger($triggerType, $triggerConfig);

            if (!$triggered) {
                continue;
            }

            if (!$this->evaluateConditions($conditions, (int) $rule['family_id'])) {
                continue;
            }

            $this->executeActions($actions, (int) $rule['family_id'], (int) $rule['id'], $rule['name']);
            $this->ruleModel->markTriggered((int) $rule['id']);
        }
    }

    private function evaluateTrigger(string $type, array $config): bool
    {
        $now = new \DateTime();

        return match ($type) {
            'time' => $this->evaluateTimeTrigger($config, $now),
            'schedule' => $this->evaluateScheduleTrigger($config, $now),
            'device_state' => $this->evaluateDeviceStateTrigger($config),
            'sensor' => $this->evaluateSensorTrigger($config),
            'manual' => false,
            default => false,
        };
    }

    private function evaluateTimeTrigger(array $config, \DateTime $now): bool
    {
        $targetTime = $config['time'] ?? '';
        if (empty($targetTime)) {
            return false;
        }

        $tolerance = (int)($config['tolerance'] ?? 60);
        $currentTime = $now->format('H:i');

        $target = \DateTime::createFromFormat('H:i', $targetTime);
        $current = \DateTime::createFromFormat('H:i', $currentTime);

        if (!$target || !$current) {
            return false;
        }

        $diff = abs($current->getTimestamp() - $target->getTimestamp());
        return $diff <= $tolerance;
    }

    private function evaluateScheduleTrigger(array $config, \DateTime $now): bool
    {
        $days = $config['days'] ?? [];
        $times = $config['times'] ?? [];

        if (empty($days) || empty($times)) {
            return false;
        }

        $dayOfWeek = (int) $now->format('N');
        if (!in_array($dayOfWeek, $days)) {
            return false;
        }

        $currentTime = $now->format('H:i');
        $tolerance = (int)($config['tolerance'] ?? 60);

        foreach ($times as $time) {
            $target = \DateTime::createFromFormat('H:i', $time);
            $current = \DateTime::createFromFormat('H:i', $currentTime);

            if ($target && $current) {
                $diff = abs($current->getTimestamp() - $target->getTimestamp());
                if ($diff <= $tolerance) {
                    return true;
                }
            }
        }

        return false;
    }

    private function evaluateDeviceStateTrigger(array $config): bool
    {
        $deviceId = (int)($config['device_id'] ?? 0);
        $field = $config['field'] ?? '';
        $operator = $config['operator'] ?? '==';
        $value = $config['value'] ?? null;

        if ($deviceId === 0 || empty($field)) {
            return false;
        }

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            return false;
        }

        $state = json_decode($device['state'] ?? '{}', true);
        if (!isset($state[$field])) {
            return false;
        }

        $actualValue = $state[$field];

        return match ($operator) {
            '==', 'eq' => $actualValue == $value,
            '!=', 'ne' => $actualValue != $value,
            '>', 'gt' => $actualValue > $value,
            '>=', 'gte' => $actualValue >= $value,
            '<', 'lt' => $actualValue < $value,
            '<=', 'lte' => $actualValue <= $value,
            'contains' => is_string($actualValue) && str_contains($actualValue, (string)$value),
            default => false,
        };
    }

    private function evaluateSensorTrigger(array $config): bool
    {
        return $this->evaluateDeviceStateTrigger($config);
    }

    private function evaluateConditions(array $conditions, int $familyId): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $logic = $conditions['logic'] ?? 'and';
        $rules = $conditions['rules'] ?? $conditions;

        $results = [];
        foreach ($rules as $condition) {
            $results[] = $this->evaluateSingleCondition($condition);
        }

        if ($logic === 'or') {
            return in_array(true, $results, true);
        }

        return !in_array(false, $results, true);
    }

    private function evaluateSingleCondition(array $condition): bool
    {
        $type = $condition['type'] ?? 'device_state';

        return match ($type) {
            'device_state' => $this->evaluateDeviceStateCondition($condition),
            'time_range' => $this->evaluateTimeRangeCondition($condition),
            'day_of_week' => $this->evaluateDayOfWeekCondition($condition),
            'status' => true,
            default => true,
        };
    }

    private function evaluateDeviceStateCondition(array $condition): bool
    {
        $deviceId = (int)($condition['device_id'] ?? 0);
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;

        if ($deviceId === 0 || empty($field)) {
            return true;
        }

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            return false;
        }

        $state = json_decode($device['state'] ?? '{}', true);
        if (!isset($state[$field])) {
            return false;
        }

        $actualValue = $state[$field];

        return match ($operator) {
            '==', 'eq' => $actualValue == $value,
            '!=', 'ne' => $actualValue != $value,
            '>', 'gt' => $actualValue > $value,
            '>=', 'gte' => $actualValue >= $value,
            '<', 'lt' => $actualValue < $value,
            '<=', 'lte' => $actualValue <= $value,
            default => false,
        };
    }

    private function evaluateTimeRangeCondition(array $condition): bool
    {
        $now = new \DateTime();
        $currentMinutes = (int) $now->format('H') * 60 + (int) $now->format('i');

        $startTime = $condition['start'] ?? '00:00';
        $endTime = $condition['end'] ?? '23:59';

        $startParts = explode(':', $startTime);
        $endParts = explode(':', $endTime);

        $startMinutes = (int)($startParts[0] ?? 0) * 60 + (int)($startParts[1] ?? 0);
        $endMinutes = (int)($endParts[0] ?? 0) * 60 + (int)($endParts[1] ?? 0);

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }

    private function evaluateDayOfWeekCondition(array $condition): bool
    {
        $now = new \DateTime();
        $dayOfWeek = (int) $now->format('N');
        $allowedDays = $condition['days'] ?? [];

        return in_array($dayOfWeek, $allowedDays);
    }

    private function executeActions(array $actions, int $familyId, int $ruleId, string $ruleName): void
    {
        $results = [];

        foreach ($actions as $action) {
            $actionType = $action['type'] ?? 'device';
            $result = $this->executeSingleAction($action, $familyId);
            $results[] = $result;

            if (isset($action['delay_ms']) && $action['delay_ms'] > 0) {
                usleep((int) $action['delay_ms'] * 1000);
            }
        }

        $this->logModel->add(
            $familyId,
            'info',
            'automation',
            "自动化规则触发: {$ruleName}",
            ['rule_id' => $ruleId, 'results' => $results]
        );

        WebSocketPush::broadcast([
            'type' => 'automation_triggered',
            'rule_id' => $ruleId,
            'rule_name' => $ruleName,
            'results' => $results,
        ]);
    }

    private function executeSingleAction(array $action, int $familyId): array
    {
        $actionType = $action['type'] ?? 'device';

        return match ($actionType) {
            'device' => $this->executeDeviceAction($action),
            'scene' => $this->executeSceneAction($action, $familyId),
            'notification' => $this->executeNotificationAction($action, $familyId),
            'alert' => $this->executeAlertAction($action, $familyId),
            default => ['success' => false, 'error' => "未知动作类型: {$actionType}"],
        };
    }

    private function executeDeviceAction(array $action): array
    {
        $deviceId = (int)($action['device_id'] ?? 0);
        $command = $action['command'] ?? 'toggle';
        $params = $action['params'] ?? [];

        if ($deviceId === 0) {
            return ['success' => false, 'error' => '缺少设备ID'];
        }

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => '设备不存在'];
        }

        $result = $this->matterService->sendCommand($device, $command, $params);

        if ($result['success']) {
            $currentState = json_decode($device['state'] ?? '{}', true);
            $newState = array_merge($currentState, $params);
            $this->deviceModel->updateState($deviceId, $newState);
            $this->deviceModel->addHistory($deviceId, ['automation' => true, 'command' => $command, 'params' => $params]);

            WebSocketPush::broadcast([
                'type' => 'device_state_change',
                'device_id' => $deviceId,
                'action' => $command,
                'params' => $params,
                'state' => $newState,
            ]);
        }

        return [
            'device' => $device['name'],
            'command' => $command,
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
        ];
    }

    private function executeSceneAction(array $action, int $familyId): array
    {
        $sceneId = (int)($action['scene_id'] ?? 0);
        if ($sceneId === 0) {
            return ['success' => false, 'error' => '缺少场景ID'];
        }

        $sceneModel = new \App\Models\Scene();
        $scene = $sceneModel->find($sceneId);
        if (!$scene) {
            return ['success' => false, 'error' => '场景不存在'];
        }

        $sceneActions = $sceneModel->getActions($sceneId);
        $results = [];

        foreach ($sceneActions as $sceneAction) {
            $device = $this->deviceModel->find((int) $sceneAction['device_id']);
            if (!$device) continue;

            $params = json_decode($sceneAction['action_params'] ?? '{}', true);
            $result = $this->matterService->sendCommand($device, $sceneAction['action_type'], $params);
            $results[] = [
                'device' => $device['name'],
                'success' => $result['success'],
            ];

            if ($result['success']) {
                $currentState = json_decode($device['state'] ?? '{}', true);
                $newState = array_merge($currentState, $params);
                $this->deviceModel->updateState((int) $device['id'], $newState);
            }
        }

        return ['success' => true, 'scene' => $scene['name'], 'actions' => $results];
    }

    private function executeNotificationAction(array $action, int $familyId): array
    {
        $title = $action['title'] ?? '自动化通知';
        $message = $action['message'] ?? '';

        $this->logModel->add($familyId, 'info', 'notification', $title, ['message' => $message]);

        WebSocketPush::broadcast([
            'type' => 'notification',
            'title' => $title,
            'message' => $message,
        ]);

        return ['success' => true, 'notification' => $title];
    }

    private function executeAlertAction(array $action, int $familyId): array
    {
        $type = $action['alert_type'] ?? 'automation';
        $severity = $action['severity'] ?? 'info';
        $title = $action['title'] ?? '自动化告警';
        $message = $action['message'] ?? '';

        $alertId = $this->alertModel->add($familyId, $type, $severity, $title, $message);

        WebSocketPush::broadcast([
            'type' => 'alert',
            'alert_id' => $alertId,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
        ]);

        return ['success' => true, 'alert_id' => $alertId];
    }

    public function run(): void
    {
        $interval = (int) config('automation.check_interval', 5);

        while (true) {
            $startTime = microtime(true);

            try {
                $this->evaluateAndRun();
            } catch (\Exception $e) {
                logger('error', '自动化引擎异常: ' . $e->getMessage());
            }

            $elapsed = microtime(true) - $startTime;
            $sleepTime = max(0, $interval - $elapsed);
            sleep((int) $sleepTime);
        }
    }
}
