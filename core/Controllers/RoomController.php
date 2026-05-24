<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Room;
use App\Models\Family;
use App\Models\Log;

class RoomController extends Controller
{
    private Room $roomModel;
    private Family $familyModel;
    private Log $logModel;

    public function __construct()
    {
        $this->roomModel = new Room();
        $this->familyModel = new Family();
        $this->logModel = new Log();
    }

    public function index(): never
    {
        global $current_user;
        $query = $this->getQueryParams();
        $familyId = (int) ($query['family_id'] ?? 0);

        if ($familyId > 0) {
            if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
                $this->error('无权限', 403, 403);
            }
            $rooms = $this->roomModel->withDeviceCount($familyId);
        } else {
            $families = $this->familyModel->getUserFamilies((int) $current_user['id']);
            $rooms = [];
            foreach ($families as $family) {
                $rooms = array_merge($rooms, $this->roomModel->withDeviceCount((int) $family['id']));
            }
        }

        $this->success($rooms);
    }

    public function store(): never
    {
        global $current_user;
        $input = $this->getInput();
        $errors = $this->validateInput($input, [
            'family_id' => 'required|integer',
            'name' => 'required|min:1|max:64',
        ]);
        if (!empty($errors)) {
            $this->error('参数不完整', 422, 422);
        }

        $familyId = (int) $input['family_id'];
        if (!$this->familyModel->isMember($familyId, (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $roomId = $this->roomModel->create([
            'family_id' => $familyId,
            'name' => trim($input['name']),
            'type' => $input['type'] ?? 'living_room',
            'icon' => $input['icon'] ?? 'room',
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);

        $this->logModel->add($familyId, 'info', 'room', "创建房间: {$input['name']}", [], (int) $current_user['id']);
        $room = $this->roomModel->find($roomId);
        $this->success($room, '房间创建成功');
    }

    public function show(array $params): never
    {
        global $current_user;
        $room = $this->roomModel->find((int) $params['id']);
        if (!$room) {
            $this->error('房间不存在', 404, 404);
        }
        if (!$this->familyModel->isMember((int) $room['family_id'], (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }
        $this->success($room);
    }

    public function update(array $params): never
    {
        global $current_user;
        $room = $this->roomModel->find((int) $params['id']);
        if (!$room || !$this->familyModel->isMember((int) $room['family_id'], (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $input = $this->getInput();
        $data = [];
        if (isset($input['name'])) $data['name'] = trim($input['name']);
        if (isset($input['type'])) $data['type'] = $input['type'];
        if (isset($input['icon'])) $data['icon'] = $input['icon'];
        if (isset($input['sort_order'])) $data['sort_order'] = (int) $input['sort_order'];

        if (!empty($data)) {
            $this->roomModel->update((int) $params['id'], $data);
        }

        $this->logModel->add((int) $room['family_id'], 'info', 'room', "更新房间: {$room['name']}", [], (int) $current_user['id']);
        $this->success(null, '更新成功');
    }

    public function destroy(array $params): never
    {
        global $current_user;
        $room = $this->roomModel->find((int) $params['id']);
        if (!$room || !$this->familyModel->isMember((int) $room['family_id'], (int) $current_user['id'])) {
            $this->error('无权限', 403, 403);
        }

        $this->roomModel->delete((int) $params['id']);
        $this->logModel->add((int) $room['family_id'], 'info', 'room', "删除房间: {$room['name']}", [], (int) $current_user['id']);
        $this->success(null, '房间已删除');
    }
}
