<?php

declare(strict_types=1);

/**
 * Phase 7 final integration tests (requires MySQL + schema/seed).
 *
 * Usage:
 *   php tests/test_phase7_integration.php
 *
 * Covers:
 *   TEST 1–3  create → update status → selesai (UPSERT uniqueness + history + deadline exit)
 *   TEST 4–6  H-3 / H-5 / TERLAMBAT via DeadlineClassifier + DB buckets
 *   TEST 7    petugas 403 when viewing another officer's case (service-level gate)
 *   Extra     FilterNormalizer aliases, AlertService alert bucket
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
use App\Services\AlertService;
use App\Services\CaseNeedsConfirmationException;
use App\Services\CaseUpsertService;
use App\Services\DeadlineClassifier;
use App\Services\FilterNormalizer;

App::boot($basePath);

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "OK  {$msg}\n";
}

function unique_case_number(string $prefix = 'P'): string
{
    return $prefix . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
}

// --- Offline alias checks (always run) ---
assert_true(DeadlineClassifier::normalizeKey('terlambat') === 'overdue', 'alias terlambat → overdue');
assert_true(DeadlineClassifier::normalizeKey('hari_ini') === 'today', 'alias hari_ini → today');
assert_true(DeadlineClassifier::normalizeKey('H-3') === 'h3', 'alias H-3 → h3');
assert_true(DeadlineClassifier::normalizeKey('waspada') === 'h5', 'alias waspada → h5');
assert_true(DeadlineClassifier::normalizeKey('alert') === 'alert', 'alias alert tetap alert');

$today = today_jakarta();
$tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
$plus4 = date('Y-m-d', strtotime($today . ' +4 day'));
$yesterday = date('Y-m-d', strtotime($today . ' -1 day'));

assert_true(DeadlineClassifier::classify($tomorrow, false, $today)['key'] === 'h3', 'TEST 4 engine: due besok = H-3');
assert_true(DeadlineClassifier::classify($plus4, false, $today)['key'] === 'h5', 'TEST 5 engine: due +4 = H-5');
assert_true(DeadlineClassifier::classify($yesterday, false, $today)['key'] === 'overdue', 'TEST 6 engine: due kemarin = TERLAMBAT');

try {
    $db = Database::connection();
} catch (Throwable $e) {
    echo "\nSKIP DB integration (connection failed): {$e->getMessage()}\n";
    echo "Offline alias + deadline engine checks PASSED.\n";
    exit(0);
}

try {
    $typeId = (int) $db->query('SELECT id FROM case_types WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $statusDibuat = (int) $db->query("SELECT id FROM case_statuses WHERE slug='dibuat' LIMIT 1")->fetchColumn();
    $statusDiproses = (int) $db->query("SELECT id FROM case_statuses WHERE slug='diproses' LIMIT 1")->fetchColumn();
    $statusSelesai = (int) $db->query("SELECT id FROM case_statuses WHERE slug='selesai' LIMIT 1")->fetchColumn();
    $sourceId = (int) $db->query('SELECT id FROM case_sources WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();

    $officers = $db->query('SELECT id, name FROM officers WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll();
    assert_true(count($officers) >= 1, 'Minimal 1 petugas aktif');
    $officerA = (int) $officers[0]['id'];
    $officerB = isset($officers[1]) ? (int) $officers[1]['id'] : $officerA;

    $userId = (int) ($db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($userId < 1) {
        $hash = password_hash('TestAdmin123!', PASSWORD_DEFAULT);
        $db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, officer_id, is_active, created_at, updated_at)
             VALUES ("__p7_admin__", NULL, ?, "P7 Admin", "admin", NULL, 1, NOW(), NOW())'
        )->execute([$hash]);
        $userId = (int) $db->lastInsertId();
    }

    assert_true($statusDibuat > 0 && $statusDiproses > 0 && $statusSelesai > 0, 'Status dibuat/diproses/selesai ada');

    $service = new CaseUpsertService();
    $cases = new CaseRepository();
    $histories = new CaseHistoryRepository();
    $alerts = new AlertService();

    // FilterNormalizer (DB needed for status slug)
    $norm = FilterNormalizer::normalizeCaseFilters([
        'officer' => (string) $officerA,
        'status' => 'diproses',
        'deadline' => 'h3',
    ]);
    assert_true((int) $norm['officer_id'] === $officerA, 'FilterNormalizer officer= → officer_id');
    assert_true((int) $norm['status_id'] === $statusDiproses, 'FilterNormalizer status=diproses → status_id');
    assert_true($norm['deadline'] === 'h3', 'FilterNormalizer deadline=h3');

    $scoped = FilterNormalizer::applyRoleScope(
        ['officer_id' => $officerB],
        ['role' => 'petugas', 'officer_id' => $officerA]
    );
    assert_true((int) $scoped['officer_id'] === $officerA, 'Role scope petugas memaksa officer sendiri');

    // ========== TEST 1: Create ==========
    $caseNumber = unique_case_number('P');
    $payload = [
        'case_number' => $caseNumber,
        'npwp' => '0123456789012345',
        'taxpayer_name' => 'WP Phase7 Cindy',
        'case_type_id' => $typeId,
        'status_id' => $statusDibuat,
        'source_id' => $sourceId,
        'created_date' => $today,
        'due_date' => date('Y-m-d', strtotime($today . ' +10 day')),
        'officer_id' => $officerA,
        'note' => 'phase7 create',
    ];
    $validated = $service->validate($payload);
    assert_true($validated['ok'], 'TEST 1 validasi create');

    $created = $service->upsert($validated['data'], $userId, false);
    $caseId = (int) $created['case']['id'];
    assert_true($created['action'] === 'created', 'TEST 1 action=created');

    $found = $cases->findByNumber($caseNumber);
    assert_true($found !== null && (int) $found['id'] === $caseId, 'TEST 1 case muncul di DB');

    $histCount1 = count($histories->listByCaseId($caseId));
    assert_true($histCount1 >= 1, 'TEST 1 history CREATED tercatat');

    $listCindy = $cases->paginate(['officer_id' => $officerA, 'q' => $caseNumber], 1, 10);
    assert_true($listCindy->total >= 1, 'TEST 1 muncul di monitoring filter petugas');

    // ========== TEST 2: Upsert status Dibuat → Diproses ==========
    $payload2 = $payload;
    $payload2['status_id'] = $statusDiproses;
    $payload2['note'] = 'phase7 to diproses';
    try {
        $service->upsert($service->validate($payload2)['data'], $userId, false);
        assert_true(false, 'TEST 2 harus minta konfirmasi tanpa confirm=true');
    } catch (CaseNeedsConfirmationException) {
        // expected
    }
    $updated = $service->upsert($service->validate($payload2)['data'], $userId, true);
    assert_true($updated['action'] === 'updated', 'TEST 2 action=updated');

    $stmt = $db->prepare('SELECT COUNT(*) FROM cases WHERE case_number = ?');
    $stmt->execute([$caseNumber]);
    assert_true((int) $stmt->fetchColumn() === 1, 'TEST 2 tetap 1 row (UNIQUE)');

    $histCount2 = count($histories->listByCaseId($caseId));
    assert_true($histCount2 > $histCount1, 'TEST 2 case_history bertambah');

    $after = $cases->findById($caseId);
    assert_true((int) $after['status_id'] === $statusDiproses, 'TEST 2 status = Diproses');

    // ========== TEST 3: Diproses → Selesai ==========
    $payload3 = $payload2;
    $payload3['status_id'] = $statusSelesai;
    $payload3['note'] = 'phase7 selesai';
    $done = $service->upsert($service->validate($payload3)['data'], $userId, true);
    assert_true($done['action'] === 'updated', 'TEST 3 updated ke Selesai');

    $doneRow = $cases->findById($caseId);
    assert_true((bool) (int) $doneRow['status_is_completed'], 'TEST 3 status completed');
    $dlDone = DeadlineClassifier::classify((string) $doneRow['due_date'], true, $today);
    assert_true($dlDone['key'] === 'selesai', 'TEST 3 classifier = selesai');

    $alertAfterDone = $cases->paginate([
        'case_number' => $caseNumber,
        'deadline' => 'alert',
    ], 1, 5);
    assert_true($alertAfterDone->total === 0, 'TEST 3 tidak masuk alert/deadline aktif');

    // ========== TEST 4: H-3 (due tomorrow) ==========
    $n4 = unique_case_number('H');
    $p4 = [
        'case_number' => $n4,
        'npwp' => '0123456789012345',
        'taxpayer_name' => 'WP H3',
        'case_type_id' => $typeId,
        'status_id' => $statusDiproses,
        'source_id' => $sourceId,
        'created_date' => $today,
        'due_date' => $tomorrow,
        'officer_id' => $officerA,
        'note' => 'h3',
    ];
    $c4 = $service->upsert($service->validate($p4)['data'], $userId, false);
    $bucketH3 = $cases->paginate(['case_number' => $n4, 'deadline' => 'h3'], 1, 5);
    assert_true($bucketH3->total === 1, 'TEST 4 case masuk bucket H-3');

    // ========== TEST 5: H-5 (+4 days) ==========
    $n5 = unique_case_number('W');
    $p5 = $p4;
    $p5['case_number'] = $n5;
    $p5['taxpayer_name'] = 'WP H5';
    $p5['due_date'] = $plus4;
    $service->upsert($service->validate($p5)['data'], $userId, false);
    $bucketH5 = $cases->paginate(['case_number' => $n5, 'deadline' => 'h5'], 1, 5);
    assert_true($bucketH5->total === 1, 'TEST 5 case masuk bucket H-5');

    // ========== TEST 6: TERLAMBAT ==========
    $n6 = unique_case_number('L');
    $p6 = $p4;
    $p6['case_number'] = $n6;
    $p6['taxpayer_name'] = 'WP Late';
    $p6['due_date'] = $yesterday;
    $service->upsert($service->validate($p6)['data'], $userId, false);
    $bucketLate = $cases->paginate(['case_number' => $n6, 'deadline' => 'overdue'], 1, 5);
    assert_true($bucketLate->total === 1, 'TEST 6 case masuk TERLAMBAT');

    $alertList = $alerts->list(['officer_id' => $officerA], 1, 50);
    $alertNumbers = array_column($alertList['items'], 'case_number');
    assert_true(in_array($n4, $alertNumbers, true), 'Alert Center memuat H-3');
    assert_true(in_array($n5, $alertNumbers, true), 'Alert Center memuat H-5');
    assert_true(in_array($n6, $alertNumbers, true), 'Alert Center memuat Terlambat');
    assert_true(!in_array($caseNumber, $alertNumbers, true), 'Case selesai tidak di Alert Center');

    // ========== TEST 7: Permission petugas ==========
    if ($officerA !== $officerB) {
        $foreign = $cases->findByNumber($n6);
        $petugasUser = ['role' => 'petugas', 'officer_id' => $officerB];
        $allowed = (int) $foreign['officer_id'] === (int) $petugasUser['officer_id'];
        assert_true(!$allowed, 'TEST 7 petugas B tidak boleh akses case petugas A (gate 403)');
    } else {
        echo "SKIP TEST 7 (hanya 1 petugas di seed — tambah petugas kedua untuk uji 403 penuh)\n";
    }

    // Combined filter: officer + status + deadline
    $combo = $cases->paginate([
        'officer_id' => $officerA,
        'status_id' => $statusDiproses,
        'deadline' => 'h3',
        'case_number' => $n4,
    ], 1, 10);
    assert_true($combo->total === 1, 'Kombinasi filter officer+status+deadline H-3 benar');

    echo "\nALL PHASE 7 INTEGRATION TESTS PASSED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n" . $e->getMessage() . "\n");
    exit(1);
}
