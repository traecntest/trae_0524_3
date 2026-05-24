<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Alert extends Model
{
    protected string $table = 'alerts';
    protected array $fillable = ['family_id', 'device_id', 'type', 'severity', 'title', 'message', 'is_read', 'is_resolved', 'resolved_at'];

    public function getByFamily(int $familyId, bool $onlyUnread = false): array
    {
        $sql = 'SELECT a.*, d.name AS device_name FROM alerts a LEFT JOIN devices d ON a.device_id = d.id WHERE a.family_id = ?';
        if ($onlyUnread) {
            $sql .= ' AND a.is_read = false';
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function add(int $familyId, string $type, string $severity, string $title, string $message = '', ?int $deviceId = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO alerts (family_id, device_id, type, severity, title, message) VALUES (?, ?, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([$familyId, $deviceId, $type, $severity, $title, $message]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $alertId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE alerts SET is_read = true WHERE id = ?');
        return $stmt->execute([$alertId]);
    }

    public function markAllRead(int $familyId): void
    {
        $stmt = $this->pdo->prepare('UPDATE alerts SET is_read = true WHERE family_id = ? AND is_read = false');
        $stmt->execute([$familyId]);
    }

    public function resolve(int $alertId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE alerts SET is_resolved = true, resolved_at = NOW() WHERE id = ?');
        return $stmt->execute([$alertId]);
    }

    public function getUnreadCount(int $familyId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM alerts WHERE family_id = ? AND is_read = false');
        $stmt->execute([$familyId]);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(int $familyId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE severity = 'critical') AS critical, COUNT(*) FILTER (WHERE severity = 'warning') AS warning, COUNT(*) FILTER (WHERE severity = 'info') AS info, COUNT(*) FILTER (WHERE is_read = false) AS unread FROM alerts WHERE family_id = ?");
        $stmt->execute([$familyId]);
        $row = $stmt->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'critical' => (int)($row['critical'] ?? 0),
            'warning' => (int)($row['warning'] ?? 0),
            'info' => (int)($row['info'] ?? 0),
            'unread' => (int)($row['unread'] ?? 0),
        ];
    }
}
