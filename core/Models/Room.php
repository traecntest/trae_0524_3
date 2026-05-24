<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Room extends Model
{
    protected string $table = 'rooms';
    protected array $fillable = ['family_id', 'name', 'type', 'icon', 'sort_order'];

    public function getByFamily(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rooms WHERE family_id = ? ORDER BY sort_order, id');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function withDeviceCount(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, COUNT(d.id) AS device_count FROM rooms r LEFT JOIN devices d ON r.id = d.room_id WHERE r.family_id = ? GROUP BY r.id ORDER BY r.sort_order, r.id');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }
}
