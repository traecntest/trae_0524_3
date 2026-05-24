<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class Family extends Model
{
    protected string $table = 'families';
    protected array $fillable = ['name', 'description', 'owner_id', 'address'];

    public function getMembers(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT u.id, u.username, u.email, u.real_name, u.avatar_url, fm.role AS family_role, fm.joined_at FROM users u JOIN family_members fm ON u.id = fm.user_id WHERE fm.family_id = ? ORDER BY fm.joined_at');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $familyId, int $userId, string $role = 'member'): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO family_members (family_id, user_id, role) VALUES (?, ?, ?) ON CONFLICT DO NOTHING');
        $stmt->execute([$familyId, $userId, $role]);
    }

    public function removeMember(int $familyId, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM family_members WHERE family_id = ? AND user_id = ?');
        $stmt->execute([$familyId, $userId]);
    }

    public function isMember(int $familyId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM family_members WHERE family_id = ? AND user_id = ?');
        $stmt->execute([$familyId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getUserFamilies(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.* FROM families f JOIN family_members fm ON f.id = fm.family_id WHERE fm.user_id = ? ORDER BY f.created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getByUser(int $userId, int $familyId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT f.* FROM families f JOIN family_members fm ON f.id = fm.family_id WHERE f.id = ? AND fm.user_id = ?');
        $stmt->execute([$familyId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
