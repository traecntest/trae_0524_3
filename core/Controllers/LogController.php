<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Log;
use App\Models\Family;

class LogController extends Controller
{
    private Log $logModel;
    private Family $familyModel;

    public function __construct()
    {
        $this->logModel = new Log();
        $this->familyModel = new Family();
    }

    public function index(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);
        $category = $query['category'] ?? '';
        $limit = (int) ($query['limit'] ?? 100);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            if ($category) {
                $logs = $this->logModel->getByCategory($familyId, $category, min($limit, 500));
            } else {
                $logs = $this->logModel->getByFamily($familyId, min($limit, 500));
            }
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $logs = [];
            foreach ($families as $family) {
                $familyLogs = $this->logModel->getByFamily((int) $family['id'], min($limit, 200));
                $logs = array_merge($logs, $familyLogs);
            }
            usort($logs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
            $logs = array_slice($logs, 0, min($limit, 500));
        }

        foreach ($logs as &$log) {
            $log['context'] = json_decode($log['context'] ?? '{}', true);
        }

        $this->success($logs);
    }

    public function stats(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $stats = $this->logModel->getStats($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $stats = ['total' => 0, 'errors' => 0, 'warnings' => 0, 'infos' => 0];
            foreach ($families as $family) {
                $familyStats = $this->logModel->getStats((int) $family['id']);
                foreach ($stats as $key => $value) {
                    $stats[$key] += $familyStats[$key] ?? 0;
                }
            }
        }

        $this->success($stats);
    }
}
