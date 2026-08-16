<?php

declare(strict_types=1);

/**
 * Automated uniqueness / UPSERT tests for cases.
 *
 * Usage:
 *   php tests/test_case_upsert.php
 *
 * Requires: .env DB configured, schema + seed imported, at least one admin user optional
 * (uses user id 1 if present, otherwise creates history with null-safe changed_by).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;
use App\Repositories\CaseHistoryRepository;
use App\Repositories\CaseRepository;
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

try {
    $db = Database::connection();
    $types = (int) $db->query('SELECT COUNT(*) FROM case_types')->fetchColumn();
    $statuses = (int) $db->query('SELECT COUNT(*) FROM case_statuses')->fetchColumn();
    $sources = (int) $db->query('SELECT COUNT(*) FROM case_sources')->fetchColumn();
    $officers = (int) $db->query('SELECT COUNT(*) FROM officers')->fetchColumn();

    assert_true($types > 0 && $statuses > 0 && $sources > 0 && $officers > 0, 'Master data seed tersedia');

    $typeId = (int) $db->query('SELECT id FROM case_types WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $statusDiproses = (int) $db->query("SELECT id FROM case_statuses WHERE slug='diproses' LIMIT 1")->fetchColumn();
    $statusSelesai = (int) $db->query("SELECT id FROM case_statuses WHERE slug='selesai' LIMIT 1")->fetchColumn();
    $sourceId = (int) $db->query('SELECT id FROM case_sources WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $officerId = (int) $db->query('SELECT id FROM officers WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $userId = (int) ($db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($userId < 1) {
        // Allow null changed_by — still create a disposable admin for FK friendliness if possible
        $hash = password_hash('TestAdmin123!', PASSWORD_DEFAULT);
        $db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, officer_id, is_active, created_at, updated_at)
             VALUES ("__test_admin__", NULL, ?, "Test Admin", "admin", NULL, 1, NOW(), NOW())'
        )->execute([$hash]);
        $userId = (int) $db->lastInsertId();
    }

    assert_true($statusDiproses > 0 && $statusSelesai > 0, 'Status Diproses & Selesai ada');

    $service = new CaseUpsertService();
    $cases = new CaseRepository();
    $histories = new CaseHistoryRepository();

    $caseNumber = 'T' . str_pad((string) random_int(0, 999999999), 10, '0', STR_PAD_LEFT);
    // Ensure unique pattern 1 letter + 10 digits
    if (!preg_match('/^[A-Z][0-9]{10}$/', $caseNumber)) {
        $caseNumber = 'T' . substr(str_replace(['.', 'E', '+'], '', (string) microtime(true)) . random_int(1000, 9999), 0, 10);
        $caseNumber = strtoupper(substr($caseNumber, 0, 11));
        if (strlen($caseNumber) < 11) {
            $caseNumber = 'T' . str_pad(substr(preg_replace('/\D/', '', $caseNumber) ?: '1', 0, 10), 10, '0', STR_PAD_LEFT);
        }
    }
    // Force clean test number
    $caseNumber = 'P' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
    $caseNumber = substr($caseNumber, 0, 11);

    $payloadBase = [
        'case_number' => $caseNumber,
        'npwp' => '0123456789012345',
        'taxpayer_name' => 'WP Test Uniqueness',
        'case_type_id' => $typeId,
        'status_id' => $statusDiproses,
        'source_id' => $sourceId,
        'created_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+10 days')),
        'officer_id' => $officerId,
        'note' => 'create test',
    ];

    $validated = $service->validate($payloadBase);
    assert_true($validated['ok'], 'Validasi create payload lulus');

    $beforeCount = $cases->countAll();
    $created = $service->upsert($validated['data'], $userId, false);
    assert_true($created['action'] === 'created', 'Action create = created');
    assert_true($cases->countAll() === $beforeCount + 1, 'Total cases bertambah 1 setelah create');

    $row = $cases->findByNumber($caseNumber);
    assert_true($row !== null && (int) $row['status_id'] === $statusDiproses, 'Case tersimpan status Diproses');

    // Update without confirm must require confirmation
    $payloadUpdate = $payloadBase;
    $payloadUpdate['status_id'] = $statusSelesai;
    $payloadUpdate['note'] = 'update to selesai';
    $validated2 = $service->validate($payloadUpdate);
    assert_true($validated2['ok'], 'Validasi update payload lulus');

    $needsConfirm = false;
    try {
        $service->upsert($validated2['data'], $userId, false);
    } catch (CaseNeedsConfirmationException $e) {
        $needsConfirm = true;
        assert_true($e->existingCase()['case_number'] === $caseNumber, 'Exception membawa existing case');
    }
    assert_true($needsConfirm, 'Update tanpa confirm melempar CaseNeedsConfirmationException');
    assert_true($cases->countAll() === $beforeCount + 1, 'Tanpa confirm: total cases tidak berubah');

    // Confirmed update
    $updated = $service->upsert($validated2['data'], $userId, true);
    assert_true($updated['action'] === 'updated', 'Action update = updated');
    assert_true($cases->countAll() === $beforeCount + 1, 'Setelah update: total cases TIDAK bertambah');

    $row2 = $cases->findByNumber($caseNumber);
    assert_true($row2 !== null && (int) $row2['status_id'] === $statusSelesai, 'Record utama status = Selesai');

    $hist = $histories->listByCaseId((int) $row2['id'], 'asc');
    assert_true(count($hist) >= 2, 'History minimal 2 event (CREATED + STATUS_CHANGED/UPDATED)');
    $last = $hist[count($hist) - 1];
    assert_true(
        in_array($last['event_type'], ['UPDATED', 'STATUS_CHANGED'], true),
        'History terakhir event update/status_changed'
    );
    assert_true((int) $last['old_status_id'] === $statusDiproses, 'History old_status = Diproses');
    assert_true((int) $last['new_status_id'] === $statusSelesai, 'History new_status = Selesai');

    // Identical data => unchanged, no new fake history
    $histCountBefore = count($histories->listByCaseId((int) $row2['id']));
    $same = $service->upsert($validated2['data'], $userId, true);
    assert_true($same['action'] === 'unchanged', 'Data identik = unchanged');
    assert_true(
        count($histories->listByCaseId((int) $row2['id'])) === $histCountBefore,
        'Tidak membuat history palsu saat data sama'
    );

    // UNIQUE index still present
    $idx = $db->query("SHOW INDEX FROM cases WHERE Key_name = 'uk_cases_case_number'")->fetchAll();
    assert_true($idx !== [], 'UNIQUE index uk_cases_case_number aktif di database');

    // Invalid formats rejected
    $bad = $service->validate(array_merge($payloadBase, ['case_number' => 'INVALID']));
    assert_true(!$bad['ok'] && isset($bad['errors']['case_number']), 'Nomor kasus invalid ditolak');
    $badNpwp = $service->validate(array_merge($payloadBase, ['case_number' => 'C0123456789', 'npwp' => '123']));
    assert_true(!$badNpwp['ok'] && isset($badNpwp['errors']['npwp']), 'NPWP invalid ditolak');

    echo "\nALL CASE UPSERT TESTS PASSED\n";
    echo "Test case_number used: {$caseNumber}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\n" . $e->getMessage() . "\n");
    if (isset($e) && ($e instanceof Throwable)) {
        fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . "\n");
    }
    exit(1);
}
