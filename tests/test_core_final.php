<?php

declare(strict_types=1);

/**
 * Final CORE requirement tests (TEST 1–10). No new features — verification only.
 *
 *   php tests/test_core_final.php
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
use App\Services\DashboardService;
use App\Services\DeadlineClassifier;
use App\Services\FilterNormalizer;
use App\Services\MonitoringService;

App::boot($basePath);

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "OK  {$msg}\n";
}

$db = Database::connection();
$service = new CaseUpsertService();
$cases = new CaseRepository();
$histories = new CaseHistoryRepository();
$monitoring = new MonitoringService();
$dashboard = new DashboardService();

$userId = (int) $db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$typeId = (int) $db->query('SELECT id FROM case_types WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$statusDibuat = (int) $db->query("SELECT id FROM case_statuses WHERE slug='dibuat' LIMIT 1")->fetchColumn();
$statusDiproses = (int) $db->query("SELECT id FROM case_statuses WHERE slug='diproses' LIMIT 1")->fetchColumn();
$statusSelesai = (int) $db->query("SELECT id FROM case_statuses WHERE slug='selesai' LIMIT 1")->fetchColumn();
$sourceId = (int) $db->query('SELECT id FROM case_sources WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$officers = $db->query('SELECT id FROM officers WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$officerA = (int) $officers[0];
$officerB = isset($officers[1]) ? (int) $officers[1] : $officerA;

$today = today_jakarta();
$caseNumber = 'P1234567890';

// Cleanup previous run for this number
$old = $cases->findByNumber($caseNumber);
if ($old) {
    $db->prepare('DELETE FROM case_histories WHERE case_id = ?')->execute([(int) $old['id']]);
    $db->prepare('DELETE FROM cases WHERE id = ?')->execute([(int) $old['id']]);
}

$idx = $db->query("SHOW INDEX FROM cases WHERE Key_name = 'uk_cases_case_number'")->fetchAll();
assert_true($idx !== [], 'DB: UNIQUE uk_cases_case_number');

$base = [
    'case_number' => $caseNumber,
    'npwp' => '0123456789012345',
    'taxpayer_name' => 'WP Core Final',
    'case_type_id' => $typeId,
    'status_id' => $statusDiproses,
    'source_id' => $sourceId,
    'created_date' => $today,
    'due_date' => date('Y-m-d', strtotime($today . ' +10 day')),
    'officer_id' => $officerA,
    'note' => 'TEST 1 create',
];

// TEST 1
$c1 = $service->upsert($service->validate($base)['data'], $userId, false);
assert_true($c1['action'] === 'created', 'TEST 1 create P1234567890');
$stmt = $db->prepare('SELECT COUNT(*) FROM cases WHERE case_number = ?');
$stmt->execute([$caseNumber]);
assert_true((int) $stmt->fetchColumn() === 1, 'TEST 1 hanya 1 row');

// TEST 2
$p2 = $base;
$p2['status_id'] = $statusSelesai;
$p2['note'] = 'TEST 2 to selesai';
try {
    $service->upsert($service->validate($p2)['data'], $userId, false);
    assert_true(false, 'TEST 2 harus minta konfirmasi');
} catch (CaseNeedsConfirmationException) {
    // expected
}
$c2 = $service->upsert($service->validate($p2)['data'], $userId, true);
assert_true($c2['action'] === 'updated', 'TEST 2 update status');
$stmt->execute([$caseNumber]);
assert_true((int) $stmt->fetchColumn() === 1, 'TEST 2 tetap 1 row (no duplicate)');
$hist = $histories->listByCaseId((int) $c2['case_id']);
assert_true(count($hist) >= 2, 'TEST 2 case_history bertambah');

// Reset to active for deadline tests
$pActive = $base;
$pActive['status_id'] = $statusDiproses;
$pActive['note'] = 'reopen for deadline tests';
$service->upsert($service->validate($pActive)['data'], $userId, true);

// TEST 3 H-5 (+5 days => remaining 5 => H-5 per classifier: days 4-5)
$dueH5 = date('Y-m-d', strtotime($today . ' +5 day'));
$p3 = $pActive;
$p3['due_date'] = $dueH5;
$p3['note'] = 'TEST 3 H-5';
$service->upsert($service->validate($p3)['data'], $userId, true);
$cls3 = DeadlineClassifier::classify($dueH5, false, $today);
assert_true($cls3['key'] === 'h5', 'TEST 3 classifier H-5 (due +5)');
$bucketH5 = $cases->paginate(['case_number' => $caseNumber, 'deadline' => 'h5'], 1, 5);
assert_true($bucketH5->total === 1, 'TEST 3 monitoring bucket H-5');

// TEST 4 H-3 (+3 days)
$dueH3 = date('Y-m-d', strtotime($today . ' +3 day'));
$p4 = $p3;
$p4['due_date'] = $dueH3;
$p4['note'] = 'TEST 4 H-3';
$service->upsert($service->validate($p4)['data'], $userId, true);
$cls4 = DeadlineClassifier::classify($dueH3, false, $today);
assert_true($cls4['key'] === 'h3', 'TEST 4 classifier H-3 (due +3)');
$bucketH3 = $cases->paginate(['case_number' => $caseNumber, 'deadline' => 'h3'], 1, 5);
assert_true($bucketH3->total === 1, 'TEST 4 monitoring bucket H-3');
$notH5 = $cases->paginate(['case_number' => $caseNumber, 'deadline' => 'h5'], 1, 5);
assert_true($notH5->total === 0, 'TEST 4 H-3 tidak digandakan ke H-5');

// TEST 5 overdue
$dueLate = date('Y-m-d', strtotime($today . ' -1 day'));
$p5 = $p4;
$p5['due_date'] = $dueLate;
$p5['note'] = 'TEST 5 terlambat';
$service->upsert($service->validate($p5)['data'], $userId, true);
$cls5 = DeadlineClassifier::classify($dueLate, false, $today);
assert_true($cls5['key'] === 'overdue', 'TEST 5 classifier terlambat');
$bucketLate = $cases->paginate(['case_number' => $caseNumber, 'deadline' => 'overdue'], 1, 5);
assert_true($bucketLate->total === 1, 'TEST 5 monitoring terlambat');

// TEST 6 selesai exits deadline buckets
$p6 = $p5;
$p6['status_id'] = $statusSelesai;
$p6['note'] = 'TEST 6 selesai';
$service->upsert($service->validate($p6)['data'], $userId, true);
$row = $cases->findByNumber($caseNumber);
$cls6 = DeadlineClassifier::classify((string) $row['due_date'], true, $today);
assert_true($cls6['key'] === 'selesai', 'TEST 6 classifier selesai');
assert_true($cases->paginate(['case_number' => $caseNumber, 'deadline' => 'overdue'], 1, 5)->total === 0, 'TEST 6 tidak di terlambat');
assert_true($cases->paginate(['case_number' => $caseNumber, 'deadline' => 'h3'], 1, 5)->total === 0, 'TEST 6 tidak di H-3');
assert_true($cases->paginate(['case_number' => $caseNumber, 'deadline' => 'h5'], 1, 5)->total === 0, 'TEST 6 tidak di H-5');

// Prepare two officers cases for filter tests
$nA = 'A1000000001';
$nB = 'B1000000002';
foreach ([$nA, $nB] as $n) {
    $oldN = $cases->findByNumber($n);
    if ($oldN) {
        $db->prepare('DELETE FROM case_histories WHERE case_id = ?')->execute([(int) $oldN['id']]);
        $db->prepare('DELETE FROM cases WHERE id = ?')->execute([(int) $oldN['id']]);
    }
}

$payloadA = [
    'case_number' => $nA,
    'npwp' => '0123456789012345',
    'taxpayer_name' => 'WP Officer A',
    'case_type_id' => $typeId,
    'status_id' => $statusDiproses,
    'source_id' => $sourceId,
    'created_date' => $today,
    'due_date' => date('Y-m-d', strtotime($today . ' +8 day')),
    'officer_id' => $officerA,
    'note' => 'officer A',
];
$payloadB = $payloadA;
$payloadB['case_number'] = $nB;
$payloadB['taxpayer_name'] = 'WP Officer B';
$payloadB['officer_id'] = $officerB;
$payloadB['note'] = 'officer B';

$service->upsert($service->validate($payloadA)['data'], $userId, false);
$service->upsert($service->validate($payloadB)['data'], $userId, false);

// TEST 7 filter petugas
$listA = $cases->paginate(['officer_id' => $officerA, 'q' => ''], 1, 100);
$numbersA = array_column($listA->items, 'case_number');
assert_true(in_array($nA, $numbersA, true), 'TEST 7 case petugas A muncul');
if ($officerA !== $officerB) {
    assert_true(!in_array($nB, $numbersA, true), 'TEST 7 case petugas B tidak muncul di filter A');
}

// TEST 8 combined filters
$combo = $cases->paginate([
    'officer_id' => $officerA,
    'status_id' => $statusDiproses,
    'case_type_id' => $typeId,
    'source_id' => $sourceId,
    'created_from' => $today,
    'created_to' => $today,
    'case_number' => $nA,
], 1, 10);
assert_true($combo->total === 1, 'TEST 8 filter petugas+jenis+status+sumber+periode');

$norm = FilterNormalizer::normalizeCaseFilters([
    'officer' => (string) $officerA,
    'status' => 'diproses',
    'case_type_id' => $typeId,
    'source_id' => $sourceId,
    'created_from' => $today,
    'created_to' => $today,
]);
assert_true((int) $norm['officer_id'] === $officerA, 'TEST 8 FilterNormalizer officer');
assert_true((int) $norm['status_id'] === $statusDiproses, 'TEST 8 FilterNormalizer status slug');

// TEST 9 dashboard from DB
$dash = $dashboard->build([]);
assert_true(isset($dash['kpi']['h5'], $dash['kpi']['h3'], $dash['kpi']['overdue']), 'TEST 9 KPI H-5/H-3/terlambat ada');
assert_true(is_int($dash['kpi']['h5']) || is_numeric($dash['kpi']['h5']), 'TEST 9 KPI numeric dari DB');
assert_true(isset($dash['charts']['workload']) || isset($dash['tables']['workload']), 'TEST 9 workload petugas ada');
assert_true(isset($dash['charts']['types']) || isset($dash['charts']['priority']), 'TEST 9 jenis kasus chart ada');

// TEST 10 detail + history
$detail = $cases->findById((int) $c2['case_id']);
assert_true($detail !== null, 'TEST 10 detail case ditemukan');
assert_true($detail['case_number'] === $caseNumber, 'TEST 10 nomor kasus benar');
$hist10 = $histories->listByCaseId((int) $detail['id']);
assert_true(count($hist10) >= 2, 'TEST 10 history dari DB (>=2)');
$hasChangedFields = false;
foreach ($hist10 as $h) {
    if (!empty($h['changed_fields'])) {
        $hasChangedFields = true;
        break;
    }
}
assert_true($hasChangedFields, 'TEST 10 history memuat changed_fields');

// Date UI helper: date-only (no time)
$formatted = format_date_id($today);
assert_true(!str_contains($formatted, ':'), 'UI date helper tanpa jam');
assert_true(preg_match('/^\d{2}-\d{2}-\d{4}$/', $formatted) === 1, 'UI date format dd-mm-yyyy');

// Monitoring officers path works
$wl = $monitoring->workloadSummary([]);
assert_true(is_array($wl) && $wl !== [], 'Monitoring petugas workload dari DB');

echo "\nALL CORE FINAL TESTS (1–10) PASSED\n";
