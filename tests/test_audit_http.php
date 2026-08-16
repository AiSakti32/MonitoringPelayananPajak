<?php

declare(strict_types=1);

/**
 * HTTP smoke: admin can open Audit Log; petugas gets 403.
 * Requires php built-in server on APP_URL (default http://localhost:8080).
 */

$base = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
$cookie = tempnam(sys_get_temp_dir(), 'klc');

function req(string $method, string $url, ?string $cookieFile, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
    ]);
    if (!empty($opts['post'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['post']));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = explode("\r\n\r\n", (string) $raw, 2);
    return ['code' => $code, 'headers' => $parts[0] ?? '', 'body' => $parts[1] ?? ''];
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    throw new RuntimeException('CSRF token not found');
}

// Login page
$loginPage = req('GET', $base . '/login', $cookie);
if ($loginPage['code'] !== 200) {
    fwrite(STDERR, "Login page failed: {$loginPage['code']}\n");
    exit(1);
}
$csrf = extractCsrf($loginPage['body']);

$login = req('POST', $base . '/login', $cookie, [
    'post' => ['_csrf' => $csrf, 'username' => 'admin', 'password' => 'Admin123!'],
]);
echo "Admin login HTTP {$login['code']}\n";

$audit = req('GET', $base . '/audit-logs', $cookie);
echo "Admin /audit-logs HTTP {$audit['code']}\n";
if ($audit['code'] !== 200) {
    fwrite(STDERR, "FAIL admin audit page\n");
    exit(1);
}
if (str_contains($audit['body'], 'akan diimplementasikan') || str_contains($audit['body'], 'coming soon')) {
    fwrite(STDERR, "FAIL still placeholder\n");
    exit(1);
}
if (!str_contains($audit['body'], 'Audit Log') && !str_contains($audit['body'], 'audit')) {
    fwrite(STDERR, "FAIL unexpected audit body\n");
    exit(1);
}
echo "OK admin audit page renders (no placeholder)\n";

// Ensure petugas user exists
$basePath = dirname(__DIR__);
require_once $basePath . '/app/bootstrap.php';
use App\Core\App;
use App\Core\Database;
App::boot($basePath);
$db = Database::connection();
$officerId = (int) $db->query('SELECT id FROM officers WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$existing = $db->query("SELECT id FROM users WHERE username='petugas1' LIMIT 1")->fetchColumn();
if (!$existing) {
    $hash = password_hash('Petugas123!', PASSWORD_DEFAULT);
    $db->prepare(
        'INSERT INTO users (username, email, password_hash, full_name, role, officer_id, is_active, created_at, updated_at)
         VALUES ("petugas1", NULL, ?, "Petugas Satu", "petugas", ?, 1, NOW(), NOW())'
    )->execute([$hash, $officerId]);
    echo "Created petugas1\n";
}

$cookie2 = tempnam(sys_get_temp_dir(), 'klp');
$lp = req('GET', $base . '/login', $cookie2);
$csrf2 = extractCsrf($lp['body']);
req('POST', $base . '/login', $cookie2, [
    'post' => ['_csrf' => $csrf2, 'username' => 'petugas1', 'password' => 'Petugas123!'],
]);
$denied = req('GET', $base . '/audit-logs', $cookie2);
echo "Petugas /audit-logs HTTP {$denied['code']}\n";
if ($denied['code'] !== 403) {
    fwrite(STDERR, "FAIL expected 403 for petugas, got {$denied['code']}\n");
    exit(1);
}
echo "OK petugas blocked with 403\n";
echo "ALL HTTP AUDIT SMOKE PASSED\n";
