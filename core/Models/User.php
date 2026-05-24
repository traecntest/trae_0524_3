<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['username', 'email', 'password_hash', 'real_name', 'avatar_url', 'role', 'status', 'last_login_at'];
    protected array $casts = ['id' => 'int', 'last_login_at' => 'string'];

    public function createSession(int $userId, string $ip = '', string $userAgent = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (int) config('security.session_lifetime', 86400));

        $stmt = $this->pdo->prepare('INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $token, $ip, $userAgent, $expiresAt]);

        return $token;
    }

    public function destroySession(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE token = ?');
        $stmt->execute([$token]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
        $stmt->execute([now(), $userId]);
    }

    public function getFamilies(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.*, fm.role AS member_role, fm.joined_at FROM families f JOIN family_members fm ON f.id = fm.family_id WHERE fm.user_id = ? ORDER BY f.created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
