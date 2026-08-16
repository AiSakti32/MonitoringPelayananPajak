<?php

declare(strict_types=1);

/**
 * Offline tests for deadline classification (no DB).
 *   php tests/test_deadline_classifier.php
 */

$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Services\DeadlineClassifier;

App::boot($basePath);

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "OK  {$msg}\n";
}

$today = '2026-08-11';

$selesai = DeadlineClassifier::classify('2026-08-01', true, $today);
assert_true($selesai['key'] === 'selesai', 'Selesai tidak masuk monitoring aktif');

$overdue = DeadlineClassifier::classify('2026-08-09', false, $today);
assert_true($overdue['key'] === 'overdue', 'due < today = terlambat');

$hariIni = DeadlineClassifier::classify('2026-08-11', false, $today);
assert_true($hariIni['key'] === 'today', 'due = today = hari ini');

$h3a = DeadlineClassifier::classify('2026-08-12', false, $today);
$h3b = DeadlineClassifier::classify('2026-08-14', false, $today);
assert_true($h3a['key'] === 'h3' && $h3b['key'] === 'h3', 'sisa 1-3 = H-3');

$h5a = DeadlineClassifier::classify('2026-08-15', false, $today);
$h5b = DeadlineClassifier::classify('2026-08-16', false, $today);
assert_true($h5a['key'] === 'h5' && $h5b['key'] === 'h5', 'sisa 4-5 = H-5');

$normal = DeadlineClassifier::classify('2026-08-17', false, $today);
assert_true($normal['key'] === 'normal', 'sisa >5 = normal');

// Mutual exclusive: H-3 not also H-5
assert_true($h3b['key'] !== 'h5', 'H-3 tidak digandakan ke H-5');

$days = DeadlineClassifier::daysRemaining('2026-08-09', false, $today);
assert_true($days === -2, 'daysRemaining overdue = -2');

assert_true(DeadlineClassifier::normalizeKey('terlambat') === 'overdue', 'normalize terlambat');
assert_true(DeadlineClassifier::normalizeKey('hari_ini') === 'today', 'normalize hari_ini');
assert_true(DeadlineClassifier::priorityLabel('overdue') === 'Terlambat', 'priorityLabel overdue');

echo "\nALL DEADLINE CLASSIFIER TESTS PASSED\n";
