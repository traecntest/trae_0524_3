<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    public function handle(array $params): mixed
    {
        $token = $this->extractToken();

        if ($token === null) {
            http_response_code(401);
            echo json_encode(['code' => 401, 'message' => '未登录或Token无效']);
            exit;
        }

        $user = $this->validateToken($token);
        if ($user === null) {
            http_response_code(401);
            echo json_encode(['code' => 401, 'message' => 'Token已过期或无效']);
            exit;
        }

        $GLOBALS['current_user'] = $user;
        return true;
    }

    private function extractToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    private function validateToken(string $token): ?array
    {
        $pdo = \App\Database\Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = (SELECT user_id FROM user_sessions WHERE token = ? AND expires_at > NOW())');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}
