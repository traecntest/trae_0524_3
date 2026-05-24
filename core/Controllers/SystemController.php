<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Device;
use App\Models\Scene;
use App\Models\AutomationRule;
use App\Models\Log;
use App\Models\Alert;
use App\Models\Family;
use App\Services\Matter\MatterService;

class SystemController extends Controller
{
    private Device $deviceModel;
    private Scene $sceneModel;
    private AutomationRule $automationModel;
    private Log $logModel;
    private Alert $alertModel;
    private Family $familyModel;
    private MatterService $matterService;

    public function __construct()
    {
        $this->deviceModel = new Device();
        $this->sceneModel = new Scene();
        $this->automationModel = new AutomationRule();
        $this->logModel = new Log();
        $this->alertModel = new Alert();
        $this->familyModel = new Family();
        $this->matterService = new MatterService();
    }

    public function status(): never
    {
        $status = [
            'app' => config('app.name'),
            'version' => config('app.version'),
            'time' => now(),
            'matter_bridge' => $this->matterService->getBridgeStatus(),
        ];

        try {
            $pdo = \App\Database\Database::connection();
            $pdo->query('SELECT 1');
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'disconnected';
        }

        $this->success($status);
    }

    public function dashboard(): never
    {
        global $current_user;
        $families = $this->familyModel->getUserFamilies((int) $current_user['id']);

        $dashboard = [
            'families' => [],
            'total_devices' => 0,
            'online_devices' => 0,
            'total_scenes' => 0,
            'total_automations' => 0,
            'unread_alerts' => 0,
            'recent_logs' => [],
        ];

        foreach ($families as $family) {
            $familyId = (int) $family['id'];

            $deviceStats = $this->deviceModel->getStats($familyId);
            $sceneCount = count($this->sceneModel->getByFamily($familyId));
            $automationStats = $this->automationModel->getStats($familyId);
            $alertStats = $this->alertModel->getStats($familyId);
            $recentLogs = $this->logModel->getRecent($familyId, 10);

            $dashboard['families'][] = [
                'id' => $familyId,
                'name' => $family['name'],
                'devices' => $deviceStats,
                'scenes' => $sceneCount,
                'automations' => $automationStats,
                'alerts' => $alertStats,
            ];

            $dashboard['total_devices'] += $deviceStats['total'];
            $dashboard['online_devices'] += $deviceStats['online'];
            $dashboard['total_scenes'] += $sceneCount;
            $dashboard['total_automations'] += $automationStats['total'];
            $dashboard['unread_alerts'] += $alertStats['unread'];

            foreach ($recentLogs as $log) {
                $log['context'] = json_decode($log['context'] ?? '{}', true);
                $dashboard['recent_logs'][] = $log;
            }
        }

        usort($dashboard['recent_logs'], fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $dashboard['recent_logs'] = array_slice($dashboard['recent_logs'], 0, 20);

        $this->success($dashboard);
    }
}
