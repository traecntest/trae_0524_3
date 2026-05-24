<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Log extends Model
{
    protected string $table = 'logs';
    protected array $fillable = ['family_id', 'user_id', 'device_id', 'level', 'category', 'message', 'context'];
    protected array $casts = ['context' => 'json'];

    public function getByFamily(int $familyId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM logs WHERE family_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$familyId, $limit]);
        return $stmt->fetchAll();
    }

    public function getByCategory(int $familyId, string $category, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM logs WHERE family_id = ? AND category = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$familyId, $category, $limit]);
        return $stmt->fetchAll();
    }

    public function add(?int $familyId, string $level, string $category, string $message, array $context = [], ?int $userId = null, ?int $deviceId = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs (family_id, user_id, device_id, level, category, message, context) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([
            $familyId,
            $userId,
            $deviceId,
            $level,
            $category,
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(int $familyId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE level = 'error') AS errors, COUNT(*) FILTER (WHERE level = 'warning') AS warnings, COUNT(*) FILTER (WHERE level = 'info') AS infos FROM logs WHERE family_id = ?");
        $stmt->execute([$familyId]);
        $row = $stmt->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'errors' => (int)($row['errors'] ?? 0),
            'warnings' => (int)($row['warnings'] ?? 0),
            'infos' => (int)($row['infos'] ?? 0),
        ];
    }

    public function getRecent(int $familyId, int $limit = 20): array
    {
        return $this->getByFamily($familyId, $limit);
    }
}
