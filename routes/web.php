<?php

declare(strict_types=1);

use App\Controllers\AlertController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\CaseController;
use App\Controllers\CaseImportController;
use App\Controllers\DashboardController;
use App\Controllers\Master\CaseTypeController;
use App\Controllers\Master\OfficerController;
use App\Controllers\Master\SourceController;
use App\Controllers\Master\StatusController;
use App\Controllers\MonitoringController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;

/** @var Router $router */

$auth = [AuthMiddleware::class];
$csrf = [CsrfMiddleware::class];
$authCsrf = [AuthMiddleware::class, CsrfMiddleware::class];
$admin = [AuthMiddleware::class, RoleMiddleware::require(['admin'])];
$adminCsrf = [AuthMiddleware::class, CsrfMiddleware::class, RoleMiddleware::require(['admin'])];

$router->get('/', [DashboardController::class, 'index'], $auth);
$router->get('/dashboard', [DashboardController::class, 'index'], $auth);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], $csrf);
$router->post('/logout', [AuthController::class, 'logout'], $authCsrf);

$router->get('/profile', [ProfileController::class, 'index'], $auth);
$router->post('/profile/update', [ProfileController::class, 'update'], $authCsrf);

// Cases — CORE
$router->get('/cases', [CaseController::class, 'index'], $auth);
$router->get('/cases/create', [CaseController::class, 'create'], $auth);
$router->post('/cases/upsert', [CaseController::class, 'upsert'], $authCsrf);
$router->get('/cases/import', [CaseImportController::class, 'form'], $auth);
$router->post('/cases/import', [CaseImportController::class, 'store'], $authCsrf);
$router->get('/cases/import/template', [CaseImportController::class, 'template'], $auth);
$router->get('/cases/{id}', [CaseController::class, 'show'], $auth);
$router->get('/cases/{id}/edit', [CaseController::class, 'edit'], $auth);
$router->post('/cases/{id}/update', [CaseController::class, 'update'], $authCsrf);
$router->get('/api/cases/by-number', [CaseController::class, 'lookupByNumber'], $auth);
$router->get('/api/cases/search', [CaseController::class, 'searchCases'], $auth);

$router->get('/monitoring/deadlines', [MonitoringController::class, 'deadlines'], $auth);
$router->get('/monitoring/officers', [MonitoringController::class, 'officers'], $auth);
$router->get('/alerts', [AlertController::class, 'index'], $auth);

// Master — Officers
$router->get('/master/officers', [OfficerController::class, 'index'], $admin);
$router->get('/master/officers/create', [OfficerController::class, 'create'], $admin);
$router->post('/master/officers', [OfficerController::class, 'store'], $adminCsrf);
$router->get('/master/officers/{id}/edit', [OfficerController::class, 'edit'], $admin);
$router->post('/master/officers/{id}/update', [OfficerController::class, 'update'], $adminCsrf);
$router->post('/master/officers/{id}/toggle', [OfficerController::class, 'toggle'], $adminCsrf);

// Master — Case types
$router->get('/master/case-types', [CaseTypeController::class, 'index'], $admin);
$router->get('/master/case-types/create', [CaseTypeController::class, 'create'], $admin);
$router->post('/master/case-types', [CaseTypeController::class, 'store'], $adminCsrf);
$router->get('/master/case-types/{id}/edit', [CaseTypeController::class, 'edit'], $admin);
$router->post('/master/case-types/{id}/update', [CaseTypeController::class, 'update'], $adminCsrf);
$router->post('/master/case-types/{id}/toggle', [CaseTypeController::class, 'toggle'], $adminCsrf);

// Master — Statuses
$router->get('/master/statuses', [StatusController::class, 'index'], $admin);
$router->get('/master/statuses/create', [StatusController::class, 'create'], $admin);
$router->post('/master/statuses', [StatusController::class, 'store'], $adminCsrf);
$router->get('/master/statuses/{id}/edit', [StatusController::class, 'edit'], $admin);
$router->post('/master/statuses/{id}/update', [StatusController::class, 'update'], $adminCsrf);
$router->post('/master/statuses/{id}/toggle', [StatusController::class, 'toggle'], $adminCsrf);

// Master — Sources
$router->get('/master/sources', [SourceController::class, 'index'], $admin);
$router->get('/master/sources/create', [SourceController::class, 'create'], $admin);
$router->post('/master/sources', [SourceController::class, 'store'], $adminCsrf);
$router->get('/master/sources/{id}/edit', [SourceController::class, 'edit'], $admin);
$router->post('/master/sources/{id}/update', [SourceController::class, 'update'], $adminCsrf);
$router->post('/master/sources/{id}/toggle', [SourceController::class, 'toggle'], $adminCsrf);

$router->get('/master', static function (): void {
    redirect('/master/officers');
}, $admin);

// Users
$router->get('/users', [UserController::class, 'index'], $admin);
$router->get('/users/create', [UserController::class, 'create'], $admin);
$router->post('/users', [UserController::class, 'store'], $adminCsrf);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], $admin);
$router->post('/users/{id}/update', [UserController::class, 'update'], $adminCsrf);
$router->post('/users/{id}/toggle', [UserController::class, 'toggle'], $adminCsrf);

$router->get('/audit-logs', [AuditLogController::class, 'index'], $admin);
$router->get('/audit-logs/{id}', [AuditLogController::class, 'show'], $admin);
