<?php

declare(strict_types=1);

/**
 * End-to-end Audit Log scenario (requires DB + migrated audit_logs).
 *
 *   php tests/test_audit_log.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Services\AuthService;
use App\Services\CaseNeedsConfirmationException;
use App\Services\CaseUpsertService;

App::boot($basePath);

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "OK  {$msg}\n";
}

$db = Database::connection();

// Ensure enriched columns exist
$cols = $db->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'"
)->fetchAll(PDO::FETCH_COLUMN);
foreach (['module', 'description', 'old_values', 'new_values'] as $c) {
    assert_true(in_array($c, $cols, true), "Kolom audit_logs.{$c} ada");
}

$userId = (int) $db->query("SELECT id FROM users WHERE username='admin' LIMIT 1")->fetchColumn();
assert_true($userId > 0, 'User admin tersedia');

$typeId = (int) $db->query('SELECT id FROM case_types WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$statusDibuat = (int) $db->query("SELECT id FROM case_statuses WHERE slug='dibuat' LIMIT 1")->fetchColumn();
$statusDiproses = (int) $db->query("SELECT id FROM case_statuses WHERE slug='diproses' LIMIT 1")->fetchColumn();
$statusSelesai = (int) $db->query("SELECT id FROM case_statuses WHERE slug='selesai' LIMIT 1")->fetchColumn();
$sourceId = (int) $db->query('SELECT id FROM case_sources WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$officers = $db->query('SELECT id FROM officers WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$officerA = (int) $officers[0];
$officerB = isset($officers[1]) ? (int) $officers[1] : $officerA;

$caseNumber = 'P1234567890';
$db->prepare('DELETE FROM case_histories WHERE case_id IN (SELECT id FROM cases WHERE case_number = ?)')->execute([$caseNumber]);
$db->prepare('DELETE FROM cases WHERE case_number = ?')->execute([$caseNumber]);
$db->prepare("DELETE FROM audit_logs WHERE JSON_UNQUOTE(JSON_EXTRACT(meta, '$.case_number')) = ? OR description LIKE ?")
    ->execute([$caseNumber, '%' . $caseNumber . '%']);

$beforeCount = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

$auth = new AuthService();
$service = new CaseUpsertService();
$today = today_jakarta();

// Simulate login success via logger path (AuthService needs session; call AuditLogger via service upsert only + direct auth attempt may need session)
// Use AuthService::attempt — requires Session. Boot session for CLI.
\App\Core\Session::start();
$login = $auth->attempt('admin', 'Admin123!');
assert_true($login['ok'] === true, 'Login admin berhasil');

$base = [
    'case_number' => $caseNumber,
    'npwp' => '0123456789012345',
    'taxpayer_name' => 'WP Audit Test',
    'case_type_id' => $typeId,
    'status_id' => $statusDibuat,
    'source_id' => $sourceId,
    'created_date' => $today,
    'due_date' => date('Y-m-d', strtotime($today . ' +10 day')),
    'officer_id' => $officerA,
    'note' => 'audit create',
];

$created = $service->upsert($service->validate($base)['data'], $userId, false);
assert_true($created['action'] === 'created', 'Create case');

$p2 = $base;
$p2['status_id'] = $statusDiproses;
$p2['note'] = 'audit to diproses';
try {
    $service->upsert($service->validate($p2)['data'], $userId, false);
    assert_true(false, 'Harus minta konfirmasi');
} catch (CaseNeedsConfirmationException) {
    // ok
}
$upd1 = $service->upsert($service->validate($p2)['data'], $userId, true);
assert_true($upd1['action'] === 'updated', 'Update status Dibuat→Diproses');

$p3 = $p2;
$p3['officer_id'] = $officerB;
$p3['note'] = 'audit ganti petugas';
$upd2 = $service->upsert($service->validate($p3)['data'], $userId, true);
assert_true($upd2['action'] === 'updated', 'Update petugas');

$p4 = $p3;
$p4['due_date'] = date('Y-m-d', strtotime($today . ' +3 day'));
$p4['note'] = 'audit ganti deadline';
$upd3 = $service->upsert($service->validate($p4)['data'], $userId, true);
assert_true($upd3['action'] === 'updated', 'Update deadline');

$p5 = $p4;
$p5['status_id'] = $statusSelesai;
$p5['note'] = 'audit selesai';
$done = $service->upsert($service->validate($p5)['data'], $userId, true);
assert_true($done['action'] === 'updated', 'Selesaikan case');

$auth->logout();
$login2 = $auth->attempt('admin', 'Admin123!');
assert_true($login2['ok'] === true, 'Login kembali berhasil');

$repo = new AuditLogRepository();
$page = $repo->paginate(['q' => $caseNumber], 1, 50);
$actions = array_column($page->items, 'action');

assert_true(in_array('case_created', $actions, true), 'Audit: case_created');
assert_true(in_array('case_updated', $actions, true) || in_array('case_completed', $actions, true), 'Audit: case_updated/completed');
assert_true(in_array('case_completed', $actions, true), 'Audit: case_completed');

$allRecent = $repo->paginate([], 1, 30);
$recentActions = array_column($allRecent->items, 'action');
assert_true(in_array('login_success', $recentActions, true), 'Audit: login_success');
assert_true(in_array('logout', $recentActions, true), 'Audit: logout');

// Detail row has module + description
$createdRow = null;
foreach ($page->items as $row) {
    if ($row['action'] === 'case_created') {
        $createdRow = $row;
        break;
    }
}
assert_true($createdRow !== null, 'Baris case_created ditemukan');
assert_true(($createdRow['module'] ?? '') === 'CASE', 'Module CASE');
assert_true(str_contains((string) ($createdRow['description'] ?? ''), $caseNumber), 'Description memuat nomor kasus');
assert_true(!empty($createdRow['new_values']), 'new_values terisi');

// No password leakage in any recent audit meta/values
foreach ($allRecent->items as $row) {
    $blob = strtolower(json_encode([
        $row['meta'] ?? null,
        $row['old_values'] ?? null,
        $row['new_values'] ?? null,
        $row['description'] ?? null,
    ], JSON_UNESCAPED_UNICODE) ?: '');
    assert_true(!str_contains($blob, 'password_hash'), 'Tidak ada password_hash di audit');
    assert_true(!str_contains($blob, 'admin123!'), 'Tidak ada plaintext password di audit');
}

// Unchanged should not duplicate
$same = $service->upsert($service->validate($p5)['data'], $userId, true);
assert_true($same['action'] === 'unchanged', 'Data identik = unchanged');
$afterSame = $repo->paginate(['q' => $caseNumber], 1, 50);
assert_true($afterSame->total === $page->total, 'Tidak ada audit palsu saat unchanged');

$afterCount = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
assert_true($afterCount > $beforeCount, 'Audit logs bertambah');

echo "\nALL AUDIT LOG TESTS PASSED\n";
