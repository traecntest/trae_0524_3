<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Alert;
use App\Models\Family;

class AlertController extends Controller
{
    private Alert $alertModel;
    private Family $familyModel;

    public function __construct()
    {
        $this->alertModel = new Alert();
        $this->familyModel = new Family();
    }

    public function index(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);
        $onlyUnread = (bool) ($query['unread'] ?? false);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $alerts = $this->alertModel->getByFamily($familyId, $onlyUnread);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $alerts = [];
            foreach ($families as $family) {
                $familyAlerts = $this->alertModel->getByFamily((int) $family['id'], $onlyUnread);
                $alerts = array_merge($alerts, $familyAlerts);
            }
            usort($alerts, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        }

        $this->success($alerts);
    }

    public function markRead(array $params): never
    {
        global $current_user;
        $alert = $this->alertModel->find((int) $params['id']);
        if (!$alert || !$this->familyModel->isMember((int) $alert['family_id'], (int) $current_user['id'])) {
            $this->error('告警不存在或无权限', 404, 404);
        }

        $this->alertModel->markRead((int) $params['id']);
        $this->success(null, '已标记为已读');
    }

    public function markAllRead(): never
    {
        global $current_user;
        $input = $this->getInput();
        $familyId = (int) ($input['family_id'] ?? 0);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $this->alertModel->markAllRead($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            foreach ($families as $family) {
                $this->alertModel->markAllRead((int) $family['id']);
            }
        }

        $this->success(null, '所有告警已标记为已读');
    }

    public function resolve(array $params): never
    {
        global $current_user;
        $alert = $this->alertModel->find((int) $params['id']);
        if (!$alert || !$this->familyModel->isMember((int) $alert['family_id'], (int) $current_user['id'])) {
            $this->error('告警不存在或无权限', 404, 404);
        }

        $this->alertModel->resolve((int) $params['id']);
        $this->success(null, '告警已解决');
    }
}
