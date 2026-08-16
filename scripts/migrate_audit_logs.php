<?php

declare(strict_types=1);

/**
 * Non-destructive: add module/description/old_values/new_values to audit_logs if missing.
 *
 *   php scripts/migrate_audit_logs.php
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
$db = Database::connection();

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

$adds = [
    'module' => "ADD COLUMN `module` VARCHAR(50) NULL DEFAULT NULL AFTER `action`",
    'description' => "ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL AFTER `entity_id`",
    'old_values' => "ADD COLUMN `old_values` JSON NULL AFTER `description`",
    'new_values' => "ADD COLUMN `new_values` JSON NULL AFTER `old_values`",
];

foreach ($adds as $col => $ddl) {
    if (columnExists($db, 'audit_logs', $col)) {
        echo "OK  column {$col} already exists\n";
        continue;
    }
    $db->exec("ALTER TABLE `audit_logs` {$ddl}");
    echo "OK  added column {$col}\n";
}

if (!indexExists($db, 'audit_logs', 'idx_audit_logs_module')) {
    $db->exec('ALTER TABLE `audit_logs` ADD KEY `idx_audit_logs_module` (`module`)');
    echo "OK  added index idx_audit_logs_module\n";
} else {
    echo "OK  index idx_audit_logs_module exists\n";
}

if (!indexExists($db, 'audit_logs', 'idx_audit_logs_entity')) {
    $db->exec('ALTER TABLE `audit_logs` ADD KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`)');
    echo "OK  added index idx_audit_logs_entity\n";
} else {
    echo "OK  index idx_audit_logs_entity exists\n";
}

echo "DONE migrate_audit_logs\n";
