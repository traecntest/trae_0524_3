<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Family;
use App\Models\Log;

class FamilyController extends Controller
{
    private Family $familyModel;
    private Log $logModel;

    public function __construct()
    {
        $this->familyModel = new Family();
        $this->logModel = new Log();
    }

    public function index(): never
    {
        global $current_user;
        $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
        $this->success($families);
    }

    public function store(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, ['name' => 'required|min:1|max:128']);
        if (!empty($errors)) {
            $this->error('家庭名称不能为空', 422, 422);
        }

        $familyId = $this->familyModel->create([
            'name' => trim($input['name']),
            'description' => $input['description'] ?? '',
            'owner_id' => (int) $current_user['id'],
            'address' => $input['address'] ?? '',
        ]);

        $this->familyModel->addMember($familyId, (int) $current_user['id'], 'owner');

        $this->logModel->add($familyId, 'info', 'family', "创建家庭: {$input['name']}", [], (int) $current_user['id']);

        $family = $this->familyModel->find($familyId);
        $this->success($family, '家庭创建成功');
    }

    public function show(array $params): never
    {
        global $current_user;
        $family = $this->familyModel->getByUser((int) $current_user['id'], (int) $params['id']);
        if (!$family) {
            $this->error('家庭不存在或无权限', 404, 404);
        }
        $family['members'] = $this->familyModel->getMembers((int) $params['id']);
        $this->success($family);
    }

    public function update(array $params): never
    {
        global $current_user;
        $family = $this->familyModel->getByUser((int) $current_user['id'], (int) $params['id']);
        if (!$family) {
            $this->error('家庭不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $data = [];
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (isset($input['description'])) $data['description'] = $input['description'];
        if (isset($input['address'])) $data['address'] = $input['address'];

        if (!empty($data)) {
            $this->familyModel->update((int) $params['id'], $data);
        }

        $this->logModel->add((int) $params['id'], 'info', 'family', "更新家庭信息", [], (int) $current_user['id']);
        $this->success(null, '更新成功');
    }

    public function destroy(array $params): never
    {
        global $current_user;
        $family = $this->familyModel->getByUser((int) $current_user['id'], (int) $params['id']);
        if (!$family) {
            $this->error('家庭不存在或无权限', 404, 404);
        }

        if ((int) $family['owner_id'] !== (int) $current_user['id']) {
            $this->error('只有家庭所有者可以删除', 403, 403);
        }

        $this->familyModel->delete((int) $params['id']);
        $this->success(null, '家庭已删除');
    }

    public function members(array $params): never
    {
        global $current_user;
        if (!$this->familyModel->isMember((int) $params['id'], (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }
        $members = $this->familyModel->getMembers((int) $params['id']);
        $this->success($members);
    }

    public function addMember(array $params): never
    {
        global $current_user;
        $family = $this->familyModel->getByUser((int) $current_user['id'], (int) $params['id']);
        if (!$family) {
            $this->error('家庭不存在或无权限', 404, 404);
        }

        $input = $this->getInput();
        $userId = (int) ($input['user_id'] ?? 0);
        $role = $input['role'] ?? 'member';

        $userModel = new \App\Models\User();
        $user = $userModel->find($userId);
        if (!$user) {
            $this->error('用户不存在', 404, 404);
        }

        $this->familyModel->addMember((int) $params['id'], $userId, $role);
        $this->logModel->add((int) $params['id'], 'info', 'family', "添加成员: {$user['username']}", [], (int) $current_user['id']);
        $this->success(null, '成员添加成功');
    }

    public function removeMember(array $params): never
    {
        global $current_user;
        $family = $this->familyModel->getByUser((int) $current_user['id'], (int) $params['id']);
        if (!$family) {
            $this->error('家庭不存在或无权限', 404, 404);
        }

        $this->familyModel->removeMember((int) $params['id'], (int) $params['userId']);
        $this->logModel->add((int) $params['id'], 'info', 'family', "移除成员", [], (int) $current_user['id']);
        $this->success(null, '成员已移除');
    }
}
