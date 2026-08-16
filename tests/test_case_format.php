<?php

declare(strict_types=1);

/**
 * Offline format tests (no database required).
 *   php tests/test_case_format.php
 */

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    echo "OK  {$msg}\n";
}

$caseOk = ['P0000000001', 'C1234567890', 'a1234567890'];
$caseBad = ['', 'P123', '12345678901', 'PP1234567890', 'P12345678901', 'P123456789A'];

foreach ($caseOk as $n) {
    $n = strtoupper($n);
    assert_true((bool) preg_match('/^[A-Z][0-9]{10}$/', $n), "Nomor valid: {$n}");
}
foreach ($caseBad as $n) {
    assert_true(!preg_match('/^[A-Z][0-9]{10}$/', strtoupper($n)), "Nomor invalid ditolak: {$n}");
}

$npwpOk = '0123456789012345';
$npwpBad = ['123', '01234567890123456', 'ABCDEFGHABCDEFGH'];
assert_true((bool) preg_match('/^[0-9]{16}$/', $npwpOk), 'NPWP 16 digit valid');
foreach ($npwpBad as $n) {
    assert_true(!preg_match('/^[0-9]{16}$/', $n), "NPWP invalid ditolak: {$n}");
}

// Simulate uniqueness rule conceptually
$set = [];
$n = 'P1234567890';
assert_true(!isset($set[$n]), 'First insert allowed');
$set[$n] = ['status' => 'Diproses'];
assert_true(isset($set[$n]), 'Second insert blocked — must update');
$set[$n]['status'] = 'Selesai';
assert_true($set[$n]['status'] === 'Selesai' && count($set) === 1, 'Update in-place keeps 1 record');

echo "\nALL FORMAT / UNIQUENESS CONCEPT TESTS PASSED\n";
echo "NOTE: Jalankan juga php tests/test_case_upsert.php saat database aktif.\n";
