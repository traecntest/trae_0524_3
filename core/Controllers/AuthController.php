<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function register(): never
    {
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'username' => 'required|min:3|max:64',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if (!empty($errors)) {
            $this->error('验证失败', 422, 422);
        }

        $username = trim($input['username']);
        $email = trim($input['email']);
        $password = $input['password'];

        if ($this->userModel->findByUsername($username)) {
            $this->error('用户名已存在');
        }
        if ($this->userModel->findByEmail($email)) {
            $this->error('邮箱已被注册');
        }

        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'real_name' => $input['real_name'] ?? $username,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $user = $this->userModel->find($userId);
        $token = $this->userModel->createSession($userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

        $this->success([
            'user' => $this->sanitizeUser($user),
            'token' => $token,
        ], '注册成功');
    }

    public function login(): never
    {
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'username' => 'required',
            'password' => 'required',
        ]);

        if (!empty($errors)) {
            $this->error('请输入用户名和密码', 422, 422);
        }

        $username = trim($input['username']);
        $password = $input['password'];

        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            $user = $this->userModel->findByEmail($username);
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->error('用户名或密码错误', 401, 401);
        }

        if ($user['status'] !== 'active') {
            $this->error('账户已被禁用', 403, 403);
        }

        $this->userModel->updateLastLogin((int) $user['id']);
        $token = $this->userModel->createSession((int) $user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

        $this->success([
            'user' => $this->sanitizeUser($user),
            'token' => $token,
        ], '登录成功');
    }

    public function logout(): never
    {
        $token = $this->extractBearerToken();
        if ($token) {
            $this->userModel->destroySession($token);
        }
        $this->success(null, '已退出登录');
    }

    public function me(): never
    {
        global $current_user;
        if (!$current_user) {
            $this->error('未登录', 401, 401);
        }
        $this->success($this->sanitizeUser($current_user));
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function extractBearerToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return null;
    }
}
