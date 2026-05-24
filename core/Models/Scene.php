<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Scene extends Model
{
    protected string $table = 'scenes';
    protected array $fillable = ['family_id', 'name', 'icon', 'color', 'description', 'is_favorite', 'sort_order'];

    public function getByFamily(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scenes WHERE family_id = ? ORDER BY is_favorite DESC, sort_order, id');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function getActions(int $sceneId): array
    {
        $stmt = $this->pdo->prepare('SELECT sa.*, d.name AS device_name, dt.name AS type_name FROM scene_actions sa JOIN devices d ON sa.device_id = d.id LEFT JOIN device_types dt ON d.type_id = dt.id WHERE sa.scene_id = ? ORDER BY sa.sort_order, sa.id');
        $stmt->execute([$sceneId]);
        return $stmt->fetchAll();
    }

    public function addAction(int $sceneId, int $deviceId, string $actionType, array $params, int $delayMs = 0, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO scene_actions (scene_id, device_id, action_type, action_params, delay_ms, sort_order) VALUES (?, ?, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([$sceneId, $deviceId, $actionType, json_encode($params, JSON_UNESCAPED_UNICODE), $delayMs, $sortOrder]);
        return (int) $stmt->fetchColumn();
    }

    public function updateAction(int $actionId, string $actionType, array $params, int $delayMs = 0, int $sortOrder = 0): bool
    {
        $stmt = $this->pdo->prepare('UPDATE scene_actions SET action_type = ?, action_params = ?, delay_ms = ?, sort_order = ? WHERE id = ?');
        return $stmt->execute([$actionType, json_encode($params, JSON_UNESCAPED_UNICODE), $delayMs, $sortOrder, $actionId]);
    }

    public function deleteAction(int $actionId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM scene_actions WHERE id = ?');
        return $stmt->execute([$actionId]);
    }

    public function getFavoriteScenes(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scenes WHERE family_id = ? AND is_favorite = true ORDER BY sort_order, id');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }
}
