<?php

declare(strict_types=1);

/**
 * Apply master seed (+ optional demo) for LOCAL development only.
 *
 * Usage:
 *   php scripts/seed_local.php           # master + demo, cleanup test cases
 *   php scripts/seed_local.php --master-only
 *   php scripts/seed_local.php --keep-cases
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;

App::boot($basePath);

$env = (string) config('app.env', 'production');
if ($env !== 'local') {
    fwrite(STDERR, "ABORT: APP_ENV={$env}. seed_local.php hanya untuk local.\n");
    exit(1);
}

$masterOnly = in_array('--master-only', $argv, true);
$keepCases = in_array('--keep-cases', $argv, true);

$db = Database::connection();

function runSqlFile(PDO $db, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$path}");
    }
    $db->exec($sql);
    echo 'OK  imported ' . basename($path) . "\n";
}

echo "APP_ENV=local — seeding...\n";

runSqlFile($db, $basePath . '/database/seed.sql');

// Fix LA priority if previously wrong
$db->exec(
    "UPDATE case_types
     SET is_dashboard_priority = 0, dashboard_group = NULL, updated_at = NOW()
     WHERE name LIKE 'LA.19-05%'"
);

if (!$keepCases) {
    // Local only: hapus case hasil testing/demo lama (bukan production user data)
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $db->exec('DELETE FROM case_histories');
    $db->exec('DELETE FROM cases');
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "OK  cleared local cases + histories\n";
}

if (!$masterOnly) {
    runSqlFile($db, $basePath . '/database/seed_demo.sql');
}

$counts = [
    'statuses' => (int) $db->query('SELECT COUNT(*) FROM case_statuses')->fetchColumn(),
    'sources' => (int) $db->query('SELECT COUNT(*) FROM case_sources')->fetchColumn(),
    'officers' => (int) $db->query('SELECT COUNT(*) FROM officers')->fetchColumn(),
    'case_types' => (int) $db->query('SELECT COUNT(*) FROM case_types')->fetchColumn(),
    'cases' => (int) $db->query('SELECT COUNT(*) FROM cases')->fetchColumn(),
];

echo "\nCOUNTS:\n";
foreach ($counts as $k => $v) {
    echo "  {$k}: {$v}\n";
}

echo "\nMASTER STATUS:\n";
foreach ($db->query('SELECT slug, name FROM case_statuses ORDER BY sort_order')->fetchAll() as $r) {
    echo "  {$r['slug']} | {$r['name']}\n";
}
echo "MASTER SUMBER:\n";
foreach ($db->query('SELECT id, name FROM case_sources ORDER BY id')->fetchAll() as $r) {
    echo "  {$r['id']} | {$r['name']}\n";
}
echo "MASTER PETUGAS:\n";
foreach ($db->query('SELECT name FROM officers ORDER BY id')->fetchAll() as $r) {
    echo "  {$r['name']}\n";
}
echo "MASTER JENIS KASUS:\n";
foreach ($db->query('SELECT name, dashboard_group, is_dashboard_priority FROM case_types ORDER BY id')->fetchAll() as $r) {
    $g = $r['dashboard_group'] ?? '—';
    $p = (int) $r['is_dashboard_priority'];
    echo "  {$r['name']} | {$g} | priority={$p}\n";
}
echo "DEMO CASES:\n";
$sql = "SELECT c.case_number, c.npwp, c.taxpayer_name, ct.name AS type_name,
               cs.name AS status_name, src.name AS source_name, o.name AS officer_name, c.due_date
        FROM cases c
        JOIN case_types ct ON ct.id = c.case_type_id
        JOIN case_statuses cs ON cs.id = c.status_id
        JOIN case_sources src ON src.id = c.source_id
        JOIN officers o ON o.id = c.officer_id
        ORDER BY c.case_number";
foreach ($db->query($sql)->fetchAll() as $r) {
    echo sprintf(
        "  %s | %s | %s | %s | %s | %s | %s | %s\n",
        $r['case_number'],
        $r['npwp'],
        $r['taxpayer_name'],
        $r['type_name'],
        $r['status_name'],
        $r['source_name'],
        $r['officer_name'],
        $r['due_date']
    );
}

echo "\nDONE seed_local\n";
