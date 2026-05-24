<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers.php';

use App\Database\Database;
use App\Router\Router;
use App\Middleware\AuthMiddleware;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Expose-Headers: X-Total-Count');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

Database::configure($config['database']);

$router = new Router();

$router->group('/api/auth', function (Router $r) {
    $r->post('/register', [\App\Controllers\AuthController::class, 'register']);
    $r->post('/login', [\App\Controllers\AuthController::class, 'login']);
    $r->post('/logout', [\App\Controllers\AuthController::class, 'logout']);
    $r->get('/me', [\App\Controllers\AuthController::class, 'me']);
});

$router->group('/api', function (Router $r) {
    $r->group('/families', function (Router $r) {
        $r->get('', [\App\Controllers\FamilyController::class, 'index']);
        $r->post('', [\App\Controllers\FamilyController::class, 'store']);
        $r->get('/{id}', [\App\Controllers\FamilyController::class, 'show']);
        $r->put('/{id}', [\App\Controllers\FamilyController::class, 'update']);
        $r->delete('/{id}', [\App\Controllers\FamilyController::class, 'destroy']);
        $r->get('/{id}/members', [\App\Controllers\FamilyController::class, 'members']);
        $r->post('/{id}/members', [\App\Controllers\FamilyController::class, 'addMember']);
        $r->delete('/{id}/members/{userId}', [\App\Controllers\FamilyController::class, 'removeMember']);
    });

    $r->group('/rooms', function (Router $r) {
        $r->get('', [\App\Controllers\RoomController::class, 'index']);
        $r->post('', [\App\Controllers\RoomController::class, 'store']);
        $r->get('/{id}', [\App\Controllers\RoomController::class, 'show']);
        $r->put('/{id}', [\App\Controllers\RoomController::class, 'update']);
        $r->delete('/{id}', [\App\Controllers\RoomController::class, 'destroy']);
    });

    $r->group('/devices', function (Router $r) {
        $r->get('', [\App\Controllers\DeviceController::class, 'index']);
        $r->post('', [\App\Controllers\DeviceController::class, 'store']);
        $r->get('/types', [\App\Controllers\DeviceController::class, 'types']);
        $r->get('/{id}', [\App\Controllers\DeviceController::class, 'show']);
        $r->put('/{id}', [\App\Controllers\DeviceController::class, 'update']);
        $r->delete('/{id}', [\App\Controllers\DeviceController::class, 'destroy']);
        $r->post('/{id}/control', [\App\Controllers\DeviceController::class, 'control']);
        $r->get('/{id}/history', [\App\Controllers\DeviceController::class, 'history']);
        $r->post('/discover', [\App\Controllers\DeviceController::class, 'discover']);
        $r->post('/commission', [\App\Controllers\DeviceController::class, 'commission']);
    });

    $r->group('/scenes', function (Router $r) {
        $r->get('', [\App\Controllers\SceneController::class, 'index']);
        $r->post('', [\App\Controllers\SceneController::class, 'store']);
        $r->get('/{id}', [\App\Controllers\SceneController::class, 'show']);
        $r->put('/{id}', [\App\Controllers\SceneController::class, 'update']);
        $r->delete('/{id}', [\App\Controllers\SceneController::class, 'destroy']);
        $r->post('/{id}/execute', [\App\Controllers\SceneController::class, 'execute']);
        $r->post('/{id}/actions', [\App\Controllers\SceneController::class, 'addAction']);
        $r->put('/actions/{id}', [\App\Controllers\SceneController::class, 'updateAction']);
        $r->delete('/actions/{id}', [\App\Controllers\SceneController::class, 'deleteAction']);
    });

    $r->group('/automations', function (Router $r) {
        $r->get('', [\App\Controllers\AutomationController::class, 'index']);
        $r->post('', [\App\Controllers\AutomationController::class, 'store']);
        $r->get('/{id}', [\App\Controllers\AutomationController::class, 'show']);
        $r->put('/{id}', [\App\Controllers\AutomationController::class, 'update']);
        $r->delete('/{id}', [\App\Controllers\AutomationController::class, 'destroy']);
        $r->post('/{id}/trigger', [\App\Controllers\AutomationController::class, 'trigger']);
        $r->post('/{id}/toggle', [\App\Controllers\AutomationController::class, 'toggle']);
    });

    $r->group('/logs', function (Router $r) {
        $r->get('', [\App\Controllers\LogController::class, 'index']);
        $r->get('/stats', [\App\Controllers\LogController::class, 'stats']);
    });

    $r->group('/alerts', function (Router $r) {
        $r->get('', [\App\Controllers\AlertController::class, 'index']);
        $r->put('/{id}/read', [\App\Controllers\AlertController::class, 'markRead']);
        $r->put('/read-all', [\App\Controllers\AlertController::class, 'markAllRead']);
        $r->put('/{id}/resolve', [\App\Controllers\AlertController::class, 'resolve']);
    });

    $r->group('/matter', function (Router $r) {
        $r->get('/devices', [\App\Controllers\MatterController::class, 'devices']);
        $r->post('/pair', [\App\Controllers\MatterController::class, 'pair']);
        $r->post('/unpair', [\App\Controllers\MatterController::class, 'unpair']);
        $r->post('/subscribe', [\App\Controllers\MatterController::class, 'subscribe']);
        $r->post('/command', [\App\Controllers\MatterController::class, 'command']);
    });

    $r->group('/system', function (Router $r) {
        $r->get('/status', [\App\Controllers\SystemController::class, 'status']);
        $r->get('/dashboard', [\App\Controllers\SystemController::class, 'dashboard']);
    });
}, [AuthMiddleware::class . '@handle']);

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

$router->dispatch($method, $uri);
