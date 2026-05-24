<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Device extends Model
{
    protected string $table = 'devices';
    protected array $fillable = [
        'family_id', 'room_id', 'type_id', 'name', 'matter_node_id', 'matter_endpoint',
        'matter_device_type', 'matter_vendor_id', 'matter_product_id', 'matter_unique_id',
        'status', 'is_online', 'state', 'capabilities', 'config', 'last_seen_at',
    ];
    protected array $casts = ['state' => 'json', 'capabilities' => 'json', 'config' => 'json'];

    public function getByFamily(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, dt.name AS type_name, dt.code AS type_code, dt.category AS type_category, dt.icon AS type_icon, r.name AS room_name FROM devices d LEFT JOIN device_types dt ON d.type_id = dt.id LEFT JOIN rooms r ON d.room_id = r.id WHERE d.family_id = ? ORDER BY d.created_at DESC');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function getByRoom(int $roomId): array
    {
        $stmt = $this->pdo->prepare('SELECT d.*, dt.name AS type_name, dt.code AS type_code, dt.category AS type_category, dt.icon AS type_icon FROM devices d JOIN device_types dt ON d.type_id = dt.id WHERE d.room_id = ? ORDER BY d.created_at DESC');
        $stmt->execute([$roomId]);
        return $stmt->fetchAll();
    }

    public function findByMatterUid(string $matterUniqueId): ?array
    {
        return $this->findBy('matter_unique_id', $matterUniqueId);
    }

    public function updateState(int $deviceId, array $state): void
    {
        $stmt = $this->pdo->prepare('UPDATE devices SET state = ?, is_online = true, last_seen_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($state, JSON_UNESCAPED_UNICODE), $deviceId]);
    }

    public function setOnline(int $deviceId, bool $online): void
    {
        $stmt = $this->pdo->prepare('UPDATE devices SET is_online = ?, status = ?, updated_at = NOW() WHERE id = ?');
        $status = $online ? 'online' : 'offline';
        $stmt->execute([$online, $status, $deviceId]);
    }

    public function addHistory(int $deviceId, array $state): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO device_states_history (device_id, state) VALUES (?, ?)');
        $stmt->execute([$deviceId, json_encode($state, JSON_UNESCAPED_UNICODE)]);
    }

    public function getHistory(int $deviceId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM device_states_history WHERE device_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$deviceId, $limit]);
        return $stmt->fetchAll();
    }

    public function getTypes(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM device_types ORDER BY category, name');
        return $stmt->fetchAll();
    }

    public function getOnlineDevices(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devices WHERE family_id = ? AND is_online = true');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function getStats(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE is_online = true) AS online, COUNT(*) FILTER (WHERE is_online = false) AS offline FROM devices WHERE family_id = ?');
        $stmt->execute([$familyId]);
        $row = $stmt->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'online' => (int)($row['online'] ?? 0),
            'offline' => (int)($row['offline'] ?? 0),
        ];
    }
}
