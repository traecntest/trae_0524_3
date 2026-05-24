<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class AutomationRule extends Model
{
    protected string $table = 'automation_rules';
    protected array $fillable = ['family_id', 'name', 'description', 'is_enabled', 'trigger_type', 'trigger_config', 'conditions', 'actions'];
    protected array $casts = ['trigger_config' => 'json', 'conditions' => 'json', 'actions' => 'json'];

    public function getByFamily(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_rules WHERE family_id = ? ORDER BY created_at DESC');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function getEnabledRules(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_rules WHERE family_id = ? AND is_enabled = true');
        $stmt->execute([$familyId]);
        return $stmt->fetchAll();
    }

    public function getAllEnabled(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM automation_rules WHERE is_enabled = true');
        return $stmt->fetchAll();
    }

    public function toggle(int $ruleId, bool $enabled): bool
    {
        $stmt = $this->pdo->prepare('UPDATE automation_rules SET is_enabled = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$enabled, $ruleId]);
    }

    public function markTriggered(int $ruleId): void
    {
        $stmt = $this->pdo->prepare('UPDATE automation_rules SET last_triggered_at = NOW(), trigger_count = trigger_count + 1 WHERE id = ?');
        $stmt->execute([$ruleId]);
    }

    public function getByTriggerType(string $triggerType): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM automation_rules WHERE trigger_type = ? AND is_enabled = true');
        $stmt->execute([$triggerType]);
        return $stmt->fetchAll();
    }

    public function getStats(int $familyId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE is_enabled = true) AS enabled, COUNT(*) FILTER (WHERE is_enabled = false) AS disabled FROM automation_rules WHERE family_id = ?');
        $stmt->execute([$familyId]);
        $row = $stmt->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'enabled' => (int)($row['enabled'] ?? 0),
            'disabled' => (int)($row['disabled'] ?? 0),
        ];
    }
}
